<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookExportJob;
use App\Models\FacebookImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProcessFacebookExportJobTest extends TestCase
{
    use RefreshDatabase;

    protected string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDirectory = storage_path('framework/testing/export_job_test');
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
        parent::tearDown();
    }

    public function test_it_processes_export_and_cleans_local_directory(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_test_123',
            'file_name' => 'meta-2026-Oct-06-11-05-44',
            'status' => 'downloaded',
        ]);

        file_put_contents($this->testDirectory.'/test.json', json_encode(['foo' => 'bar']));
        $this->assertDirectoryExists($this->testDirectory);

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);
        $job->handle();

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertDirectoryDoesNotExist($this->testDirectory);
    }
}
