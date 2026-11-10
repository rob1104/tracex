<?php

namespace Tests\Feature;

use App\Livewire\Admin\FacebookMonitoring\Index;
use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\EvidenceTextImport;
use App\Models\EvidenceType;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\FacebookTextEvidenceParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FacebookMonitoringProfileMetricsAndIdAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_collaborator_can_assign_facebook_id_to_assigned_profile_without_identifier(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $email = EmailAccount::create([
            'email' => 'colab@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Sin ID',
            'social_network' => 'Facebook',
            'facebook_identifier' => null,
            'status' => 'active',
        ]);
        $profile->users()->attach($user->id);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set("profileFacebookIdentifiers.{$profile->id}", 'https://www.facebook.com/profile.php?id=1000555666777')
            ->call('assignFacebookIdentifier', $profile->id)
            ->assertHasNoErrors();

        $this->assertSame('id:1000555666777', $profile->fresh()->facebook_identifier);
    }

    public function test_collaborator_cannot_assign_duplicate_or_invalid_facebook_identifier(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $email = EmailAccount::create([
            'email' => 'dup@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Existente',
            'social_network' => 'Facebook',
            'facebook_identifier' => 'id:1000999888777',
            'status' => 'active',
        ]);

        $targetProfile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Objetivo',
            'social_network' => 'Facebook',
            'facebook_identifier' => null,
            'status' => 'active',
        ]);
        $targetProfile->users()->attach($user->id);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set("profileFacebookIdentifiers.{$targetProfile->id}", '1000999888777')
            ->call('assignFacebookIdentifier', $targetProfile->id)
            ->assertHasErrors(["profileFacebookIdentifiers.{$targetProfile->id}"]);

        $this->assertNull($targetProfile->fresh()->facebook_identifier);
    }

    public function test_collaborator_cannot_assign_identifier_to_unassigned_profile(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $email = EmailAccount::create([
            'email' => 'other@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $otherProfile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Ajeno',
            'social_network' => 'Facebook',
            'facebook_identifier' => null,
            'status' => 'active',
        ]);
        $otherProfile->users()->attach($otherUser->id);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set("profileFacebookIdentifiers.{$otherProfile->id}", '1000111222333')
            ->call('assignFacebookIdentifier', $otherProfile->id)
            ->assertForbidden();

        $this->assertNull($otherProfile->fresh()->facebook_identifier);
    }

    public function test_monitoring_panel_displays_assigned_profiles_activity_metrics_and_lists(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $email = EmailAccount::create([
            'email' => 'metrics@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $activeProfile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Con Actividad',
            'social_network' => 'Facebook',
            'facebook_identifier' => 'id:1000111000111',
            'status' => 'active',
        ]);
        $activeProfile->users()->attach($user->id);

        $inactiveProfile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Sin Actividad',
            'social_network' => 'Facebook',
            'facebook_identifier' => null,
            'status' => 'active',
        ]);
        $inactiveProfile->users()->attach($user->id);

        $commentType = EvidenceType::firstOrCreate(['name' => 'Comentario']);

        Evidence::create([
            'user_id' => $user->id,
            'profile_id' => $activeProfile->id,
            'evidence_type_id' => $commentType->id,
            'origin' => 'facebook_txt',
            'social_network' => 'Facebook',
            'comment' => 'Comentario de prueba',
            'external_published_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertViewHas('totalProfiles', 2)
            ->assertViewHas('profilesWithActivityCount', 1)
            ->assertViewHas('profilesWithoutActivityCount', 1)
            ->assertViewHas('profilesMissingIdCount', 1)
            ->assertViewHas('activityCoveragePercent', 50)
            ->assertSee('Gestión de Perfiles de Facebook')
            ->assertSee('1 de 2 perfiles han recibido actividad')
            ->assertSee('Todos (2)')
            ->assertSee('Con actividad (1)')
            ->assertSee('Sin actividad (1)')
            ->assertSee('Sin ID (1)')
            ->assertSee('Perfil Con Actividad')
            ->assertSee('Perfil Sin Actividad')
            ->call('setProfileActivityFilter', 'without_activity')
            ->assertSet('profileActivityFilter', 'without_activity');
    }

    public function test_imports_tab_renders_identical_result_badges_for_text_and_drive_imports(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $email = EmailAccount::create([
            'email' => 'imports@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Importaciones',
            'social_network' => 'Facebook',
            'facebook_identifier' => 'id:1000222333444',
            'status' => 'active',
        ]);
        $profile->users()->attach($user->id);

        $commentType = EvidenceType::firstOrCreate(['name' => 'Comentario']);
        $reactionType = EvidenceType::firstOrCreate(['name' => 'Reacción']);
        $shareType = EvidenceType::firstOrCreate(['name' => 'Compartido']);

        Evidence::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'evidence_type_id' => $commentType->id,
            'origin' => 'facebook_txt',
            'social_network' => 'Facebook',
            'comment' => 'Comentario TXT',
            'external_published_at' => now(),
        ]);
        Evidence::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'evidence_type_id' => $reactionType->id,
            'origin' => 'facebook_txt',
            'social_network' => 'Facebook',
            'comment' => 'Reacción TXT',
            'external_published_at' => now(),
        ]);
        Evidence::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'evidence_type_id' => $shareType->id,
            'origin' => 'facebook_txt',
            'social_network' => 'Facebook',
            'comment' => 'Compartido TXT',
            'external_published_at' => now(),
        ]);

        EvidenceTextImport::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'original_file_name' => 'Texto pegado',
            'status' => 'completed',
            'total_entries' => 3,
            'recognized_count' => 3,
            'imported_count' => 3,
        ]);

        FacebookImportLog::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'drive_file_id' => 'drive-test-1',
            'file_name' => 'export-drive.zip',
            'status' => 'completed',
            'downloaded_at' => now(),
            'total_comments_extracted' => 4,
            'total_reactions_extracted' => 5,
            'total_shares_extracted' => 6,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('setActivityTab', 'imports')
            ->assertSee('Comentarios: 1')
            ->assertSee('Reacciones: 1')
            ->assertSee('Compartidos: 1')
            ->assertSee('Comentarios: 4')
            ->assertSee('Reacciones: 5')
            ->assertSee('Compartidos: 6');
    }

    public function test_text_evidence_parser_keeps_only_first_line_up_to_period_for_reactions(): void
    {
        $rawText = <<<'TXT'
9 de noviembre de 2026
A Valentin Veltran le gusta la foto de RTNoticias.
Jorge “Tico” García fortalece estructura del PAN en Victoria y llama a trabajar en unidad por las familias Ciudad Victoria, Tamaulipas.– El coordinador municipal para la Defensa de la Familia y la Libertad de Partido Acción Nacional (PAN) en Ciudad Victoria, Jorge “Tico” García, reafirmó su compromiso.
Público
10:30 pm
TXT;

        $tempFile = tempnam(sys_get_temp_dir(), 'fb_txt_test_');
        file_put_contents($tempFile, $rawText);

        try {
            $records = iterator_to_array((new FacebookTextEvidenceParser)->parseFile($tempFile));
        } finally {
            @unlink($tempFile);
        }

        $this->assertCount(1, $records);
        $this->assertSame('evidence', $records[0]['kind']);
        $this->assertSame('reaction', $records[0]['type_key']);
        $this->assertSame('A Valentin Veltran le gusta la foto de RTNoticias.', $records[0]['text']);
        $this->assertSame('A Valentin Veltran le gusta la foto de RTNoticias.', $records[0]['dedupe_text']);
    }

    public function test_supervisor_only_sees_assigned_collaborators_and_admin_sees_all(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Equipo A', 'is_active' => true]);
        $globalAdmin = User::factory()->create(['role' => 'admin', 'name' => 'Super Admin', 'is_active' => true]);

        $collabA = User::factory()->create([
            'role' => 'user',
            'name' => 'Colaborador Equipo A',
            'is_active' => true,
            'supervisor_id' => $supervisor->id,
        ]);
        $collabB = User::factory()->create([
            'role' => 'user',
            'name' => 'Colaborador Equipo B',
            'is_active' => true,
        ]);

        $email = EmailAccount::create([
            'email' => 'hierarchy@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profileA = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Equipo A',
            'social_network' => 'Facebook',
            'facebook_identifier' => 'id:1000000000001',
            'status' => 'active',
        ]);
        $profileA->users()->attach($collabA->id);

        $profileB = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Equipo B',
            'social_network' => 'Facebook',
            'facebook_identifier' => 'id:1000000000002',
            'status' => 'active',
        ]);
        $profileB->users()->attach($collabB->id);

        Livewire::actingAs($supervisor)
            ->test(Index::class)
            ->assertViewHas('totalProfiles', 1)
            ->assertSee('Perfil Equipo A')
            ->assertDontSee('Perfil Equipo B');

        Livewire::actingAs($globalAdmin)
            ->test(Index::class)
            ->assertViewHas('totalProfiles', 2)
            ->assertSee('Perfil Equipo A')
            ->assertSee('Perfil Equipo B');

        $this->actingAs($supervisor)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('admin.evidence-reports.index'))
            ->assertOk();

        $this->actingAs($supervisor)
            ->get(route('admin.evidences.index'))
            ->assertOk();

        $this->actingAs($supervisor)
            ->get(route('admin.emails.index'))
            ->assertOk();

        $this->actingAs($supervisor)
            ->get(route('admin.profiles.index'))
            ->assertOk();
    }
}
