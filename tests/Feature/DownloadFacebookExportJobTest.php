<?php

namespace Tests\Feature;

use App\Jobs\DownloadFacebookExportJob;
use App\Models\FacebookImportLog;
use App\Services\GoogleDrive\FacebookDriveSyncService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class DownloadFacebookExportJobTest extends TestCase
{
    use RefreshDatabase;

    protected string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDirectory = storage_path('framework/testing/download_job_test');
        if (File::exists($this->testDirectory)) {
            File::deleteDirectory($this->testDirectory);
        }
        File::makeDirectory($this->testDirectory, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testDirectory)) {
            File::deleteDirectory($this->testDirectory);
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_handles_download_and_invokes_service_download_folder(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_dl_123',
            'file_name' => 'meta-2026-Oct-06-11-05-44',
            'status' => 'downloaded',
        ]);

        $mockService = Mockery::mock(FacebookDriveSyncService::class);
        $mockService->shouldReceive('downloadFolder')
            ->once()
            ->with(Mockery::on(function ($importLog) use ($log) {
                return $importLog->id === $log->id;
            }), $this->testDirectory)
            ->andReturn(['comments.json' => 1024]);

        $job = new DownloadFacebookExportJob($log->id, $this->testDirectory);
        $job->handle($mockService);

        $this->assertTrue(true);
    }

    public function test_it_cleans_temporary_directory_and_updates_status_on_failure(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_dl_fail',
            'file_name' => 'meta-2026-Oct-06-fail',
            'status' => 'downloaded',
        ]);

        file_put_contents($this->testDirectory.'/partial.json', '{"part":');
        $this->assertDirectoryExists($this->testDirectory);

        $job = new DownloadFacebookExportJob($log->id, $this->testDirectory);
        $job->failed(new Exception('Network timeout during download'));

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Network timeout during download', $log->error_message);
        $this->assertDirectoryDoesNotExist($this->testDirectory);
    }

    public function test_it_safely_handles_missing_import_log_in_handle(): void
    {
        $mockService = Mockery::mock(FacebookDriveSyncService::class);
        $mockService->shouldNotReceive('downloadFolder');

        $job = new DownloadFacebookExportJob(999999, $this->testDirectory);
        $job->handle($mockService);

        $this->assertTrue(true);
    }
}
