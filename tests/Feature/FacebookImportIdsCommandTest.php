<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookImportIdsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempCsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempCsvPath = storage_path('framework/testing/test_cuentas_fb.csv');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCsvPath)) {
            unlink($this->tempCsvPath);
        }
        parent::tearDown();
    }

    public function test_updates_existing_profile_facebook_id_and_links_collaborator(): void
    {
        $collaborator = User::factory()->create([
            'name' => 'Deinna',
            'email' => 'deinna@evidencias.local',
            'role' => 'user',
            'is_active' => true,
        ]);

        $emailAccount = EmailAccount::create([
            'alias' => 'Enrique Vazquez',
            'email' => 'enrivazq431@gmail.com',
            'password' => 'K2ed#4dw',
            'status' => 'active',
        ]);

        $profile = Profile::create([
            'email_account_id' => $emailAccount->id,
            'name' => 'Enrique Vazquez',
            'facebook_identifier' => null,
            'password' => 'k9#mP$8vQ2!zL5xW',
            'social_network' => 'Facebook',
            'status' => 'active',
        ]);

        $this->writeCsv([
            ['1', 'Deinna', 'Deinna', 'Enrique Vazquez', 'enrivazq431@gmail.com', 'K2ed#4dw', 'k9#mP$8vQ2!zL5xW', 'id:61593132772027', '61593132772027', 'https://www.facebook.com/profile.php?id=61593132772027', 'DEINNA', 'active', 'UNICO', 'Hector', ''],
        ]);

        $this->artisan('facebook:import-ids', ['file' => $this->tempCsvPath])
            ->assertSuccessful();

        $profile->refresh();
        $this->assertSame('id:61593132772027', $profile->facebook_identifier);
        $this->assertTrue($profile->users()->whereKey($collaborator->id)->exists());
    }

    public function test_creates_missing_entities_when_flag_is_enabled(): void
    {
        $this->writeCsv([
            ['1', 'Katya', 'Uresti', 'Dorian Fontiveros', 'oy7c4@outlook.es', 'Reynosa1', 'Reynosa1', 'id:61594687662647', '61594687662647', 'https://www.facebook.com/profile.php?id=61594687662647', 'KATYA', 'active', 'UNICO', '', ''],
        ]);

        $this->artisan('facebook:import-ids', [
            'file' => $this->tempCsvPath,
            '--create-missing' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'Katya',
            'email' => 'katya@evidencias.local',
            'role' => 'user',
        ]);

        $this->assertDatabaseHas('email_accounts', [
            'email' => 'oy7c4@outlook.es',
        ]);

        $profile = Profile::where('facebook_identifier', 'id:61594687662647')->first();
        $this->assertNotNull($profile);
        $this->assertSame('Dorian Fontiveros', $profile->name);
        $this->assertTrue($profile->users()->where('email', 'katya@evidencias.local')->exists());
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $this->writeCsv([
            ['1', 'Eleazar', 'Eleazar', 'Alfredo Gamez', 'alfredogamez09092000@atomicmail.io', 'Aguadecoco$007', 'Aguadecoco$007', 'id:61593106918446', '61593106918446', 'https://www.facebook.com/profile.php?id=61593106918446', 'ELEAZAR', 'active', 'UNICO', '', ''],
        ]);

        $this->artisan('facebook:import-ids', [
            'file' => $this->tempCsvPath,
            '--create-missing' => true,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('email_accounts', [
            'email' => 'alfredogamez09092000@atomicmail.io',
        ]);
        $this->assertDatabaseMissing('profiles', [
            'facebook_identifier' => 'id:61593106918446',
        ]);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function writeCsv(array $rows): void
    {
        $fp = fopen($this->tempCsvPath, 'w');
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, [
            'No', 'Colaborador_Actual', 'Colaborador_Hoja_General', 'Nombre_Perfil',
            'Correo', 'Password_Correo', 'Password_Facebook', 'Facebook_Identifier_TraceX',
            'Facebook_ID_Numerico', 'Facebook_URL', 'Hoja_Origen_FB', 'Estatus_Sugerido',
            'Validacion_ID', 'Creada_Por', 'Observaciones',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($fp, $row, ',', '"', '');
        }

        fclose($fp);
    }
}
