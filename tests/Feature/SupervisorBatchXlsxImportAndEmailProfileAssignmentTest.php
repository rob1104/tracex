<?php

namespace Tests\Feature;

use App\Livewire\Admin\Emails\Index as EmailsIndex;
use App\Livewire\Admin\FacebookMonitoring\Index as MonitoringIndex;
use App\Livewire\Admin\Profiles\Index as ProfilesIndex;
use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use App\Services\EvidenceExcelWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class SupervisorBatchXlsxImportAndEmailProfileAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private string $tempXlsxPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempXlsxPath = storage_path('framework/testing/lote_110_perfiles.xlsx');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempXlsxPath)) {
            @unlink($this->tempXlsxPath);
        }
        parent::tearDown();
    }

    public function test_supervisor_can_batch_import_over_100_facebook_profiles_and_emails_from_xlsx(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Supervisor Lote',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $collaborator = User::factory()->create([
            'name' => 'Carlos Operador',
            'email' => 'carlos.operador@example.com',
            'role' => 'user',
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $rows = [
            [
                'Correo',
                'Contraseña Correo',
                'Nombre del Perfil',
                'Enlace o ID de Facebook',
                'Contraseña Facebook',
                'Colaborador',
                'Alias Correo',
                'Estatus',
            ],
        ];

        for ($i = 1; $i <= 115; $i++) {
            $padded = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $rows[] = [
                "cuenta.lote.{$padded}@tracex.test",
                "CorreoPass{$padded}!",
                "Perfil Lote {$padded}",
                "https://www.facebook.com/profile.php?id=6159000000{$padded}",
                "FbPass{$padded}!",
                'Carlos Operador',
                "Alias {$padded}",
                'Activo',
            ];
        }

        app(EvidenceExcelWorkbook::class)->create([
            [
                'name' => 'Nuevos_Perfiles',
                'rows' => $rows,
            ],
        ], $this->tempXlsxPath);

        $uploadedFile = UploadedFile::fake()->create('lote.xlsx', 10, 'application/zip');
        file_put_contents($uploadedFile->getRealPath(), (string) file_get_contents($this->tempXlsxPath));

        Livewire::actingAs($supervisor)
            ->test(ProfilesIndex::class)
            ->call('openBatchImportModal')
            ->assertSet('showBatchImportModal', true)
            ->set('batchFile', $uploadedFile)
            ->call('importBatchXlsx')
            ->assertHasNoErrors()
            ->assertSet('batchImportSummary.total_rows', 115)
            ->assertSet('batchImportSummary.emails_created', 115)
            ->assertSet('batchImportSummary.profiles_created', 115)
            ->assertSet('batchImportSummary.collaborator_links', 115)
            ->assertSet('batchImportSummary.skipped_rows', 0);

        $this->assertSame(115, EmailAccount::where('created_by', $supervisor->id)->count());
        $this->assertSame(115, Profile::where('created_by', $supervisor->id)->count());
        $this->assertSame(115, $collaborator->assignedProfiles()->count());

        $sampleEmail = EmailAccount::where('email', 'cuenta.lote.115@tracex.test')->first();
        $this->assertNotNull($sampleEmail);

        $sampleProfile = Profile::where('email_account_id', $sampleEmail->id)->first();
        $this->assertNotNull($sampleProfile);
        $this->assertSame('Perfil Lote 115', $sampleProfile->name);
        $this->assertSame('id:6159000000115', $sampleProfile->facebook_identifier);
        $this->assertTrue($sampleProfile->users()->whereKey($collaborator->id)->exists());
    }

    public function test_supervisor_assigning_email_to_collaborator_automatically_links_facebook_profile_to_user(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Supervisor Asignador',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $collaborator = User::factory()->create([
            'name' => 'Ana Colaboradora',
            'role' => 'user',
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $emailAccount = EmailAccount::create([
            'alias' => 'Cuenta Enlazada FB',
            'email' => 'correo.enlazado@tracex.test',
            'password' => 'Secret123!',
            'status' => 'active',
            'created_by' => $supervisor->id,
        ]);

        $facebookProfile = Profile::create([
            'email_account_id' => $emailAccount->id,
            'name' => 'Perfil FB Enlazado',
            'facebook_identifier' => 'id:1000888777666',
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => $supervisor->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(EmailsIndex::class)
            ->call('openAssignEmailModal', $emailAccount->id)
            ->assertSet('showAssignEmailModal', true)
            ->assertSet('assignProfileId', (string) $facebookProfile->id)
            ->set('assignCollaboratorIds', [$collaborator->id])
            ->call('saveEmailAssignment')
            ->assertHasNoErrors()
            ->assertSet('showAssignEmailModal', false);

        $this->assertTrue($facebookProfile->fresh()->users()->whereKey($collaborator->id)->exists());
    }

    public function test_supervisor_assigning_email_account_to_facebook_profile_inherits_email_collaborator(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Supervisor Perfiles',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $collaborator = User::factory()->create([
            'name' => 'Luis Colaborador',
            'role' => 'user',
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        // Supervisor registers email account with collaborator in EmailsIndex
        Livewire::actingAs($supervisor)
            ->test(EmailsIndex::class)
            ->call('create')
            ->set('alias', 'Luis FB')
            ->set('email', 'luis.fb@tracex.test')
            ->set('password', 'Pass1234!')
            ->set('status', 'active')
            ->set('facebook_profile_name', 'Luis Perfil Facebook')
            ->set('facebook_identifier', 'https://www.facebook.com/luis.perfil.fb')
            ->set('collaborator_id', (string) $collaborator->id)
            ->call('save')
            ->assertHasNoErrors();

        $emailAccount = EmailAccount::where('email', 'luis.fb@tracex.test')->firstOrFail();
        $profile = Profile::where('email_account_id', $emailAccount->id)->firstOrFail();

        $this->assertSame('Luis Perfil Facebook', $profile->name);
        $this->assertSame('uri:https://facebook.com/luis.perfil.fb', $profile->facebook_identifier);
        $this->assertTrue($profile->users()->whereKey($collaborator->id)->exists());

        // When registering another Facebook profile on the same email account via MonitoringIndex, it automatically links to Luis Colaborador
        Livewire::actingAs($supervisor)
            ->test(MonitoringIndex::class)
            ->set('monitorName', 'Luis Segundo Perfil FB')
            ->set('monitorFacebookUrl', 'https://www.facebook.com/profile.php?id=1000555444333')
            ->set('monitorEmailAccountId', (string) $emailAccount->id)
            ->call('registerMonitoredFacebookProfile')
            ->assertHasNoErrors();

        $secondProfile = Profile::where('facebook_identifier', 'id:1000555444333')->firstOrFail();
        $this->assertSame($emailAccount->id, $secondProfile->email_account_id);
        $this->assertTrue($secondProfile->users()->whereKey($collaborator->id)->exists());
    }

    public function test_supervisor_can_download_xlsx_batch_template(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        Livewire::actingAs($supervisor)
            ->test(ProfilesIndex::class)
            ->call('downloadBatchTemplate')
            ->assertFileDownloaded('plantilla_importacion_perfiles_facebook.xlsx');

        Livewire::actingAs($supervisor)
            ->test(EmailsIndex::class)
            ->call('downloadBatchTemplate')
            ->assertFileDownloaded('plantilla_importacion_perfiles_facebook.xlsx');
    }

    public function test_supervisor_can_import_cuentas_ximena_csv_format_with_banner_row_and_link_del_perfil(): void
    {
        $supervisor = User::factory()->create([
            'name' => 'Ximena Supervisor',
            'email' => 'XimenaSupervisor@scriptx.mx',
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $collaborator = User::factory()->create([
            'name' => 'Ximena Rivera',
            'email' => 'monseriveraa11@gmail.com',
            'role' => 'user',
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $csvLines = [
            'fr,,,,,,,,,,07/09/2026,,,,,08/09/2026',
            'No.,,Nombre,Correo,Contraseña Gmail,Contraseña Facebook,Link Del Perfil,Amigos,Foto,Publicaciones,MANTENIMIENTO',
            '1,07/11/2026,Ximena Casares,tee456b@outlook.com,Reynosa1,Reynosa1,https://www.facebook.com/profile.php?id=61595951207272,,,Netito Reynosa,X',
            '2,07/11/2026,Eliza Lopez,pcc567v@outlook.com,Reynosa1,Reynosa1,,,,Netito Reynosa,X',
            '9,10/11/2026,Camila Rojas,camila13581@outlook.com,Victoria100,Victoria100,https://www.facebook.com/people/Camila-Rojas/pfbid02etxdqj5TKfreoiW6t3PNCapo63tV8sUmo2fUrPnnv3E6rkMDRkd8nxYAUjgRNj3Cl/,,,Michelle Victoria,X',
            '31,10/11/2026,Brayan Zavala,cuenta19akfn@outlook.com,Victoria100,Victoria100,https://www.facebook.com/profile.php?id=61596149053565,,,,X',
            '32,10/11/2026,Fernanda Sanchez,kc0464999@gmail.com,Victoria100,Victoria100,https://www.facebook.com/profile.php?id=61596149053565,,,,X',
            '103,,,,,,,,,,',
            '104,,,,,,,,,,',
        ];

        $uploadedCsv = UploadedFile::fake()->create('Cuentas.xlsx - Ximena.csv', 10, 'text/csv');
        file_put_contents($uploadedCsv->getRealPath(), implode("\n", $csvLines));

        Livewire::actingAs($supervisor)
            ->test(ProfilesIndex::class)
            ->call('openBatchImportModal')
            ->set('batchFile', $uploadedCsv)
            ->call('importBatchXlsx')
            ->assertHasNoErrors()
            ->assertSet('batchImportSummary.total_rows', 5)
            ->assertSet('batchImportSummary.emails_created', 5)
            ->assertSet('batchImportSummary.profiles_created', 5)
            ->assertSet('batchImportSummary.collaborator_links', 5);

        $this->assertSame(5, $collaborator->assignedProfiles()->count());

        $ximenaProfile = Profile::where('name', 'Ximena Casares')->firstOrFail();
        $this->assertSame('id:61595951207272', $ximenaProfile->facebook_identifier);
        $this->assertSame('Reynosa1', $ximenaProfile->emailAccount->password);

        $fernandaProfile = Profile::where('name', 'Fernanda Sanchez')->firstOrFail();
        $this->assertNull($fernandaProfile->facebook_identifier);
        $this->assertTrue($fernandaProfile->users()->whereKey($collaborator->id)->exists());
    }
}
