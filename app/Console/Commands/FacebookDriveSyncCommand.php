<?php

namespace App\Console\Commands;

use App\Services\GoogleDrive\FacebookDriveSyncService;
use Illuminate\Console\Command;

class FacebookDriveSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:sync-drive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan Google Drive for Facebook export folders, download JSON data, enforce Zero-Retention deletion, and dispatch processing jobs.';

    /**
     * Execute the console command.
     */
    public function handle(FacebookDriveSyncService $syncService): int
    {
        $this->info('Starting Google Drive sync for Facebook exports...');

        $metrics = $syncService->sync();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Scanned Folders', $metrics['scanned']],
                ['Downloaded Folders', $metrics['downloaded']],
                ['Permanently Deleted from Drive (Zero-Retention)', $metrics['deleted_from_drive']],
                ['Failed', $metrics['failed']],
            ]
        );

        $this->info('Google Drive sync finished.');

        return $metrics['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
