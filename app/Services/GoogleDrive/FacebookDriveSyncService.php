<?php

namespace App\Services\GoogleDrive;

use App\Jobs\DeleteDriveFileJob;
use App\Jobs\ProcessFacebookExportJob;
use App\Models\FacebookImportLog;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FacebookDriveSyncService
{
    protected string $storageDirectory;

    /**
     * Create a new Facebook Drive Sync Service instance.
     */
    public function __construct(
        protected GoogleDriveClient $driveClient,
        ?string $storageDirectory = null
    ) {
        $this->storageDirectory = $storageDirectory ?? storage_path('app/temp_exports');
    }

    /**
     * Execute the full sync pipeline: scan Drive for export folders, download JSONs,
     * enforce Zero-Retention cloud destruction, and dispatch processing jobs.
     *
     * @return array{scanned: int, downloaded: int, deleted_from_drive: int, failed: int}
     */
    public function sync(): array
    {
        $metrics = [
            'scanned' => 0,
            'downloaded' => 0,
            'deleted_from_drive' => 0,
            'failed' => 0,
        ];

        try {
            $folders = $this->driveClient->listExportFolders();
        } catch (Exception $e) {
            Log::error('Failed to list Google Drive export folders during sync', ['error' => $e->getMessage()]);

            return $metrics;
        }

        $metrics['scanned'] = count($folders);

        foreach ($folders as $folder) {
            $folderId = $folder['id'];
            $folderName = $folder['name'];

            // Idempotency: skip if already logged
            if (FacebookImportLog::where('drive_file_id', $folderId)->exists()) {
                continue;
            }

            $localDir = "{$this->storageDirectory}/{$folderId}";

            // 1. Create initial log record
            $importLog = FacebookImportLog::create([
                'drive_file_id' => $folderId,
                'file_name' => $folderName,
                'file_size_bytes' => 0,
                'status' => 'downloaded',
                'downloaded_at' => now(),
            ]);

            try {
                // 2. Download all JSON files in the export folder hierarchy
                $downloadedFiles = $this->driveClient->downloadExportFolder($folderId, $localDir);

                if (empty($downloadedFiles)) {
                    throw new Exception("No JSON files found in export folder [{$folderName}]");
                }

                $totalBytes = array_sum($downloadedFiles);
                $importLog->update([
                    'file_size_bytes' => $totalBytes,
                    'metadata' => [
                        'files' => array_keys($downloadedFiles),
                    ],
                ]);

                $metrics['downloaded']++;

                // 3. Tarea 3.3: Regla de Cero-Retención (Eliminación inmediata de Drive)
                $deleted = $this->cleanDriveFile($importLog);
                if ($deleted) {
                    $metrics['deleted_from_drive']++;
                }

                // 4. Dispatch async parsing job
                ProcessFacebookExportJob::dispatch($localDir, $importLog->id);

            } catch (Exception $e) {
                $metrics['failed']++;
                Log::error("Failed processing export folder [{$folderName}] from Google Drive", [
                    'folder_id' => $folderId,
                    'error' => $e->getMessage(),
                ]);

                $importLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                // Clean corrupt local directory if needed
                if (File::exists($localDir)) {
                    File::deleteDirectory($localDir);
                }
            }
        }

        return $metrics;
    }

    /**
     * Tarea 3.3: Enforce Zero-Retention Policy by permanently deleting the folder from Google Drive.
     * Certifies destruction by setting `drive_deleted_at = now()`.
     */
    public function cleanDriveFile(FacebookImportLog $log): bool
    {
        try {
            $this->driveClient->deleteFile($log->drive_file_id);

            // Audit timestamp of cloud destruction
            $log->update([
                'drive_deleted_at' => now(),
            ]);

            Log::info("Zero-retention deletion completed for log [{$log->id}] and drive folder [{$log->drive_file_id}]");

            return true;
        } catch (Exception $e) {
            Log::error("Zero-retention deletion failed for folder [{$log->drive_file_id}]. Scheduling retry job.", [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'error_message' => "Zero-retention deletion pending retry: {$e->getMessage()}",
            ]);

            // Dispatch fallback retry job with backoff to guarantee deletion
            DeleteDriveFileJob::dispatch($log->drive_file_id, $log->id);

            return false;
        }
    }
}
