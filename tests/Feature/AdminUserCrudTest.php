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
}
