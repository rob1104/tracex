<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookExportJob;
use App\Models\EmailAccount;
use App\Models\FacebookComment;
use App\Models\FacebookImportLog;
use App\Models\FacebookUser;
use App\Models\Profile;
use App\Models\User;
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

        file_put_contents($this->testDirectory.'/comments.json', json_encode([
            [
                'timestamp' => 1791500000,
                'data' => [
                    ['comment' => [
                        'comment' => 'Comentario de prueba',
                        'timestamp' => 1791500000,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->assertDirectoryExists($this->testDirectory);

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);
        $job->handle();

        $log->refresh();
        $this->assertSame('pending_assignment', $log->status);
        $this->assertDirectoryDoesNotExist($this->testDirectory);
    }

    public function test_it_completes_when_profile_and_user_match(): void
    {
        $user = User::factory()->create(['role' => 'user', 'name' => 'Ana Garcia']);
        $email = EmailAccount::create([
            'email' => 'ana@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Ana Garcia',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);
        $profile->users()->attach($user->id);

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_test_match',
            'file_name' => 'meta-2026-Oct-06-11-05-44',
            'status' => 'downloaded',
        ]);

        file_put_contents($this->testDirectory.'/profile_information.json', json_encode([
            'profile_v2' => ['name' => ['full_name' => 'Ana Garcia']],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($this->testDirectory.'/comments.json', json_encode([
            [
                'timestamp' => 1791500000,
                'data' => [
                    ['comment' => [
                        'comment' => 'Comentario de prueba',
                        'timestamp' => 1791500000,
                    ]],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);
        $job->handle();

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertDirectoryDoesNotExist($this->testDirectory);
    }

    public function test_it_cleans_temporary_directory_and_marks_failed_on_definitive_failure(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_test_failed',
            'file_name' => 'meta-2026-Oct-06-failed',
            'status' => 'processing',
        ]);

        file_put_contents($this->testDirectory.'/test.json', json_encode(['foo' => 'bar']));
        $this->assertDirectoryExists($this->testDirectory);

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);
        $job->failed(new \Exception('Unrecoverable parsing exception'));

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Unrecoverable parsing exception', $log->error_message);
        $this->assertDirectoryDoesNotExist($this->testDirectory);
    }

    public function test_it_fails_and_preserves_existing_data_when_export_structure_is_invalid(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_invalid_struct',
            'file_name' => 'meta-invalid',
            'status' => 'downloaded',
        ]);

        $user = FacebookUser::create([
            'import_log_id' => $log->id,
            'metadata' => [],
        ]);

        FacebookComment::create([
            'facebook_user_id' => $user->id,
            'import_log_id' => $log->id,
            'content' => 'Comentario previo existente',
            'character_count' => 27,
            'word_count' => 3,
            'published_at' => now(),
        ]);

        // Archivo que no corresponde a ningún formato conocido
        file_put_contents($this->testDirectory.'/comments.json', json_encode([
            'unsupported_format' => ['data' => 'unknown'],
        ]));

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);

        try {
            $job->handle();
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Formato JSON no reconocido', $e->getMessage());
        }

        $log->refresh();
        $this->assertSame('failed', $log->status);

        // Los datos previos NO deben haberse eliminado
        $this->assertDatabaseHas('facebook_comments', [
            'import_log_id' => $log->id,
            'content' => 'Comentario previo existente',
        ]);
        $this->assertDatabaseHas('facebook_users', [
            'id' => $user->id,
        ]);
    }

    public function test_it_fails_explicitly_when_export_has_zero_extractable_records(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_zero_records',
            'file_name' => 'meta-zero',
            'status' => 'downloaded',
        ]);

        // Archivo con formato válido pero sin registros
        file_put_contents($this->testDirectory.'/comments.json', json_encode([]));

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);

        try {
            $job->handle();
            $this->fail('Expected exception for zero records was not thrown.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('no se extrajeron comentarios ni reacciones válidas', $e->getMessage());
        }

        $log->refresh();
        $this->assertSame('failed', $log->status);
    }

    public function test_it_omits_incomplete_reactions_and_does_not_default_to_like(): void
    {
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_reactions_test',
            'file_name' => 'meta-reactions',
            'status' => 'downloaded',
        ]);

        file_put_contents($this->testDirectory.'/reactions.json', json_encode([
            'reactions_v2' => [
                [
                    'timestamp' => 1791500000,
                    'title' => 'Reacción válida',
                    'data' => [
                        ['reaction' => ['reaction' => 'LOVE']],
                    ],
                ],
                [
                    'timestamp' => 1791500001,
                    'title' => 'Incompleta sin reaction',
                    'data' => [
                        ['reaction' => []],
                    ],
                ],
                [
                    'timestamp' => 1791500002,
                    'title' => 'Incompleta con data vacía',
                    'data' => [],
                ],
                [
                    'timestamp' => 1791500003,
                    'title' => 'Incompleta con reaction vacía',
                    'data' => [
                        ['reaction' => ['reaction' => '']],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $job = new ProcessFacebookExportJob($this->testDirectory, $log->id);
        $job->handle();

        $log->refresh();
        $this->assertSame('pending_assignment', $log->status);
        $this->assertSame(1, $log->total_reactions_extracted);

        // Se extrajo únicamente LOVE
        $this->assertDatabaseHas('facebook_reactions', [
            'import_log_id' => $log->id,
            'reaction_type' => 'LOVE',
        ]);

        // NO se debe haber asignado LIKE por defecto a los incompletos
        $this->assertDatabaseMissing('facebook_reactions', [
            'import_log_id' => $log->id,
            'reaction_type' => 'LIKE',
        ]);
    }
}
