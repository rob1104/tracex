<?php

namespace Tests\Feature;

use App\Livewire\Admin\Emails\Index;
use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminEmailCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_open_create_modal()
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSee('Nueva Cuenta');
    }

    public function test_can_open_edit_modal()
    {
        $admin = User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin']);
        $account = EmailAccount::first() ?? EmailAccount::create([
            'email' => 'test@example.com',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('edit', $account->id)
            ->assertSet('showModal', true)
            ->assertSee('Editar Cuenta');
    }

    public function test_component_rendering_with_modal_open()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $test = Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create');

        $html = $test->html();
        $this->assertStringContainsString('Nueva Cuenta', $html);
    }

    public function test_evidence_types_modal_can_be_opened()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\EvidenceTypes\Index::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->assertSee('Nuevo Tipo');
    }
}
