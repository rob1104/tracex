<?php

namespace App\Livewire\Admin\EvidenceReports;

use App\Models\Evidence;
use App\Models\FacebookImportLog;
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

        if (! in_array($column, ['name', 'evidence_count', 'successful_exports_count', 'empty_imports_count'], true)) {
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
            fputcsv($file, ['Colaborador', 'Correo', 'Total de evidencias', 'Exportaciones exitosas', 'Importaciones vacías']);

            foreach ($collaborators as $collaborator) {
                fputcsv($file, [
                    $this->escapeSpreadsheetValue($collaborator->name),
                    $this->escapeSpreadsheetValue($collaborator->email),
                    $collaborator->evidence_count,
                    $collaborator->successful_exports_count,
                    $collaborator->empty_imports_count,
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
            'totalSuccessfulExports' => $collaborators->sum('successful_exports_count'),
            'totalEmptyImports' => $collaborators->sum('empty_imports_count'),
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
        $query = User::query()
            ->where('role', 'user')
            ->withCount(['evidences as evidence_count' => fn (Builder $query) => $this->applyDateRange($query)])
            ->addSelect([
                'successful_exports_count' => $this->importsForCollaboratorQuery()
                    ->where('facebook_import_logs.status', 'completed')
                    ->selectRaw('COUNT(*)'),
                'empty_imports_count' => $this->emptyImportsForCollaboratorQuery()->selectRaw('COUNT(*)'),
            ]);

        $this->applyCollaboratorFilters($query);

        $allowedSortColumns = ['name', 'evidence_count', 'successful_exports_count', 'empty_imports_count'];
        $sortColumn = in_array($this->sortColumn, $allowedSortColumns, true) ? $this->sortColumn : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortColumn, $sortDirection)->orderBy('id');
    }

    private function importsForCollaboratorQuery(): Builder
    {
        $query = FacebookImportLog::query()
            ->join('profile_user', 'profile_user.profile_id', '=', 'facebook_import_logs.profile_id')
            ->whereColumn('profile_user.user_id', 'users.id');

        $this->applyDateRange($query, 'facebook_import_logs.created_at');

        return $query;
    }

    private function emptyImportsForCollaboratorQuery(): Builder
    {
        return $this->importsForCollaboratorQuery()
            ->whereIn('facebook_import_logs.status', ['completed', 'pending_assignment'])
            ->where(fn (Builder $query) => $query->whereNull('facebook_import_logs.total_comments_extracted')->orWhere('facebook_import_logs.total_comments_extracted', '<=', 0))
            ->where(fn (Builder $query) => $query->whereNull('facebook_import_logs.total_reactions_extracted')->orWhere('facebook_import_logs.total_reactions_extracted', '<=', 0))
            ->whereDoesntHave('comments')
            ->whereDoesntHave('reactions')
            ->where(fn (Builder $query) => $query
                ->whereNull('facebook_import_logs.metadata->facebook_shares_count')
                ->orWhere('facebook_import_logs.metadata->facebook_shares_count', '<=', 0))
            ->where(fn (Builder $query) => $query
                ->whereNull('facebook_import_logs.metadata->facebook_shares')
                ->orWhereJsonLength('facebook_import_logs.metadata->facebook_shares', '=', 0))
            ->whereDoesntHave('evidences', fn (Builder $query) => $query
                ->whereHas('evidenceType', fn (Builder $typeQuery) => $typeQuery->where('name', 'Compartido')));
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

    private function applyDateRange(Builder $query, string $column = 'created_at'): void
    {
        if (! $this->dateRangeIsValid()) {
            $query->whereRaw('1 = 0');

            return;
        }

        [$start, $end] = $this->validDateRange();

        if ($start !== null) {
            $query->where($column, '>=', $start);
        }

        if ($end !== null) {
            $query->where($column, '<', $end);
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
