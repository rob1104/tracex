<?php

namespace Tests\Feature;

use App\Livewire\User\EvidenceList;
use App\Models\EmailAccount;
use App\Models\EvidenceType;
use App\Models\FacebookComment;
use App\Models\FacebookImportLog;
use App\Models\FacebookUser;
use App\Models\Profile;
use App\Models\User;
use App\Services\GoogleDrive\FacebookDriveSyncService;
use App\Services\GoogleDrive\GoogleDriveClient;
use App\Services\ProfileAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DriveHistorialIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempStorageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempStorageDir = storage_path('framework/testing/integration_drive_historial');
        if (File::exists($this->tempStorageDir)) {
            File::deleteDirectory($this->tempStorageDir);
        }
        File::makeDirectory($this->tempStorageDir, 0755, true);

        // Ensure catalog types exist
        EvidenceType::firstOrCreate(['name' => 'Comentario']);
        EvidenceType::firstOrCreate(['name' => 'Like / Reacción']);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempStorageDir)) {
            File::deleteDirectory($this->tempStorageDir);
        }
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test complete E2E integration:
     * Drive Scan -> Sync Download -> Remote Drive Deletion -> Parsing -> Auto-Assignment -> Collaborator Livewire Historial.
     */
    public function test_end_to_end_from_drive_sync_to_collaborator_historial_view(): void
    {
        // 1. Arrange Collaborator, EmailAccount, and Profile
        $collaborator = User::factory()->create([
            'role' => 'user',
            'name' => 'Colaborador E2E',
            'is_active' => true,
        ]);

        $email = EmailAccount::create([
            'email' => 'e2e_drive@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Alejandro Morales',
            'social_network' => 'facebook',
            'facebook_identifier' => 'id:61590000000001',
            'status' => 'active',
        ]);
        $profile->users()->attach($collaborator->id);

        $folderId = 'folder_e2e_meta_01';
        $downloadDir = "{$this->tempStorageDir}/{$folderId}";

        // Mock Drive Client
        $mockClient = Mockery::mock(GoogleDriveClient::class);

        $mockClient->shouldReceive('listExportFolders')
            ->once()
            ->andReturn([
                [
                    'id' => $folderId,
                    'name' => 'meta-2026-Oct-10-e2e',
                ],
            ]);

        $mockClient->shouldReceive('downloadExportFolder')
            ->once()
            ->with($folderId, $downloadDir)
            ->andReturnUsing(function ($fId, $targetDir) {
                File::makeDirectory($targetDir, 0755, true, true);

                $profileJson = [
                    'profile_v2' => [
                        'name' => ['full_name' => 'Alejandro Morales'],
                        'profile_uri' => 'https://facebook.com/profile.php?id=61590000000001',
                        'registration_timestamp' => 1610000000,
                    ],
                ];

                $commentsJson = [
                    'comments_v2' => [
                        [
                            'title' => 'Alejandro comentó en una noticia',
                            'timestamp' => 1760001000,
                            'data' => [
                                [
                                    'comment' => [
                                        'comment' => 'Reporte verificado con evidencias digitales.',
                                        'timestamp' => 1760001000,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                $reactionsJson = [
                    'reactions_v2' => [
                        [
                            'title' => 'Post de divulgación',
                            'timestamp' => 1760002000,
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
                    'profile_information.json' => 400,
                    'comments.json' => 700,
                    'likes_and_reactions.json' => 750,
                ];
            });

        // Verify zero-retention deletion from Google Drive is called
        $mockClient->shouldReceive('deleteFile')
            ->once()
            ->with($folderId)
            ->andReturn(true);

        $syncService = new FacebookDriveSyncService($mockClient, $this->tempStorageDir);
        $this->app->instance(FacebookDriveSyncService::class, $syncService);

        // 2. Act: Trigger Artisan sync
        $this->artisan('facebook:sync-drive --sync')
            ->assertSuccessful()
            ->run();

        // 3. Assert Database Layer
        $log = FacebookImportLog::where('drive_file_id', $folderId)->first();
        $this->assertNotNull($log);
        $this->assertSame('completed', $log->status);
        $this->assertSame($profile->id, $log->profile_id);
        $this->assertSame($collaborator->id, $log->user_id);
        $this->assertNotNull($log->drive_deleted_at);

        $this->assertDatabaseCount('evidence', 2);
        $this->assertDatabaseHas('evidence', [
            'user_id' => $collaborator->id,
            'profile_id' => $profile->id,
            'origin' => 'google_drive',
            'facebook_import_log_id' => $log->id,
            'comment' => 'Alejandro comentó en una noticia: Reporte verificado con evidencias digitales.',
        ]);
        $this->assertDatabaseHas('evidence', [
            'user_id' => $collaborator->id,
            'profile_id' => $profile->id,
            'origin' => 'google_drive',
            'facebook_import_log_id' => $log->id,
            'comment' => 'Reacción [LOVE] en: Post de divulgación',
        ]);

        // 4. Assert UI Layer: Collaborator views their "Historial"
        Livewire::actingAs($collaborator)
            ->test(EvidenceList::class)
            ->assertSee('Reporte verificado con evidencias digitales.')
            ->assertSee('Reacción [LOVE]')
            ->assertSee('Drive')
            ->assertSee('Alejandro Morales')
            ->set('filterOrigin', 'google_drive')
            ->assertSee('Reporte verificado con evidencias digitales.')
            ->assertSee('Reacción [LOVE]')
            ->set('filterOrigin', 'manual')
            ->assertDontSee('Reporte verificado con evidencias digitales.')
            ->assertDontSee('Reacción [LOVE]')
            ->assertSee('No hay evidencias que coincidan con los filtros aplicados.');
    }

    /**
     * Test manual assignment of unassigned/orphaned Drive export into collaborator's Historial.
     */
    public function test_manual_claiming_of_unassigned_drive_import_into_collaborator_historial(): void
    {
        $collaborator = User::factory()->create(['role' => 'user', 'name' => 'Colaborador Reclamo']);
        $email = EmailAccount::create([
            'email' => 'manual_claim@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Reclamable',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        // 1. Initial State: Unassigned import in database
        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_unassigned_999',
            'file_name' => 'meta-sin-perfil',
            'status' => 'pending_assignment',
            'profile_id' => null,
            'user_id' => null,
        ]);

        $fbUser = FacebookUser::create([
            'import_log_id' => $log->id,
            'metadata' => [],
        ]);

        FacebookComment::create([
            'facebook_user_id' => $fbUser->id,
            'import_log_id' => $log->id,
            'content' => 'Comentario que requería asignación manual',
            'character_count' => 45,
            'word_count' => 6,
            'published_at' => now(),
            'metadata' => ['context' => 'Publicación externa'],
        ]);

        // Collaborator initially sees 0 evidences
        Livewire::actingAs($collaborator)
            ->test(EvidenceList::class)
            ->assertDontSee('Comentario que requería asignación manual');

        // 2. Act: Admin / Gestor claims the import for this profile & collaborator
        $assignmentService = app(ProfileAssignmentService::class);
        $claimedCount = $assignmentService->claimPendingImport($log, $profile, $collaborator);

        $this->assertSame(1, $claimedCount);

        // 3. Assert: Collaborator now sees the newly generated evidence in their Historial
        Livewire::actingAs($collaborator)
            ->test(EvidenceList::class)
            ->assertSee('Publicación externa: Comentario que requería asignación manual')
            ->assertSee('Drive')
            ->assertSee('Perfil Reclamable');
    }
}
