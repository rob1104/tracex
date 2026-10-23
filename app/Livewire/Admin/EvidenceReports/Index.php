<?php

namespace App\Livewire\Admin\EvidenceReports;

use App\Models\Evidence;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    /** @var array<int, int|string> */
    public array $collaboratorIds = [];

    public string $sortColumn = 'name';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'dateFrom', 'dateTo', 'collaboratorIds'], true) || str_starts_with($property, 'collaboratorIds.')) {
            $this->resetPage();
        }

        if (in_array($property, ['dateFrom', 'dateTo'], true)) {
            $this->validateOnly($property, $this->rules());

            if ($this->dateFrom !== '' && $this->dateTo !== '') {
                $this->validateOnly('dateTo', $this->rules());
            }
        }

        if ($property === 'search') {
            $this->validateOnly('search', $this->rules());
        }

        if ($property === 'collaboratorIds' || str_starts_with($property, 'collaboratorIds.')) {
            $this->validate($this->rules());
        }
    }

    public function sortBy(string $column): void
    {
        $this->authorizeAccess();

        if (! in_array($column, ['name', 'evidence_count', 'first_evidence_at', 'last_evidence_at'], true)) {
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->authorizeAccess();
        $this->reset(['search', 'dateFrom', 'dateTo', 'collaboratorIds']);
        $this->resetPage();
    }

    public function clearCollaboratorSelection(): void
    {
        $this->authorizeAccess();
        $this->reset('collaboratorIds');
        $this->resetPage();
    }

    public function exportCsv()
    {
        $this->authorizeAccess();
        $this->validate($this->rules());
        $collaborators = $this->collaboratorsQuery()->get();

        return response()->streamDownload(function () use ($collaborators): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Colaborador', 'Correo', 'Total de evidencias', 'Primera captura', 'Última captura']);

            foreach ($collaborators as $collaborator) {
                fputcsv($file, [
                    $this->escapeSpreadsheetValue($collaborator->name),
                    $this->escapeSpreadsheetValue($collaborator->email),
                    $collaborator->evidence_count,
                    $collaborator->first_evidence_at ? Carbon::parse($collaborator->first_evidence_at)->format('Y-m-d H:i:s') : '',
                    $collaborator->last_evidence_at ? Carbon::parse($collaborator->last_evidence_at)->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($file);
        }, 'reporte_evidencias_'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf()
    {
        $this->authorizeAccess();
        $this->validate($this->rules());

        $collaborators = $this->collaboratorsQuery()->get();
        $pdf = Pdf::loadView('pdf.evidence-reports', [
            'collaborators' => $collaborators,
            'totalEvidence' => $this->totalEvidenceCount(),
            'filters' => $this->filterLabels(),
            'generator' => auth()->user(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, 'reporte_evidencias_'.now()->format('Y-m-d').'.pdf');
    }

    protected function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
            'collaboratorIds' => ['array'],
            'collaboratorIds.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function render()
    {
        $this->authorizeAccess();

        $filteredCollaborators = $this->collaboratorsQuery();
        $totalEvidence = $this->totalEvidenceCount();

        return view('livewire.admin.evidence-reports.index', [
            'collaborators' => $filteredCollaborators->paginate(15),
            'collaboratorOptions' => User::query()
                ->where('role', 'user')
                ->orderBy('name')
                ->get(['id', 'name']),
            'totalCollaborators' => (clone $filteredCollaborators)->count(),
            'totalEvidence' => $totalEvidence,
        ])->layout('layouts.app');
    }

    private function collaboratorsQuery(): Builder
    {
        $periodConstraint = fn (Builder $query) => $this->applyDateRange($query);

        $query = User::query()
            ->where('role', 'user')
            ->withCount(['evidences as evidence_count' => $periodConstraint])
            ->withMin(['evidences as first_evidence_at' => $periodConstraint], 'created_at')
            ->withMax(['evidences as last_evidence_at' => $periodConstraint], 'created_at');

        $this->applyCollaboratorFilters($query);

        $allowedSortColumns = ['name', 'evidence_count', 'first_evidence_at', 'last_evidence_at'];
        $sortColumn = in_array($this->sortColumn, $allowedSortColumns, true) ? $this->sortColumn : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortColumn, $sortDirection)->orderBy('id');
    }

    private function applyCollaboratorFilters(Builder $query): void
    {
        $query
            ->when(trim($this->search) !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($this->collaboratorIds !== [], function (Builder $builder): void {
                $builder->whereIn('id', array_map('intval', $this->collaboratorIds));
            });
    }

    private function applyDateRange(Builder $query): void
    {
        if (! $this->dateRangeIsValid()) {
            $query->whereRaw('1 = 0');

            return;
        }

        [$start, $end] = $this->validDateRange();

        if ($start !== null) {
            $query->where('created_at', '>=', $start);
        }

        if ($end !== null) {
            $query->where('created_at', '<', $end);
        }
    }

    private function totalEvidenceCount(): int
    {
        if (! $this->dateRangeIsValid()) {
            return 0;
        }

        [$start, $end] = $this->validDateRange();

        return Evidence::query()
            ->whereHas('user', function (Builder $query): void {
                $query->where('role', 'user');
                $this->applyCollaboratorFilters($query);
            })
            ->when($start !== null, fn (Builder $query) => $query->where('created_at', '>=', $start))
            ->when($end !== null, fn (Builder $query) => $query->where('created_at', '<', $end))
            ->count();
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function validDateRange(): array
    {
        $start = $this->parseDate($this->dateFrom)?->startOfDay();
        $endDate = $this->parseDate($this->dateTo);

        return [$start, $endDate?->addDay()->startOfDay()];
    }

    private function dateRangeIsValid(): bool
    {
        $start = $this->parseDate($this->dateFrom);
        $end = $this->parseDate($this->dateTo);

        if (($this->dateFrom !== '' && $start === null) || ($this->dateTo !== '' && $end === null)) {
            return false;
        }

        return $start === null || $end === null || $end->greaterThanOrEqualTo($start);
    }

    private function parseDate(string $date): ?Carbon
    {
        if ($date === '') {
            return null;
        }

        try {
            $parsedDate = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        return $parsedDate !== false && $parsedDate->format('Y-m-d') === $date ? $parsedDate : null;
    }

    private function filterLabels(): array
    {
        return [
            'search' => trim($this->search) !== '' ? trim($this->search) : 'Todos',
            'dateFrom' => $this->dateFrom !== '' ? Carbon::parse($this->dateFrom)->format('d/m/Y') : 'Sin límite',
            'dateTo' => $this->dateTo !== '' ? Carbon::parse($this->dateTo)->format('d/m/Y') : 'Sin límite',
            'collaborators' => $this->collaboratorIds === []
                ? 'Todos'
                : User::query()->whereIn('id', array_map('intval', $this->collaboratorIds))->where('role', 'user')->orderBy('name')->pluck('name')->implode(', '),
        ];
    }

    private function escapeSpreadsheetValue(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isCuentas()), 403);
    }
}
