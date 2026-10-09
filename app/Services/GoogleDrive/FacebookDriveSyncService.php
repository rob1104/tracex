<?php

namespace App\Services\GoogleDrive;

use App\Jobs\DeleteDriveFileJob;
use App\Jobs\DownloadFacebookExportJob;
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
     * Execute the sync pipeline: scan Drive for export folders, and dispatch download jobs
     * or execute downloads synchronously.
     *
     * @return array{scanned: int, dispatched: int, downloaded: int, deleted_from_drive: int, failed: int}
     */
    public function sync(bool $async = true): array
    {
        $metrics = [
            'scanned' => 0,
            'dispatched' => 0,
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
                'downloaded_at' => null,
            ]);

            if ($async) {
                // Dispatch download of this folder to queue to prevent PHP execution timeouts
                DownloadFacebookExportJob::dispatch($importLog->id, $localDir);
                $metrics['dispatched']++;
            } else {
                try {
                    $this->downloadFolder($importLog, $localDir);
                    $metrics['downloaded']++;
                    if ($importLog->drive_deleted_at !== null) {
                        $metrics['deleted_from_drive']++;
                    }
                } catch (Exception $e) {
                    $metrics['failed']++;
                }
            }
        }

        return $metrics;
    }

    /**
     * Download all JSON files for a given import log, enforce Zero-Retention, and dispatch the parsing job.
     *
     * @return array<string, int>
     *
     * @throws Exception
     */
    public function downloadFolder(FacebookImportLog $importLog, ?string $localDir = null): array
    {
        $folderId = $importLog->drive_file_id;
        $folderName = $importLog->file_name;
        $localDir = $localDir ?? "{$this->storageDirectory}/{$folderId}";

        try {
            // 1. Download all JSON files in the export folder hierarchy
            $downloadedFiles = $this->driveClient->downloadExportFolder($folderId, $localDir);

            if (empty($downloadedFiles)) {
                throw new Exception("No JSON files found in export folder [{$folderName}]");
            }

            $totalBytes = array_sum($downloadedFiles);
            $importLog->update([
                'file_size_bytes' => $totalBytes,
                'status' => 'downloaded',
                'downloaded_at' => now(),
                'metadata' => [
                    'files' => array_keys($downloadedFiles),
                ],
            ]);

            // 2. Tarea 3.3: Regla de Cero-Retención (Eliminación inmediata de Drive)
            $this->cleanDriveFile($importLog);

            // 3. Dispatch async parsing job
            ProcessFacebookExportJob::dispatch($localDir, $importLog->id);

            return $downloadedFiles;
        } catch (Exception $e) {
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

            throw $e;
        }
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
