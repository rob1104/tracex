<?php

namespace Tests\Feature;

use App\Services\GoogleDrive\FacebookDriveSyncService;
use Illuminate\Console\Scheduling\Schedule;
use Mockery;
use Tests\TestCase;

class FacebookDriveSyncCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_schedules_sync_drive_and_queue_work_commands(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $hasSyncCommand = $events->contains(fn ($event) => str_contains((string) $event->command, 'facebook:sync-drive'));
        $queueWorkerEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'queue:work --stop-when-empty'));

        $this->assertTrue($hasSyncCommand, 'Expected facebook:sync-drive to be registered in scheduler.');
        $this->assertNotNull($queueWorkerEvent, 'Expected queue:work --stop-when-empty to be registered in scheduler.');
        $this->assertSame(storage_path('logs/laravel.log'), $queueWorkerEvent->output);
        $this->assertTrue($queueWorkerEvent->shouldAppendOutput);
    }

    public function test_it_executes_facebook_sync_drive_command(): void
    {
        $mockService = Mockery::mock(FacebookDriveSyncService::class);
        $mockService->shouldReceive('sync')
            ->once()
            ->with(true)
            ->andReturn([
                'scanned' => 2,
                'dispatched' => 2,
                'downloaded' => 0,
                'deleted_from_drive' => 0,
                'failed' => 0,
            ]);

        $this->app->instance(FacebookDriveSyncService::class, $mockService);

        $this->artisan('facebook:sync-drive')
            ->expectsOutput('Starting Google Drive sync for Facebook exports...')
            ->expectsOutput('Google Drive sync finished.')
            ->assertSuccessful();
    }

    public function test_it_executes_facebook_sync_drive_command_with_sync_flag(): void
    {
        $mockService = Mockery::mock(FacebookDriveSyncService::class);
        $mockService->shouldReceive('sync')
            ->once()
            ->with(false)
            ->andReturn([
                'scanned' => 2,
                'dispatched' => 0,
                'downloaded' => 2,
                'deleted_from_drive' => 2,
                'failed' => 0,
            ]);

        $this->app->instance(FacebookDriveSyncService::class, $mockService);

        $this->artisan('facebook:sync-drive --sync')
            ->expectsOutput('Starting Google Drive sync for Facebook exports...')
            ->expectsOutput('Google Drive sync finished.')
            ->assertSuccessful();
    }
}
