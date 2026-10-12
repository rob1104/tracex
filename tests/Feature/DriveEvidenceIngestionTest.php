<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookExportJob;
use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DriveEvidenceIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected string $exportDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportDir = storage_path('framework/testing/ingestion_test');
        if (File::exists($this->exportDir)) {
            File::deleteDirectory($this->exportDir);
        }
        File::makeDirectory($this->exportDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->exportDir)) {
            File::deleteDirectory($this->exportDir);
        }
        parent::tearDown();
    }

    public function test_it_automatically_ingests_comments_and_reactions_into_user_evidences(): void
    {
        // 1. Arrange User, EmailAccount, Profile and EvidenceTypes
        $collaborator = User::factory()->create(['role' => 'user', 'name' => 'Colaborador Juan']);

        $email = EmailAccount::create([
            'email' => 'ingestion@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Valeria Gómez',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);
        $profile->users()->attach($collaborator->id);

        $typeComment = EvidenceType::create(['name' => 'Comentario']);
        $typeReaction = EvidenceType::create(['name' => 'Like / Reacción']);

        // 2. Prepare Export JSON files
        $profileData = [
            'profile_v2' => [
                'name' => ['full_name' => 'Valeria Gómez'],
                'profile_uri' => 'https://facebook.com/valeria.gomez',
            ],
        ];

        $commentsData = [
            'comments_v2' => [
                [
                    'title' => 'Valeria comentó en un post',
                    'timestamp' => 1760000000,
                    'data' => [
                        [
                            'comment' => [
                                'comment' => 'Totalmente de acuerdo con el informe de auditoría.',
                                'timestamp' => 1760000000,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $reactionsData = [
            'reactions_v2' => [
                [
                    'title' => 'Publicación de Roberto',
                    'timestamp' => 1760000100,
                    'data' => [
                        [
                            'reaction' => [
                                'reaction' => 'LIKE',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        File::put("{$this->exportDir}/profile_information.json", json_encode($profileData));
        File::put("{$this->exportDir}/comments.json", json_encode($commentsData));
        File::put("{$this->exportDir}/likes_and_reactions.json", json_encode($reactionsData));

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_ingest_001',
            'file_name' => 'meta-valeria-export',
            'status' => 'downloaded',
        ]);

        // 3. Act: Execute Job
        $job = new ProcessFacebookExportJob($this->exportDir, $log->id);
        $job->handle();

        // 4. Assert
        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertSame($profile->id, $log->profile_id);
        $this->assertSame($collaborator->id, $log->user_id);

        // Verify Evidence table has 2 entries (1 comment, 1 reaction)
        $this->assertDatabaseCount('evidence', 2);

        $this->assertDatabaseHas('evidence', [
            'user_id' => $collaborator->id,
            'profile_id' => $profile->id,
            'evidence_type_id' => $typeComment->id,
            'origin' => 'google_drive',
            'facebook_import_log_id' => $log->id,
            'social_network' => 'Facebook',
            'comment' => 'Valeria comentó en un post: Totalmente de acuerdo con el informe de auditoría.',
        ]);

        $this->assertDatabaseHas('evidence', [
            'user_id' => $collaborator->id,
            'profile_id' => $profile->id,
            'evidence_type_id' => $typeReaction->id,
            'origin' => 'google_drive',
            'facebook_import_log_id' => $log->id,
            'social_network' => 'Facebook',
            'comment' => 'Reacción [LIKE] en: Publicación de Roberto',
        ]);

        // Verify collaborator can retrieve them directly via relationship (Historial query)
        $this->assertCount(2, $collaborator->evidences);
        $this->assertSame('google_drive', $collaborator->evidences->first()->origin);
    }

    public function test_it_guarantees_idempotency_when_reprocessing(): void
    {
        $user = User::factory()->create();
        $email = EmailAccount::create([
            'email' => 'idempotent@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Diana Lopez',
            'social_network' => 'facebook',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        EvidenceType::create(['name' => 'Comentario']);

        $profileData = ['profile_v2' => ['name' => ['full_name' => 'Diana Lopez']]];
        $commentsData = [
            'comments_v2' => [
                [
                    'timestamp' => 1760000000,
                    'data' => [['comment' => ['comment' => 'Mensaje repetido', 'timestamp' => 1760000000]]],
                ],
            ],
        ];

        File::put("{$this->exportDir}/profile_information.json", json_encode($profileData));
        File::put("{$this->exportDir}/comments.json", json_encode($commentsData));

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_idempotent',
            'file_name' => 'meta-diana',
            'status' => 'processing',
        ]);

        // First run
        $job = new ProcessFacebookExportJob($this->exportDir, $log->id);
        $job->handle();

        $this->assertDatabaseCount('evidence', 1);

        // Re-create directory for second run simulation
        File::makeDirectory($this->exportDir, 0755, true);
        File::put("{$this->exportDir}/profile_information.json", json_encode($profileData));
        File::put("{$this->exportDir}/comments.json", json_encode($commentsData));

        $log->update(['status' => 'processing']);

        // Second run
        $job2 = new ProcessFacebookExportJob($this->exportDir, $log->id);
        $job2->handle();

        // Must still be 1 evidence row, not duplicated
        $this->assertDatabaseCount('evidence', 1);
    }
}
