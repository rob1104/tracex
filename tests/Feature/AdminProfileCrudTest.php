<?php

namespace Tests\Feature;

use App\Livewire\Admin\Profiles\Index;
use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProfileCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_gestor_with_no_owned_accounts_can_open_create_modal_and_sees_active_accounts(): void
    {
        $gestor = User::factory()->create([
            'role' => 'cuentas',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $emailAccount = EmailAccount::create([
            'email' => 'active@example.com',
            'alias' => 'Cuenta Activa',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSeeHtml($emailAccount->email)
            ->assertSeeHtml('Cuenta Activa');
    }

    public function test_gestor_can_create_profile_and_is_automatically_assigned(): void
    {
        $gestor = User::factory()->create([
            'role' => 'cuentas',
            'is_active' => true,
        ]);

        $emailAccount = EmailAccount::create([
            'email' => 'gestor@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $gestor->id,
        ]);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Perfil Nuevo Gestor')
            ->set('email_account_id', $emailAccount->id)
            ->set('social_network', 'Facebook')
            ->set('facebook_identifier', 'id:123456789')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $profile = Profile::where('name', 'Perfil Nuevo Gestor')->first();
        $this->assertNotNull($profile);
        $this->assertSame($gestor->id, $profile->created_by);
        $this->assertTrue($profile->users()->where('users.id', $gestor->id)->exists());
    }

    public function test_gestor_can_edit_profile_assigned_to_them_even_if_created_by_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);

        $emailAccount = EmailAccount::create([
            'email' => 'shared@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $profile = Profile::create([
            'name' => 'Perfil Administrado',
            'email_account_id' => $emailAccount->id,
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $profile->users()->attach($gestor->id);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('edit', $profile->id)
            ->assertSet('showModal', true)
            ->assertSet('name', 'Perfil Administrado')
            ->assertSet('email_account_id', $emailAccount->id)
            ->set('name', 'Perfil Administrado Modificado')
            ->call('save')
            ->assertHasNoErrors();

        $profile->refresh();
        $this->assertSame('Perfil Administrado Modificado', $profile->name);
        $this->assertSame($admin->id, $profile->created_by);
    }

    public function test_users_list_contains_both_users_and_cuentas_roles(): void
    {
        $gestor = User::factory()->create([
            'name' => 'Alberto Gestor',
            'role' => 'cuentas',
            'is_active' => true,
        ]);

        $normalUser = User::factory()->create([
            'name' => 'Carlos Colaborador',
            'role' => 'user',
            'is_active' => true,
        ]);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->assertViewHas('usersList', function ($usersList) use ($gestor, $normalUser) {
                return $usersList->contains('id', $gestor->id)
                    && $usersList->contains('id', $normalUser->id);
            });
    }

    public function test_gestor_cannot_edit_unassigned_profile_created_by_someone_else(): void
    {
        $otherGestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);

        $emailAccount = EmailAccount::create([
            'email' => 'other@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $otherGestor->id,
        ]);

        $profile = Profile::create([
            'name' => 'Perfil Ajeno',
            'email_account_id' => $emailAccount->id,
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => $otherGestor->id,
        ]);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('edit', $profile->id)
            ->assertStatus(403);
    }

    public function test_gestor_can_open_assign_modal_and_sync_collaborators(): void
    {
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);
        $collaborator = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);

        $emailAccount = EmailAccount::create([
            'email' => 'gestor@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $gestor->id,
        ]);

        $profile = Profile::create([
            'name' => 'Perfil Propio',
            'email_account_id' => $emailAccount->id,
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => $gestor->id,
        ]);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('openAssignModal', $profile->id)
            ->assertSet('showAssignModal', true)
            ->set('assignedUsers', [$collaborator->id])
            ->call('saveAssignments')
            ->assertSet('showAssignModal', false);

        $profile->refresh();
        $this->assertTrue($profile->users()->where('users.id', $collaborator->id)->exists());
    }

    public function test_gestor_can_view_profiles_assigned_to_collaborator(): void
    {
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $emailAccount = EmailAccount::create([
            'email' => 'gestor@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $gestor->id,
        ]);

        $profile = Profile::create([
            'name' => 'Perfil del Colaborador',
            'email_account_id' => $emailAccount->id,
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => $gestor->id,
        ]);

        $profile->users()->attach($collaborator->id);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('openColabProfilesModal')
            ->assertSet('showColabProfilesModal', true)
            ->set('selectedColabViewId', (string) $collaborator->id)
            ->assertCount('viewingColabProfiles', 1);
    }

    public function test_gestor_can_view_and_edit_admin_created_profile_without_explicit_pivot_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $emailAccount = EmailAccount::create([
            'email' => 'adminaccount@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $profile = Profile::create([
            'name' => 'Perfil Creado Por Admin',
            'email_account_id' => $emailAccount->id,
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $profile->users()->attach($collaborator->id);

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->assertSee('Perfil Creado Por Admin')
            ->call('edit', $profile->id)
            ->assertSet('showModal', true)
            ->assertSet('name', 'Perfil Creado Por Admin')
            ->set('name', 'Perfil Actualizado Por Gestor')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $profile->refresh();
        $this->assertSame('Perfil Actualizado Por Gestor', $profile->name);
    }

    public function test_gestor_can_view_and_edit_admin_created_email_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);

        $emailAccount = EmailAccount::create([
            'email' => 'adminemail@example.com',
            'alias' => 'Admin Correo',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($gestor)
            ->test(\App\Livewire\Admin\Emails\Index::class)
            ->assertSee('adminemail@example.com')
            ->call('edit', $emailAccount->id)
            ->assertSet('showModal', true)
            ->assertSet('email', 'adminemail@example.com')
            ->set('alias', 'Admin Correo Editado Por Gestor')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $emailAccount->refresh();
        $this->assertSame('Admin Correo Editado Por Gestor', $emailAccount->alias);
    }

    public function test_collaborator_only_sees_assigned_profiles_and_cannot_export_profiles(): void
    {
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $otherCollaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $account = EmailAccount::create(['email' => 'profiles@example.com', 'password' => 'private-secret', 'status' => 'active']);
        $assignedProfile = Profile::create(['name' => 'Assigned profile', 'email_account_id' => $account->id, 'social_network' => 'Facebook', 'status' => 'active']);
        $otherProfile = Profile::create(['name' => 'Other profile', 'email_account_id' => $account->id, 'social_network' => 'Facebook', 'status' => 'active']);
        $assignedProfile->users()->attach($collaborator->id);
        $otherProfile->users()->attach($otherCollaborator->id);

        Livewire::actingAs($collaborator)
            ->test(Index::class)
            ->assertSee('Assigned profile')
            ->assertDontSee('Other profile')
            ->assertDontSee('exportCsv')
            ->call('exportCsv')
            ->assertStatus(403);

        Livewire::actingAs($collaborator)
            ->test(Index::class)
            ->call('exportPdf')
            ->assertStatus(403);
    }

    public function test_collaborator_cannot_export_email_accounts_and_admin_retains_catalog_access(): void
    {
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $gestor = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);

        Livewire::actingAs($collaborator)
            ->test(\App\Livewire\Admin\Emails\Index::class)
            ->assertDontSee('exportCsv')
            ->call('exportCsv')
            ->assertStatus(403);

        Livewire::actingAs($collaborator)
            ->test(\App\Livewire\Admin\Emails\Index::class)
            ->call('exportPdf')
            ->assertStatus(403);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\Emails\Index::class)
            ->call('exportCsv')
            ->assertFileDownloaded();

        Livewire::actingAs($gestor)
            ->test(\App\Livewire\Admin\Emails\Index::class)
            ->call('exportCsv')
            ->assertFileDownloaded();

        $this->actingAs($admin)
            ->get(route('admin.evidence_types.index'))
            ->assertOk()
            ->assertSee('Catálogo');

        $this->actingAs($collaborator)
            ->get(route('admin.evidence_types.index'))
            ->assertForbidden();
    }
}
