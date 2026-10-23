<?php

namespace Tests\Feature;

use App\Livewire\Admin\EvidenceReports\Index;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EvidenceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_and_cuentas_can_access_the_report(): void
    {
        foreach (['admin', 'cuentas'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user)
                ->get('/admin/reporte-evidencias')
                ->assertOk()
                ->assertSee(route('admin.evidence-reports.index'), false);
        }
    }

    public function test_regular_collaborators_cannot_access_the_report(): void
    {
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $this->actingAs($collaborator)
            ->get('/admin/reporte-evidencias')
            ->assertForbidden();
    }

    public function test_report_includes_zero_evidence_collaborators_and_excludes_other_roles(): void
    {
        $type = EvidenceType::create(['name' => 'Comentario', 'is_active' => true]);
        $collaborator = User::factory()->create(['name' => 'Ana Colaboradora', 'role' => 'user', 'is_active' => true]);
        $zeroCollaborator = User::factory()->create(['name' => 'Beto Sin Registros', 'role' => 'user', 'is_active' => true]);
        $account = User::factory()->create(['name' => 'Cuenta No Incluida', 'role' => 'cuentas', 'is_active' => true]);
        $this->createEvidence($collaborator, $type, '2026-06-05 09:30:00');
        $this->createEvidence($account, $type, '2026-06-05 10:30:00');

        Livewire::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]))
            ->test(Index::class)
            ->assertSee('Ana Colaboradora')
            ->assertSee('Beto Sin Registros')
            ->assertSee('1')
            ->assertSee('0')
            ->assertDontSee('Cuenta No Incluida');
    }

    public function test_date_filters_include_both_calendar_day_boundaries_and_update_total(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $type = EvidenceType::create(['name' => 'Comentario', 'is_active' => true]);
        $collaborator = User::factory()->create(['name' => 'Rango Fechas', 'role' => 'user', 'is_active' => true]);

        $this->createEvidence($collaborator, $type, '2026-06-09 23:59:59');
        $this->createEvidence($collaborator, $type, '2026-06-10 00:00:00');
        $this->createEvidence($collaborator, $type, '2026-06-10 23:59:59');
        $this->createEvidence($collaborator, $type, '2026-06-11 00:00:00');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('dateFrom', '2026-06-10')
            ->set('dateTo', '2026-06-10')
            ->assertSee('2')
            ->assertSee('Rango Fechas');
    }

    public function test_invalid_reversed_date_range_does_not_show_unfiltered_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $type = EvidenceType::create(['name' => 'Comentario', 'is_active' => true]);
        $collaborator = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->createEvidence($collaborator, $type, '2026-06-10 12:00:00');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('dateFrom', '2026-06-11')
            ->set('dateTo', '2026-06-10')
            ->assertHasErrors('dateTo')
            ->assertSee('0')
            ->assertSee('La fecha final debe ser válida y no anterior a la inicial.');
    }

    public function test_search_and_collaborator_selection_can_be_combined(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = User::factory()->create(['name' => 'Carla Norte', 'role' => 'user', 'is_active' => true]);
        $second = User::factory()->create(['name' => 'Carla Sur', 'role' => 'user', 'is_active' => true]);
        $third = User::factory()->create(['name' => 'Daniel Este', 'role' => 'user', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('search', 'Carla')
            ->set('collaboratorIds', [$second->id, $third->id])
            ->assertSee('Carla Sur')
            ->assertSee('evidence-report-collaborator-'.$second->id)
            ->assertDontSee('evidence-report-collaborator-'.$first->id)
            ->assertDontSee('evidence-report-collaborator-'.$third->id);
    }

    public function test_report_can_sort_by_evidence_total_and_paginate_collaborators(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $type = EvidenceType::create(['name' => 'Comentario', 'is_active' => true]);
        $moreActive = User::factory()->create(['name' => 'Colaborador Más Activo', 'role' => 'user', 'is_active' => true]);
        $lessActive = User::factory()->create(['name' => 'Colaborador Menos Activo', 'role' => 'user', 'is_active' => true]);
        $this->createEvidence($moreActive, $type, '2026-06-10 12:00:00');
        $this->createEvidence($moreActive, $type, '2026-06-10 12:30:00');
        $this->createEvidence($lessActive, $type, '2026-06-10 13:00:00');

        $component = Livewire::actingAs($admin)->test(Index::class);
        $component->call('sortBy', 'evidence_count')->call('sortBy', 'evidence_count')
            ->assertSeeInOrder([
                'evidence-report-collaborator-'.$moreActive->id,
                'evidence-report-collaborator-'.$lessActive->id,
            ]);

        $lastCollaborator = null;
        for ($index = 1; $index <= 16; $index++) {
            $lastCollaborator = User::factory()->create([
                'name' => sprintf('Paginado %02d', $index),
                'role' => 'user',
                'is_active' => true,
            ]);
        }

        $component->set('search', 'Paginado')
            ->assertDontSee('evidence-report-collaborator-'.$lastCollaborator->id)
            ->call('gotoPage', 2)
            ->assertSee('evidence-report-collaborator-'.$lastCollaborator->id);
    }

    public function test_csv_and_pdf_exports_are_available_to_authorized_users(): void
    {
        $cuentas = User::factory()->create(['role' => 'cuentas', 'is_active' => true]);
        $type = EvidenceType::create(['name' => 'Comentario', 'is_active' => true]);
        $collaborator = User::factory()->create([
            'name' => '=Ángela CSV',
            'email' => 'angela@example.com',
            'role' => 'user',
            'is_active' => true,
        ]);
        $this->createEvidence($collaborator, $type, '2026-06-10 12:30:00');

        Livewire::actingAs($cuentas)
            ->test(Index::class)
            ->call('exportCsv')
            ->assertFileDownloaded(
                'reporte_evidencias_'.now()->format('Y-m-d').'.csv',
                "\xEF\xBB\xBFColaborador,Correo,\"Total de evidencias\",\"Primera captura\",\"Última captura\"\n\"'=Ángela CSV\",angela@example.com,1,\"2026-06-10 12:30:00\",\"2026-06-10 12:30:00\"\n"
            );

        Livewire::actingAs($cuentas)
            ->test(Index::class)
            ->call('exportPdf')
            ->assertFileDownloaded('reporte_evidencias_'.now()->format('Y-m-d').'.pdf');
    }

    private function createEvidence(User $user, EvidenceType $type, string $createdAt): Evidence
    {
        $evidence = Evidence::create([
            'user_id' => $user->id,
            'evidence_type_id' => $type->id,
            'social_network' => 'Facebook',
        ]);
        $evidence->created_at = Carbon::parse($createdAt);
        $evidence->save();

        return $evidence;
    }
}
