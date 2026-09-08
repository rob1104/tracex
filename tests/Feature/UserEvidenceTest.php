<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\EvidenceType;
use App\Models\Evidence;
use Livewire\Livewire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->type = EvidenceType::create(['name' => 'Type 1', 'is_active' => true]);
    }

    public function test_user_can_view_evidence_create_page()
    {
        $this->actingAs($this->user)
             ->get('/evidencias/registrar')
             ->assertStatus(200);
    }

    public function test_user_can_submit_evidence_with_multiple_images()
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->image('photo1.jpg')->size(100);
        $file2 = UploadedFile::fake()->image('photo2.jpg')->size(100);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\User\EvidenceCreate::class)
            ->set('evidence_type_id', $this->type->id)
            ->set('social_network', 'Facebook')
            ->set('comment', 'Test comment')
            ->set('images', [$file1, $file2])
            ->call('save')
            ->assertRedirect('/dashboard');

        $evidence = Evidence::where('user_id', $this->user->id)->first();
        $this->assertNotNull($evidence);
        $this->assertCount(2, $evidence->images);
    }

    public function test_user_can_view_their_own_evidences()
    {
        Evidence::create([
            'user_id' => $this->user->id,
            'evidence_type_id' => $this->type->id,
            'social_network' => 'Facebook',
        ]);

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\User\EvidenceList::class)
            ->assertSee('Facebook');
    }
}

