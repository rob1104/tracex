<?php

namespace App\Jobs;

use App\Models\FacebookImportLog;
use App\Services\GoogleDrive\FacebookDriveSyncService;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class DownloadFacebookExportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importLogId,
        public ?string $localDir = null
    ) {}

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->importLogId;
    }

    /**
     * Execute the job: download the export folder from Google Drive and initiate processing.
     *
     * @throws Exception
     */
    public function handle(FacebookDriveSyncService $syncService): void
    {
        Log::info("Starting download job for import log ID [{$this->importLogId}]");

        $importLog = FacebookImportLog::find($this->importLogId);
        if (! $importLog) {
            Log::warning("Import log not found for ID [{$this->importLogId}] in DownloadFacebookExportJob");

            return;
        }

        $syncService->downloadFolder($importLog, $this->localDir);
    }

    /**
     * Handle a job failure after exhausting all attempts.
     */
    public function failed(?Throwable $exception): void
    {
        Log::critical("DownloadFacebookExportJob failed definitively for import log ID [{$this->importLogId}] after maximum attempts", [
            'error' => $exception?->getMessage(),
        ]);

        if ($this->localDir && File::exists($this->localDir)) {
            File::deleteDirectory($this->localDir);
        }

        $importLog = FacebookImportLog::find($this->importLogId);
        if ($importLog) {
            $importLog->update([
                'status' => 'failed',
                'error_message' => $exception ? "Download failed after maximum attempts: {$exception->getMessage()}" : 'Download failed after maximum attempts',
            ]);
        }
    }
}
