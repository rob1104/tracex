<?php

namespace Tests\Feature;

use App\Jobs\DeleteDriveFileJob;
use App\Models\FacebookImportLog;
use App\Services\GoogleDrive\GoogleDriveClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeleteDriveFileJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_deletes_drive_file_and_updates_import_log(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_to_clean',
            'file_name' => 'meta-2026-Oct-06-11-05-44',
            'status' => 'downloaded',
            'error_message' => 'Pending retry',
        ]);

        $mockClient = Mockery::mock(GoogleDriveClient::class);
        $mockClient->shouldReceive('deleteFile')
            ->once()
            ->with('folder_to_clean')
            ->andReturn(true);

        $job = new DeleteDriveFileJob('folder_to_clean', $log->id);
        $job->handle($mockClient);

        $log->refresh();
        $this->assertNotNull($log->drive_deleted_at);
        $this->assertNull($log->error_message);
    }
}
