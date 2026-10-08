<?php

namespace App\Jobs;

use App\Models\FacebookImportLog;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessFacebookExportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $localPath,
        public int $importLogId
    ) {}

    /**
     * Execute the job: parse the local JSON export, perform bulk inserts, and delete local temporary data.
     *
     * @throws Exception
     */
    public function handle(): void
    {
        Log::info("Starting local processing for export [{$this->localPath}]");

        $importLog = FacebookImportLog::find($this->importLogId);
        if (! $importLog) {
            Log::warning("Import log not found for ID [{$this->importLogId}]");

            return;
        }

        $importLog->update(['status' => 'processing']);

        try {
            // Skeleton placeholder for Tarea 3.2:
            // 1. Read and parse JSON content in streaming/chunks.
            // 2. Extract participants, comments, reactions.
            // 3. Precalculate metrics (word_count, character_count).
            // 4. Perform bulk inserts into MariaDB.

            // 5. Update log status to completed
            $importLog->update([
                'status' => 'completed',
            ]);

            // 6. Delete local temporary files/directory to maintain minimal storage footprint
            if (File::isDirectory($this->localPath)) {
                File::deleteDirectory($this->localPath);
            } elseif (File::exists($this->localPath)) {
                File::delete($this->localPath);
            }

            Log::info("Finished processing and cleaned local files for export [{$this->importLogId}]");
        } catch (Exception $e) {
            Log::error("Failed parsing export path [{$this->localPath}]", [
                'error' => $e->getMessage(),
            ]);

            $importLog->update([
                'status' => 'failed',
                'error_message' => "Parsing failed: {$e->getMessage()}",
            ]);

            throw $e;
        }
    }
}
