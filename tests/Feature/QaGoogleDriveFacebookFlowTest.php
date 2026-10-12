<?php

namespace Tests\Feature;

use App\Jobs\DeleteDriveFileJob;
use App\Jobs\ProcessFacebookExportJob;
use App\Models\EmailAccount;
use App\Models\FacebookComment;
use App\Models\FacebookImportLog;
use App\Models\FacebookReaction;
use App\Models\FacebookUser;
use App\Models\Profile;
use App\Models\User;
use App\Services\GoogleDrive\FacebookDriveSyncService;
use App\Services\GoogleDrive\GoogleDriveClient;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class QaGoogleDriveFacebookFlowTest extends TestCase
{
    use RefreshDatabase;

    protected string $qaTempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qaTempDir = storage_path('framework/testing/qa_flow_test');
        if (File::exists($this->qaTempDir)) {
            File::deleteDirectory($this->qaTempDir);
        }
        File::makeDirectory($this->qaTempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->qaTempDir)) {
            File::deleteDirectory($this->qaTempDir);
        }
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test Case 1: E2E Full Positive Path.
     * Drive scan -> Sync download -> Remote Zero-Retention -> JSON Parsing -> DB Ingestion -> Local Cleanup.
     */
    public function test_e2e_complete_flow_drive_scan_to_database_ingestion_and_zero_retention(): void
    {
        // 1. Arrange existing Facebook profile in DB
        $email = EmailAccount::create([
            'email' => 'qa_target@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['name' => 'Carlos Mendoza User', 'role' => 'user']);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Carlos Mendoza',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);
        $profile->users()->attach($user->id);

        $folderId = 'drive_meta_folder_001';
        $folderName = 'meta-2026-Oct-10-11-00-00';
        $downloadDir = "{$this->qaTempDir}/{$folderId}";

        // Mock Drive Client
        $mockClient = Mockery::mock(GoogleDriveClient::class);

        $mockClient->shouldReceive('listExportFolders')
            ->once()
            ->andReturn([
                [
                    'id' => $folderId,
                    'name' => $folderName,
                ],
            ]);

        // When downloadExportFolder is called, write realistic mock JSON files
        $mockClient->shouldReceive('downloadExportFolder')
            ->once()
            ->with($folderId, $downloadDir)
            ->andReturnUsing(function ($fId, $targetDir) {
                File::makeDirectory($targetDir, 0755, true, true);

                $profileJson = [
                    'profile_v2' => [
                        'name' => ['full_name' => 'Carlos Mendoza'],
                        'profile_uri' => 'https://www.facebook.com/profile.php?id=1000987654321',
                        'registration_timestamp' => 1600000000,
                    ],
                ];

                $commentsJson = [
                    'comments_v2' => [
                        [
                            'title' => 'Carlos Mendoza comentó en una publicación',
                            'timestamp' => 1760000000,
                            'data' => [
                                [
                                    'comment' => [
                                        'comment' => 'Excelente investigación sobre ciberseguridad.',
                                        'timestamp' => 1760000000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                $reactionsJson = [
                    'reactions_v2' => [
                        [
                            'title' => 'Reaccionó a la publicación de María',
                            'timestamp' => 1760000500,
                            'data' => [
                                [
                                    'reaction' => [
                                        'reaction' => 'LIKE',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'title' => 'Reaccionó al estado de Juan',
                            'timestamp' => 1760000600,
                            'data' => [
                                [
                                    'reaction' => [
                                        'reaction' => 'LOVE',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                File::put("{$targetDir}/profile_information.json", json_encode($profileJson));
                File::put("{$targetDir}/comments.json", json_encode($commentsJson));
                File::put("{$targetDir}/likes_and_reactions.json", json_encode($reactionsJson));

                return [
                    'profile_information.json' => 500,
                    'comments.json' => 800,
                    'likes_and_reactions.json' => 900,
                ];
            });

        // Zero-Retention expectation: Drive file MUST be deleted immediately
        $mockClient->shouldReceive('deleteFile')
            ->once()
            ->with($folderId)
            ->andReturn(true);

        $syncService = new FacebookDriveSyncService($mockClient, $this->qaTempDir);
        $this->app->instance(FacebookDriveSyncService::class, $syncService);

        // 2. Act: Execute Artisan command with synchronous processing flag
        $this->artisan('facebook:sync-drive --sync')
            ->assertSuccessful()
            ->run();

        // A. Verify Audit Log
        $this->assertDatabaseHas('facebook_import_logs', [
            'drive_file_id' => $folderId,
            'file_name' => $folderName,
            'status' => 'completed',
            'total_comments_extracted' => 1,
            'total_reactions_extracted' => 2,
        ]);

        $log = FacebookImportLog::where('drive_file_id', $folderId)->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->drive_deleted_at, 'Zero-Retention: drive_deleted_at timestamp must be recorded.');

        // B. Verify Facebook User linked to Profile
        $this->assertDatabaseHas('facebook_users', [
            'import_log_id' => $log->id,
            'profile_id' => $profile->id,
        ]);

        $fbUser = FacebookUser::where('import_log_id', $log->id)->first();
        $this->assertSame('id:1000987654321', $fbUser->metadata['export_identifier'] ?? null);

        // C. Verify Comments
        $this->assertDatabaseHas('facebook_comments', [
            'facebook_user_id' => $fbUser->id,
            'import_log_id' => $log->id,
            'content' => 'Excelente investigación sobre ciberseguridad.',
            'word_count' => 4,
        ]);

        // D. Verify Reactions
        $this->assertDatabaseHas('facebook_reactions', [
            'facebook_user_id' => $fbUser->id,
            'import_log_id' => $log->id,
            'reaction_type' => 'LIKE',
        ]);
        $this->assertDatabaseHas('facebook_reactions', [
            'facebook_user_id' => $fbUser->id,
            'import_log_id' => $log->id,
            'reaction_type' => 'LOVE',
        ]);

        // E. Verify Local Zero-Retention: local temporary directory MUST be removed
        $this->assertDirectoryDoesNotExist($downloadDir, 'Local Zero-Retention: temporary folder must be purged.');
    }

    /**
     * Test Case 2: Profile Auto-Matching variations (case insensitivity, ambiguous profiles, and non-matches).
     */
    public function test_profile_auto_matching_behavior(): void
    {
        $email = EmailAccount::create([
            'email' => 'qa_matching@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $singleProfile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'MARIA FERNANDA',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        // Process single profile (case mismatch in export: "maria fernanda")
        $folderDir = "{$this->qaTempDir}/match_test_single";
        File::makeDirectory($folderDir, 0755, true);
        File::put("{$folderDir}/profile_information.json", json_encode([
            'profile_v2' => [
                'name' => ['full_name' => 'maria fernanda'],
                'profile_uri' => 'https://facebook.com/maria.fernanda',
            ],
        ]));

        $log1 = FacebookImportLog::create([
            'drive_file_id' => 'folder_match_1',
            'file_name' => 'meta-maria',
            'status' => 'downloaded',
        ]);

        $job1 = new ProcessFacebookExportJob($folderDir, $log1->id);
        $job1->handle();

        $this->assertDatabaseHas('facebook_users', [
            'import_log_id' => $log1->id,
            'profile_id' => $singleProfile->id,
        ]);

        // Ambiguous matching case: 2 profiles with exact same name
        Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Juan Perez Duplicado',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);
        Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Juan Perez Duplicado',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        $ambiguousDir = "{$this->qaTempDir}/match_test_ambiguous";
        File::makeDirectory($ambiguousDir, 0755, true);
        File::put("{$ambiguousDir}/profile_information.json", json_encode([
            'profile_v2' => [
                'name' => ['full_name' => 'Juan Perez Duplicado'],
                'profile_uri' => 'https://facebook.com/juan.perez',
            ],
        ]));

        $log2 = FacebookImportLog::create([
            'drive_file_id' => 'folder_match_2',
            'file_name' => 'meta-ambiguous',
            'status' => 'downloaded',
        ]);

        $job2 = new ProcessFacebookExportJob($ambiguousDir, $log2->id);
        $job2->handle();

        // When ambiguous, profile_id should safely remain null to prevent misattribution
        $this->assertDatabaseHas('facebook_users', [
            'import_log_id' => $log2->id,
            'profile_id' => null,
        ]);
    }

    /**
     * Test Case 3: Facebook Mojibake Encoding Correction.
     * Verifies that escaped Latin-1 bytes exported by Facebook (e.g. Ã³ for ó) are cleaned.
     */
    public function test_facebook_mojibake_encoding_repair(): void
    {
        $folderDir = "{$this->qaTempDir}/mojibake_test";
        File::makeDirectory($folderDir, 0755, true);

        // "Atención rápida y confidencial" encoded as Facebook mojibake
        $mojibakeComment = 'Atenci'.chr(0xC3).chr(0x83).chr(0xC2).chr(0xB3).'n r'.chr(0xC3).chr(0x83).chr(0xC2).chr(0xA1).'pida';

        File::put("{$folderDir}/comments.json", json_encode([
            'comments_v2' => [
                [
                    'timestamp' => 1760000000,
                    'data' => [
                        [
                            'comment' => [
                                'comment' => $mojibakeComment,
                                'timestamp' => 1760000000,
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_mojibake',
            'file_name' => 'meta-mojibake',
            'status' => 'downloaded',
        ]);

        $job = new ProcessFacebookExportJob($folderDir, $log->id);
        $job->handle();

        $savedComment = FacebookComment::where('import_log_id', $log->id)->first();
        $this->assertNotNull($savedComment);
        $this->assertStringContainsString('Atención rápida', $savedComment->content);
    }

    /**
     * Test Case 4: Bulk Chunking Performance & Stress (>500 items).
     * Validates that batch inserts of 500 items execute seamlessly without truncation.
     */
    public function test_batch_chunking_with_large_dataset(): void
    {
        $folderDir = "{$this->qaTempDir}/bulk_test";
        File::makeDirectory($folderDir, 0755, true);

        $comments = [];
        $reactions = [];

        for ($i = 1; $i <= 550; $i++) {
            $comments[] = [
                'timestamp' => 1760000000 + $i,
                'data' => [
                    [
                        'comment' => [
                            'comment' => "Mensaje de auditoria volumetrica #{$i}",
                            'timestamp' => 1760000000 + $i,
                        ],
                    ],
                ],
            ];

            $reactions[] = [
                'timestamp' => 1760000000 + $i,
                'data' => [
                    [
                        'reaction' => [
                            'reaction' => 'LIKE',
                        ],
                    ],
                ],
            ];
        }

        File::put("{$folderDir}/comments.json", json_encode(['comments_v2' => $comments]));
        File::put("{$folderDir}/likes_and_reactions.json", json_encode(['reactions_v2' => $reactions]));

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_bulk_test',
            'file_name' => 'meta-bulk-550',
            'status' => 'downloaded',
        ]);

        $job = new ProcessFacebookExportJob($folderDir, $log->id);
        $job->handle();

        $log->refresh();
        $this->assertSame('pending_assignment', $log->status);
        $this->assertSame(550, $log->total_comments_extracted);
        $this->assertSame(550, $log->total_reactions_extracted);
        $this->assertCount(550, FacebookComment::where('import_log_id', $log->id)->get());
        $this->assertCount(550, FacebookReaction::where('import_log_id', $log->id)->get());
    }

    /**
     * Test Case 5: Zero-Retention Resilience on Drive API failure.
     * When cloud deletion encounters an API error, retry job is queued with backoff.
     */
    public function test_zero_retention_cloud_failure_recovery(): void
    {
        Queue::fake();

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_fail_retention',
            'file_name' => 'meta-fail-retention',
            'status' => 'downloaded',
        ]);

        $mockClient = Mockery::mock(GoogleDriveClient::class);
        $mockClient->shouldReceive('downloadExportFolder')
            ->once()
            ->andReturn(['comments.json' => 100]);

        $mockClient->shouldReceive('deleteFile')
            ->once()
            ->with('folder_fail_retention')
            ->andThrow(new Exception('Google Drive 503 Service Unavailable'));

        $service = new FacebookDriveSyncService($mockClient, $this->qaTempDir);
        $service->downloadFolder($log);

        $log->refresh();
        $this->assertNull($log->drive_deleted_at);
        $this->assertStringContainsString('Zero-retention deletion pending retry', $log->error_message);

        Queue::assertPushed(DeleteDriveFileJob::class, function ($job) use ($log) {
            return $job->driveFileId === 'folder_fail_retention' && $job->importLogId === $log->id;
        });
    }

    /**
     * Test Case 6: Mass Pruning Policy.
     * Logs older than 6 months with completed status are purged; recent ones remain.
     */
    public function test_mass_pruning_policy_enforcement(): void
    {
        // Old log (> 6 months)
        $oldLog = FacebookImportLog::create([
            'drive_file_id' => 'folder_old_prune',
            'file_name' => 'meta-old',
            'status' => 'completed',
        ]);
        $oldLog->created_at = now()->subMonths(7);
        $oldLog->saveQuietly();

        // Recent log (< 6 months)
        $recentLog = FacebookImportLog::create([
            'drive_file_id' => 'folder_recent_keep',
            'file_name' => 'meta-recent',
            'status' => 'completed',
        ]);
        $recentLog->created_at = now()->subDays(10);
        $recentLog->saveQuietly();

        $this->artisan('model:prune', ['--model' => [FacebookImportLog::class]])
            ->assertSuccessful();

        $this->assertDatabaseMissing('facebook_import_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('facebook_import_logs', ['id' => $recentLog->id]);
    }
}
