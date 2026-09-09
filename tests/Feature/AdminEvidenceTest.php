<?php

namespace Tests\Feature;

use App\Livewire\Admin\Evidences\Index;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->user = User::factory()->create(['role' => 'user', 'is_active' => true]);
    }

    public function test_non_admins_cannot_access_admin_routes()
    {
        $this->actingAs($this->user)
            ->get('/admin/evidencias')
            ->assertStatus(403);
    }

    public function test_admins_can_access_admin_routes()
    {
        $this->actingAs($this->admin)
            ->get('/admin/evidencias')
            ->assertStatus(200);
    }

    public function test_admins_can_delete_evidence()
    {
        $evidence = Evidence::create([
            'user_id' => $this->user->id,
            'evidence_type_id' => EvidenceType::create(['name' => 'T', 'is_active' => true])->id,
            'social_network' => 'Facebook',
        ]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('delete', $evidence->id);

        $this->assertDatabaseMissing('evidence', ['id' => $evidence->id]);
    }

    public function test_admins_can_toggle_user_active_status()
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Admin\Users\Index::class)
            ->call('toggleActive', $this->user->id);

        $this->user->refresh();
        $this->assertFalse((bool) $this->user->is_active);
    }
}
