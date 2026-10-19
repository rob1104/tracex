<?php

namespace App\Console\Commands;

use App\Services\GoogleDrive\FacebookDriveSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        $isSync = (bool) $this->option('sync');

        Log::info('[FacebookDriveSync] Starting facebook:sync-drive command', [
            'mode' => $isSync ? 'sync' : 'async',
            'queue_connection' => config('queue.default'),
            'configured_folder_id' => config('services.google.drive.folder_id'),
            'schedule_frequency' => config('services.google.drive.schedule_frequency'),
            'has_client_id' => filled(config('services.google.drive.client_id')),
            'has_client_secret' => filled(config('services.google.drive.client_secret')),
            'has_refresh_token' => filled(config('services.google.drive.refresh_token')),
            'config_cached' => app()->configurationIsCached(),
        ]);

        $this->info('Starting Google Drive sync for Facebook exports...');

        $metrics = $syncService->sync(async: ! $isSync);

        $rows = [
            ['Scanned Folders', $metrics['scanned']],
        ];

        if (isset($metrics['skipped']) && $metrics['skipped'] > 0) {
            $rows[] = ['Skipped (Already Logged)', $metrics['skipped']];
        }

        if (isset($metrics['dispatched']) && $metrics['dispatched'] > 0) {
            $rows[] = ['Dispatched Folders to Queue', $metrics['dispatched']];
        }

        $rows[] = ['Downloaded Folders', $metrics['downloaded'] ?? 0];
        $rows[] = ['Permanently Deleted from Drive (Zero-Retention)', $metrics['deleted_from_drive'] ?? 0];
        $rows[] = ['Failed', $metrics['failed'] ?? 0];

        $this->table(['Metric', 'Count'], $rows);

        if (! empty($metrics['error'])) {
            $this->error("Google Drive sync error: {$metrics['error']}");
            Log::error('[FacebookDriveSync] Command aborted due to Google Drive API/Auth error.', $metrics);
        } elseif (($metrics['scanned'] ?? 0) === 0) {
            Log::warning('[FacebookDriveSync] Command finished: 0 export folders found in Google Drive.', $metrics);
        } else {
            Log::info('[FacebookDriveSync] Command finished.', $metrics);
        }

        $this->info('Google Drive sync finished.');

        return ($metrics['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
