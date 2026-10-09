<?php

namespace App\Jobs;

use App\Models\FacebookImportLog;
use App\Services\GoogleDrive\GoogleDriveClient;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeleteDriveFileJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public array $backoff = [30, 60, 120, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $driveFileId,
        public int $importLogId
    ) {}

    /**
     * Execute the job to permanently delete the file from Google Drive (Zero-Retention Rule).
     *
     * @throws Exception
     */
    public function handle(GoogleDriveClient $driveClient): void
    {
        Log::info("Retrying Zero-Retention deletion for drive file [{$this->driveFileId}] via queue");

        $driveClient->deleteFile($this->driveFileId);

        // Record the destruction timestamp in the import log
        FacebookImportLog::where('id', $this->importLogId)->update([
            'drive_deleted_at' => now(),
            'error_message' => null,
        ]);

        Log::info("Successfully completed delayed Zero-Retention deletion for drive file [{$this->driveFileId}]");
    }

    /**
     * Handle a job failure after exhausting all attempts.
     */
    public function failed(?Exception $exception): void
    {
        Log::critical("Critical: Failed to enforce Zero-Retention Rule for drive file [{$this->driveFileId}] after maximum retries.", [
            'import_log_id' => $this->importLogId,
            'error' => $exception?->getMessage(),
        ]);

        FacebookImportLog::where('id', $this->importLogId)->update([
            'error_message' => 'Critical: Zero-Retention deletion failed after maximum retries: '.($exception?->getMessage() ?? 'Unknown error'),
        ]);
    }
}
