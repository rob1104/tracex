<?php

namespace Tests\Feature;

use App\Services\GoogleDrive\FacebookDriveSyncService;
use Mockery;
use Tests\TestCase;

class FacebookDriveSyncCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
