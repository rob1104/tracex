<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_rendered()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate()
    {
        $user = User::factory()->create(['is_active' => true]);

        \Livewire\Livewire::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_inactive_users_cannot_authenticate()
    {
        $user = User::factory()->create(['is_active' => false]);

        \Livewire\Livewire::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors();

        $this->assertGuest();
    }

    public function test_public_registration_is_disabled()
    {
        $response = $this->get('/register');
        $response->assertStatus(404);
    }
}
