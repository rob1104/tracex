<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\FacebookComment;
use App\Models\FacebookImportLog;
use App\Models\FacebookReaction;
use App\Models\FacebookUser;
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

    public function test_it_claims_pending_import_and_creates_evidences(): void
    {
        $user = User::factory()->create();
        $email = EmailAccount::create([
            'email' => 'claim@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $profile = Profile::create([
            'email_account_id' => $email->id,
            'name' => 'Perfil Claim',
            'social_network' => 'facebook',
            'status' => 'active',
        ]);

        $log = FacebookImportLog::create([
            'drive_file_id' => 'folder_claim_123',
            'file_name' => 'meta-claim',
            'status' => 'pending_assignment',
        ]);

        $fbUser = FacebookUser::create([
            'import_log_id' => $log->id,
            'metadata' => [],
        ]);

        FacebookComment::create([
            'facebook_user_id' => $fbUser->id,
            'import_log_id' => $log->id,
            'content' => 'Comentario reclamado',
            'character_count' => 20,
            'word_count' => 2,
            'published_at' => now(),
            'metadata' => ['context' => 'Contexto manual'],
        ]);

        FacebookReaction::create([
            'facebook_user_id' => $fbUser->id,
            'import_log_id' => $log->id,
            'reaction_type' => 'LIKE',
            'title' => 'Foto de perfil',
            'published_at' => now(),
        ]);

        $generatedCount = $this->service->claimPendingImport($log, $profile, $user);

        $this->assertSame(2, $generatedCount);
        $this->assertCount(2, $user->evidences);
        $this->assertSame('google_drive', $user->evidences->first()->origin);
        $this->assertSame($profile->id, $user->evidences->first()->profile_id);
    }

    public function test_it_resolves_profile_and_user_via_email_account_with_assigned_user(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $collaborator = User::factory()->create([
            'name' => 'Juana Rodriguez',
            'email' => 'juana.rodriguez@empresa.com',
            'role' => 'user',
            'is_active' => true,
        ]);

        $emailAccount = EmailAccount::create([
            'email' => 'juana.rogj69@gmail.com',
            'alias' => 'Correo Juana Rodriguez',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $creator->id,
            'assigned_user_id' => $collaborator->id,
        ]);

        $profile = Profile::create([
            'email_account_id' => $emailAccount->id,
            'name' => 'Juana Rodriguez FB',
            'social_network' => 'facebook',
            'status' => 'active',
            'created_by' => $creator->id,
        ]);

        $result = $this->service->resolve([
            'nombre' => 'Juana Rodriguez',
            'emails' => ['Juana.Rogj69@gmail.com'],
            'uri' => 'https://facebook.com/profile.php?id=61593018723655',
        ]);

        $this->assertNotNull($result['profile']);
        $this->assertSame($profile->id, $result['profile']->id);
        $this->assertNotNull($result['user']);
        $this->assertSame($collaborator->id, $result['user']->id);
        $this->assertTrue($result['profile']->users->contains($collaborator));
    }

    public function test_it_does_not_auto_create_profile_when_facebook_profile_does_not_exist(): void
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $collaborator = User::factory()->create([
            'name' => 'Sergio Perez',
            'email' => 'sergio.perez@empresa.com',
            'role' => 'user',
            'is_active' => true,
        ]);

        $emailAccount = EmailAccount::create([
            'email' => 'sergio.perez@outlook.com',
            'alias' => 'Correo Sergio Perez',
            'password' => 'secret123',
            'status' => 'active',
            'created_by' => $creator->id,
            'assigned_user_id' => $collaborator->id,
        ]);

        $result = $this->service->resolve([
            'nombre' => 'Sergio Perez Meta',
            'emails' => ['sergio.perez@outlook.com'],
            'identifier' => 'id:61592991939900',
        ]);

        $this->assertNull($result['profile']);
        $this->assertNull($result['user']);
        $this->assertDatabaseMissing('profiles', [
            'email_account_id' => $emailAccount->id,
            'social_network' => 'Facebook',
        ]);
    }
}
