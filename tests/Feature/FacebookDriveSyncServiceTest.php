<?php

namespace Tests\Feature;

use App\Jobs\DeleteDriveFileJob;
use App\Jobs\ProcessFacebookExportJob;
use App\Models\FacebookImportLog;
use App\Services\GoogleDrive\FacebookDriveSyncService;
use App\Services\GoogleDrive\GoogleDriveClient;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class FacebookDriveSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $testStorageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStorageDir = storage_path('framework/testing/sync_test');
        if (File::exists($this->testStorageDir)) {
            File::deleteDirectory($this->testStorageDir);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testStorageDir)) {
            File::deleteDirectory($this->testStorageDir);
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_successfully_syncs_and_enforces_zero_retention(): void
    {
        Queue::fake();

        $mockClient = Mockery::mock(GoogleDriveClient::class);
        $mockClient->shouldReceive('listExportFolders')
            ->once()
            ->andReturn([
                [
                    'id' => 'folder_meta_1',
                    'name' => 'meta-2026-Oct-06-11-05-44',
                ],
            ]);

        $mockClient->shouldReceive('downloadExportFolder')
            ->once()
            ->with('folder_meta_1', $this->testStorageDir.'/folder_meta_1')
            ->andReturn([
                'profile_information.json' => 1024,
                'comments.json' => 2048,
            ]);

        $mockClient->shouldReceive('deleteFile')
            ->once()
            ->with('folder_meta_1')
            ->andReturn(true);

        $service = new FacebookDriveSyncService($mockClient, $this->testStorageDir);
        $metrics = $service->sync();

        $this->assertSame(1, $metrics['scanned']);
        $this->assertSame(1, $metrics['downloaded']);
        $this->assertSame(1, $metrics['deleted_from_drive']);
        $this->assertSame(0, $metrics['failed']);

        $this->assertDatabaseHas('facebook_import_logs', [
            'drive_file_id' => 'folder_meta_1',
            'file_name' => 'meta-2026-Oct-06-11-05-44',
            'file_size_bytes' => 3072,
            'status' => 'downloaded',
        ]);

        $log = FacebookImportLog::where('drive_file_id', 'folder_meta_1')->first();
        $this->assertNotNull($log->drive_deleted_at);

        Queue::assertPushed(ProcessFacebookExportJob::class, function ($job) use ($log) {
            return $job->importLogId === $log->id;
        });
    }

    public function test_it_skips_already_processed_folders_idempotently(): void
    {
        Queue::fake();

        FacebookImportLog::create([
            'drive_file_id' => 'folder_already_imported',
            'file_name' => 'meta-2026-Oct-06-11-05-44',
            'status' => 'completed',
        ]);

        $mockClient = Mockery::mock(GoogleDriveClient::class);
        $mockClient->shouldReceive('listExportFolders')
            ->once()
            ->andReturn([
                [
                    'id' => 'folder_already_imported',
                    'name' => 'meta-2026-Oct-06-11-05-44',
                ],
            ]);

        $mockClient->shouldNotReceive('downloadExportFolder');
        $mockClient->shouldNotReceive('deleteFile');

        $service = new FacebookDriveSyncService($mockClient, $this->testStorageDir);
        $metrics = $service->sync();

        $this->assertSame(1, $metrics['scanned']);
        $this->assertSame(0, $metrics['downloaded']);
        $this->assertSame(0, $metrics['deleted_from_drive']);
        $this->assertSame(0, $metrics['failed']);

        Queue::assertNothingPushed();
    }

    public function test_it_schedules_delete_job_when_zero_retention_deletion_fails(): void
    {
        Queue::fake();

        $mockClient = Mockery::mock(GoogleDriveClient::class);
        $mockClient->shouldReceive('listExportFolders')
            ->once()
            ->andReturn([
                [
                    'id' => 'folder_retry_delete',
                    'name' => 'meta-2026-Oct-06-11-05-44',
                ],
            ]);

        $mockClient->shouldReceive('downloadExportFolder')
            ->once()
            ->andReturn(['comments.json' => 500]);

        $mockClient->shouldReceive('deleteFile')
            ->once()
            ->with('folder_retry_delete')
            ->andThrow(new Exception('Drive API rate limited'));

        $service = new FacebookDriveSyncService($mockClient, $this->testStorageDir);
        $metrics = $service->sync();

        $this->assertSame(1, $metrics['downloaded']);
        $this->assertSame(0, $metrics['deleted_from_drive']);

        $log = FacebookImportLog::where('drive_file_id', 'folder_retry_delete')->first();
        $this->assertNull($log->drive_deleted_at);
        $this->assertStringContainsString('Zero-retention deletion pending retry', $log->error_message);

        Queue::assertPushed(DeleteDriveFileJob::class, function ($job) use ($log) {
            return $job->driveFileId === 'folder_retry_delete' && $job->importLogId === $log->id;
        });
    }
}
