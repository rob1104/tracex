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
    protected $signature = 'facebook:sync-drive {--sync : Execute folder downloads synchronously instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan Google Drive for Facebook export folders, dispatch/download JSON data, enforce Zero-Retention deletion, and dispatch processing jobs.';

    /**
     * Execute the console command.
     */
    public function handle(FacebookDriveSyncService $syncService): int
    {
        $this->info('Starting Google Drive sync for Facebook exports...');

        $isSync = (bool) $this->option('sync');
        $metrics = $syncService->sync(async: ! $isSync);

        $rows = [
            ['Scanned Folders', $metrics['scanned']],
        ];

        if (isset($metrics['dispatched']) && $metrics['dispatched'] > 0) {
            $rows[] = ['Dispatched Folders to Queue', $metrics['dispatched']];
        }

        $rows[] = ['Downloaded Folders', $metrics['downloaded'] ?? 0];
        $rows[] = ['Permanently Deleted from Drive (Zero-Retention)', $metrics['deleted_from_drive'] ?? 0];
        $rows[] = ['Failed', $metrics['failed'] ?? 0];

        $this->table(['Metric', 'Count'], $rows);

        $this->info('Google Drive sync finished.');

        return ($metrics['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
