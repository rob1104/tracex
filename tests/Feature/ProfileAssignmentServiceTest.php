<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProfileAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileAssignmentService;
    }

    public function test_it_resolves_profile_and_assigned_collaborator(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $email = EmailAccount::create([
            'email' => 'service_test@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Roberto Sánchez',
            'social_network' => 'facebook',
            'status' => 'active',
            'created_by' => $creator->id,
        ]);

        $profile->users()->attach($collaborator->id);

        $result = $this->service->resolve(['nombre' => 'roberto sánchez']);

        $this->assertNotNull($result['profile']);
        $this->assertSame($profile->id, $result['profile']->id);
        $this->assertNotNull($result['user']);
        $this->assertSame($collaborator->id, $result['user']->id);
    }

    public function test_it_falls_back_to_creator_when_no_collaborator_is_assigned(): void
    {
        $creator = User::factory()->create(['role' => 'cuentas']);

        $email = EmailAccount::create([
            'email' => 'creator_fallback@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Andrea Morales',
            'social_network' => 'facebook',
            'status' => 'active',
            'created_by' => $creator->id,
        ]);

        $result = $this->service->resolve(['nombre' => 'Andrea Morales']);

        $this->assertNotNull($result['profile']);
        $this->assertSame($profile->id, $result['profile']->id);
        $this->assertNotNull($result['user']);
        $this->assertSame($creator->id, $result['user']->id);
    }

    public function test_it_returns_null_when_profile_is_ambiguous(): void
    {
        $email = EmailAccount::create([
            'email' => 'ambiguous@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);

        Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Duplicado Pérez',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Duplicado Pérez',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        $result = $this->service->resolve(['nombre' => 'Duplicado Pérez']);

        $this->assertNull($result['profile']);
        $this->assertNull($result['user']);
    }

    public function test_it_binds_import_log_explicitly(): void
    {
        $user = User::factory()->create();
        $email = EmailAccount::create([
            'email' => 'bind@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Bind',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_bind_123',
            'file_name' => 'meta-bind',
            'status' => 'downloaded',
        ]);

        $this->service->assignImportLog($log, $profile, $user);

        $log->refresh();
        $this->assertSame($profile->id, $log->profile_id);
        $this->assertSame($user->id, $log->user_id);
    }
}
