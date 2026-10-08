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
            ->andReturn([
                'scanned' => 2,
                'downloaded' => 2,
                'deleted_from_drive' => 2,
                'failed' => 0,
            ]);

        $this->app->instance(FacebookDriveSyncService::class, $mockService);

        $this->artisan('facebook:sync-drive')
            ->expectsOutput('Starting Google Drive sync for Facebook exports...')
            ->expectsOutput('Google Drive sync finished.')
            ->assertSuccessful();
    }
}
