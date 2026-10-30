<?php

namespace App\Services\GoogleDrive;

use App\Jobs\DeleteDriveFileJob;
use App\Jobs\DownloadFacebookExportJob;
use App\Jobs\ProcessFacebookExportJob;
use App\Models\FacebookImportLog;
use App\Services\FacebookProfileIdentifier;
use App\Services\ProfileAssignmentService;
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
     * @return array{scanned: int, skipped: int, dispatched: int, downloaded: int, deleted_from_drive: int, failed: int}
     */
    public function sync(bool $async = true): array
    {
        $metrics = [
            'scanned' => 0,
            'skipped' => 0,
            'dispatched' => 0,
            'downloaded' => 0,
            'deleted_from_drive' => 0,
            'failed' => 0,
        ];

        Log::info('[FacebookDriveSync] Scanning Google Drive for export folders...', [
            'async' => $async,
            'storage_directory' => $this->storageDirectory,
        ]);

        try {
            $folders = $this->driveClient->listExportFolders();
        } catch (Exception $e) {
            $metrics['error'] = $e->getMessage();

            Log::error('[FacebookDriveSync] Failed to list Google Drive export folders during sync', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'configured_folder_id' => config('services.google.drive.folder_id', 'root'),
            ]);

            return $metrics;
        }

        $metrics['scanned'] = count($folders);

        if ($metrics['scanned'] === 0) {
            Log::warning('[FacebookDriveSync] listExportFolders() returned 0 folders matching criteria.', [
                'configured_folder_id' => config('services.google.drive.folder_id', 'root'),
            ]);

            return $metrics;
        }

        Log::info('[FacebookDriveSync] Export folders discovered in Google Drive', [
            'count' => $metrics['scanned'],
            'folders' => array_map(fn (array $f) => [
                'id' => $f['id'] ?? null,
                'name' => $f['name'] ?? null,
                'createdTime' => $f['createdTime'] ?? null,
            ], $folders),
        ]);

        foreach ($folders as $folder) {
            $folderId = $folder['id'];
            $folderName = $folder['name'];

            // Idempotency: skip if already logged and downloaded/processed, but retry records stuck before download
            $existingLog = FacebookImportLog::where('drive_file_id', $folderId)->first();
            $isStuckPendingDownload = $existingLog !== null
                && $existingLog->status === 'downloaded'
                && $existingLog->downloaded_at === null;

            if ($existingLog && ! $isStuckPendingDownload) {
                $metrics['skipped']++;
                Log::info('[FacebookDriveSync] Skipping folder because drive_file_id already exists in facebook_import_logs', [
                    'folder_id' => $folderId,
                    'folder_name' => $folderName,
                    'existing_log_id' => $existingLog->id,
                    'existing_status' => $existingLog->status,
                    'downloaded_at' => $existingLog->downloaded_at?->toDateTimeString(),
                    'drive_deleted_at' => $existingLog->drive_deleted_at?->toDateTimeString(),
                    'error_message' => $existingLog->error_message,
                ]);

                continue;
            }

            $localDir = "{$this->storageDirectory}/{$folderId}";

            // Resolver perfil preliminarmente si el nombre de la carpeta contiene el Facebook ID (facebook-<id>-...)
            $preliminaryIdentifier = FacebookProfileIdentifier::normalize(
                preg_match('/facebook-(\d{5,})(?:[-_\/\\\\]|$)/i', $folderName, $idMatch) ? "id:{$idMatch[1]}" : null
            );
            $preliminaryResolution = $preliminaryIdentifier !== null
                ? app(ProfileAssignmentService::class)->resolve(['identifier' => $preliminaryIdentifier])
                : ['profile' => null, 'user' => null];
            $matchedProfile = $preliminaryResolution['profile'];
            $assignedUser = $preliminaryResolution['user'];

            // 1. Reuse stuck pending-download record or create initial log record
            if ($existingLog !== null) {
                $existingLog->update([
                    'file_name' => $folderName,
                    'profile_id' => $existingLog->profile_id ?? $matchedProfile?->id,
                    'user_id' => $existingLog->user_id ?? $assignedUser?->id,
                    'error_message' => null,
                ]);
                $importLog = $existingLog;

                Log::info('[FacebookDriveSync] Retrying folder stuck with downloaded_at = null', [
                    'import_log_id' => $importLog->id,
                    'folder_id' => $folderId,
                    'folder_name' => $folderName,
                ]);
            } else {
                $importLog = FacebookImportLog::create([
                    'drive_file_id' => $folderId,
                    'file_name' => $folderName,
                    'profile_id' => $matchedProfile?->id,
                    'user_id' => $assignedUser?->id,
                    'file_size_bytes' => 0,
                    'status' => 'downloaded',
                    'downloaded_at' => null,
                ]);
            }

            if ($async) {
                // Dispatch download of this folder to queue to prevent PHP execution timeouts
                DownloadFacebookExportJob::dispatch($importLog->id, $localDir);
                $metrics['dispatched']++;

                Log::info('[FacebookDriveSync] Dispatched DownloadFacebookExportJob to queue', [
                    'import_log_id' => $importLog->id,
                    'folder_id' => $folderId,
                    'folder_name' => $folderName,
                    'local_dir' => $localDir,
                    'queue_connection' => config('queue.default'),
                ]);
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

        Log::info("[FacebookDriveSync] Starting download of export folder [{$folderName}]", [
            'import_log_id' => $importLog->id,
            'folder_id' => $folderId,
            'local_dir' => $localDir,
        ]);

        try {
            // 1. Download all JSON files in the export folder hierarchy
            $downloadedFiles = $this->driveClient->downloadExportFolder($folderId, $localDir);

            if (empty($downloadedFiles)) {
                throw new Exception("No JSON files found in export folder [{$folderName}]");
            }

            $totalBytes = array_sum($downloadedFiles);
            $filePaths = array_keys($downloadedFiles);

            $matchedProfileId = $importLog->profile_id;
            $assignedUserId = $importLog->user_id;

            if ($matchedProfileId === null) {
                $detectedId = null;
                foreach ([$folderName, ...$filePaths] as $candidatePath) {
                    if (preg_match('/facebook-(\d{5,})(?:[-_\/\\\\]|$)/i', (string) $candidatePath, $idMatch)) {
                        $detectedId = "id:{$idMatch[1]}";
                        break;
                    }
                }

                $preliminaryIdentifier = FacebookProfileIdentifier::normalize($detectedId);
                if ($preliminaryIdentifier !== null) {
                    $resolution = app(ProfileAssignmentService::class)->resolve(['identifier' => $preliminaryIdentifier]);
                    $matchedProfileId = $resolution['profile']?->id;
                    $assignedUserId = $assignedUserId ?? $resolution['user']?->id;
                }
            }

            $importLog->update([
                'profile_id' => $matchedProfileId,
                'user_id' => $assignedUserId,
                'file_size_bytes' => $totalBytes,
                'status' => 'downloaded',
                'downloaded_at' => now(),
                'metadata' => [
                    'files' => $filePaths,
                ],
            ]);

            Log::info("[FacebookDriveSync] Successfully downloaded export folder [{$folderName}]", [
                'import_log_id' => $importLog->id,
                'folder_id' => $folderId,
                'files_count' => count($downloadedFiles),
                'total_bytes' => $totalBytes,
                'files' => array_keys($downloadedFiles),
            ]);

            // 2. Tarea 3.3: Regla de Cero-Retención (Eliminación inmediata de Drive)
            $this->cleanDriveFile($importLog);

            // 3. Dispatch async parsing job
            ProcessFacebookExportJob::dispatch($localDir, $importLog->id);

            Log::info("[FacebookDriveSync] Dispatched ProcessFacebookExportJob for [{$folderName}]", [
                'import_log_id' => $importLog->id,
                'local_dir' => $localDir,
                'queue_connection' => config('queue.default'),
            ]);

            return $downloadedFiles;
        } catch (Exception $e) {
            Log::error("Failed processing export folder [{$folderName}] from Google Drive", [
                'import_log_id' => $importLog->id,
                'folder_id' => $folderId,
                'local_dir' => $localDir,
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
