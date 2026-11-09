<?php

namespace Tests\Feature;

use App\Livewire\Admin\FacebookMonitoring\Index;
use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\Profile;
use App\Models\User;
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
            ->assertSee('Métrica de cobertura de actividad en perfiles asignados')
            ->assertSee('Perfiles con actividad (1)')
            ->assertSee('Perfiles sin actividad (1)')
            ->assertSee('Perfil Con Actividad')
            ->assertSee('Perfil Sin Actividad')
            ->call('setProfileActivityFilter', 'without_activity')
            ->assertSet('profileActivityFilter', 'without_activity');
    }
}
