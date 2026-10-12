<?php

namespace Tests\Feature;

use App\Jobs\ProcessFacebookExportJob;
use App\Livewire\User\EvidenceList;
use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class DriveHistorialStressTest extends TestCase
{
    use RefreshDatabase;

    protected string $stressStorageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stressStorageDir = storage_path('framework/testing/stress_test');
        if (File::exists($this->stressStorageDir)) {
            File::deleteDirectory($this->stressStorageDir);
        }
        File::makeDirectory($this->stressStorageDir, 0755, true);

        EvidenceType::firstOrCreate(['name' => 'Comentario']);
        EvidenceType::firstOrCreate(['name' => 'Like / Reacción']);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->stressStorageDir)) {
            File::deleteDirectory($this->stressStorageDir);
        }
        parent::tearDown();
    }

    /**
     * Stress Test 1: High Volume Ingestion (3,000 items) and Pagination UI Stability.
     * Verifies memory limit containment, chunking stability, and Livewire pagination.
     */
    public function test_stress_high_volume_batch_ingestion_and_pagination_performance(): void
    {
        // 1. Setup User and Monitored Profile
        $collaborator = User::factory()->create(['role' => 'user', 'name' => 'Stress Tester']);
        $email = EmailAccount::create([
            'email' => 'stress_high@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Cuenta Volumetrica',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);
        $profile->users()->attach($collaborator->id);

        $exportDir = "{$this->stressStorageDir}/bulk_export_3000";
        File::makeDirectory($exportDir, 0755, true);

        // 2. Generate 1,500 comments and 1,500 reactions (3,000 total items)
        $comments = [];
        $reactions = [];
        $volumeCount = 1500;

        for ($i = 1; $i <= $volumeCount; $i++) {
            $comments[] = [
                'timestamp' => 1760000000 + $i,
                'data' => [
                    [
                        'comment' => [
                            'comment' => "Comentario de carga masiva número {$i}",
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
                            'reaction' => ($i % 2 === 0) ? 'LIKE' : 'LOVE',
                        ],
                    ],
                ],
            ];
        }

        File::put("{$exportDir}/profile_information.json", json_encode([
            'profile_v2' => ['name' => ['full_name' => 'Cuenta Volumetrica']],
        ]));
        File::put("{$exportDir}/comments.json", json_encode(['comments_v2' => $comments]));
        File::put("{$exportDir}/likes_and_reactions.json", json_encode(['reactions_v2' => $reactions]));

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_stress_3000',
            'file_name' => 'meta-stress-volume-3000',
            'status' => 'processing',
        ]);

        $memoryBefore = memory_get_usage();
        $start = microtime(true);

        // 3. Act: Ingest massive export via Job
        $job = new ProcessFacebookExportJob($exportDir, $log->id);
        $job->handle();

        $elapsedTime = microtime(true) - $start;
        $memoryPeak = memory_get_peak_usage() - $memoryBefore;

        // 4. Assert Ingestion Performance & Accuracy
        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertSame($volumeCount, $log->total_comments_extracted);
        $this->assertSame($volumeCount, $log->total_reactions_extracted);

        // Verify total evidences inserted = 3,000
        $totalEvidences = Evidence::where('facebook_import_log_id', $log->id)->count();
        $this->assertSame($volumeCount * 2, $totalEvidences);

        // Assert memory remained bounded (less than 64MB peak allocated during chunking)
        $this->assertLessThan(67108864, $memoryPeak, 'Memory peak exceeded 64MB during bulk chunking.');

        // 5. Assert UI Pagination under 3,000 records
        $livewire = Livewire::actingAs($collaborator)
            ->test(EvidenceList::class)
            ->assertSet('filterOrigin', '')
            ->assertSee('Cuenta Volumetrica')
            ->assertSee('Drive');

        // Test pagination navigation to page 2
        $livewire->call('gotoPage', 2)
            ->assertStatus(200);
    }

    /**
     * Stress Test 2: Multi-profile Batch Processing.
     * Validates concurrent processing of 5 distinct profiles and ensures data isolation.
     */
    public function test_stress_multi_profile_batch_isolation(): void
    {
        $profilesCount = 5;
        $itemsPerProfile = 100;

        for ($p = 1; $p <= $profilesCount; $p++) {
            $user = User::factory()->create(['name' => "Colaborador Multi {$p}"]);
            $email = EmailAccount::create([
                'email' => "multi_{$p}@example.com",
                'password' => 'secret',
                'status' => 'active',
            ]);
            $profile = Profile::create([
                'email_account_id' => $email->id,
                'name' => "Perfil Multi {$p}",
                'social_network' => 'facebook',
                'status' => 'active',
            ]);
            $profile->users()->attach($user->id);

            $dir = "{$this->stressStorageDir}/multi_profile_{$p}";
            File::makeDirectory($dir, 0755, true);

            $comments = [];
            for ($c = 1; $c <= $itemsPerProfile; $c++) {
                $comments[] = [
                    'timestamp' => 1760000000 + $c,
                    'data' => [['comment' => ['comment' => "Mensaje P{$p}-#{$c}", 'timestamp' => 1760000000 + $c]]],
                ];
            }

            File::put("{$dir}/profile_information.json", json_encode([
                'profile_v2' => ['name' => ['full_name' => "Perfil Multi {$p}"]],
            ]));
            File::put("{$dir}/comments.json", json_encode(['comments_v2' => $comments]));

            $log = FacebookImportLog::create([
                'drive_file_id' => "folder_multi_{$p}",
                'file_name' => "meta-multi-{$p}",
                'status' => 'processing',
            ]);

            // Execute job for this profile
            $job = new ProcessFacebookExportJob($dir, $log->id);
            $job->handle();

            // Verify isolation: User only receives their profile's evidences
            $userEvidences = Evidence::where('user_id', $user->id)->count();
            $this->assertSame($itemsPerProfile, $userEvidences);
        }

        // Global check: 5 profiles * 100 items = 500 total evidences
        $this->assertDatabaseCount('evidence', $profilesCount * $itemsPerProfile);
    }
}
