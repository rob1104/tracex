<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users\Index;
use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertOk()
            ->assertSee('Buscar Usuario');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Gestión de Usuarios');
    }

    public function test_admin_can_open_create_modal_and_save_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->set('name', 'Nuevo Gestor')
            ->set('email', 'nuevogestor@example.com')
            ->set('role', 'cuentas')
            ->set('password', 'secret123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('users', [
            'name' => 'Nuevo Gestor',
            'email' => 'nuevogestor@example.com',
            'role' => 'cuentas',
        ]);
    }

    public function test_admin_can_view_gestor_accounts_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $gestor = User::factory()->create(['role' => 'cuentas', 'name' => 'Gestor Test', 'is_active' => true]);

        $account = EmailAccount::create([
            'email' => 'gestor.test@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $gestor->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('viewAccounts', $gestor->id)
            ->assertSet('showAccountsModal', true)
            ->assertSet('viewingUserName', 'Gestor Test')
            ->assertSeeHtml($account->email);
    }

    public function test_admin_can_assign_multiple_collaborators_to_a_supervisor(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Equipo', 'is_active' => true]);
        $collab1 = User::factory()->create(['role' => 'user', 'name' => 'Colaborador Uno', 'is_active' => true]);
        $collab2 = User::factory()->create(['role' => 'user', 'name' => 'Colaborador Dos', 'is_active' => true]);
        $collab3 = User::factory()->create(['role' => 'user', 'name' => 'Colaborador Tres', 'is_active' => true, 'supervisor_id' => $supervisor->id]);

        Livewire::actingAs($superAdmin)
            ->test(Index::class)
            ->call('openCollaboratorsModal', $supervisor->id)
            ->assertSet('showCollaboratorsModal', true)
            ->assertSet('assigningAdminId', $supervisor->id)
            ->set('assignedCollaboratorIds', [$collab1->id, $collab2->id])
            ->call('saveAdminCollaborators')
            ->assertHasNoErrors()
            ->assertSet('showCollaboratorsModal', false);

        $this->assertSame($supervisor->id, $collab1->fresh()->supervisor_id);
        $this->assertSame($supervisor->id, $collab2->fresh()->supervisor_id);
        $this->assertNull($collab3->fresh()->supervisor_id);
    }
}
