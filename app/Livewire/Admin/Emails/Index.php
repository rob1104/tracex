<?php

namespace App\Livewire\Admin\Emails;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use App\Services\BatchAccountProfileImporter;
use App\Services\FacebookProfileIdentifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $showPasswordModal = false;

    public $emailId = null;

    #[Validate('nullable|string|max:255')]
    public $alias = '';

    #[Validate('required|email|max:255')]
    public $email = '';

    #[Validate('required|string|max:255')]
    public $password = '';

    #[Validate('required|in:active,suspended')]
    public $status = 'active';

    public $revealedPassword = '';

    public $created_by = '';

    public string $facebook_profile_name = '';

    public string $facebook_identifier = '';

    public string $facebook_password = '';

    public string $collaborator_id = '';

    public bool $showAssignEmailModal = false;

    public ?int $assigningEmailId = null;

    public string $assigningEmailAddress = '';

    public string $assignProfileId = '';

    public string $assignProfileName = '';

    public string $assignFacebookIdentifier = '';

    /** @var array<int, int|string> */
    public array $assignCollaboratorIds = [];

    public bool $showBatchImportModal = false;

    public $batchFile = null;

    public string $batchCollaboratorId = '';

    public bool $batchOverrideCollaborator = false;

    public bool $batchCreateMissingCollaborators = true;

    /** @var array<string, mixed>|null */
    public ?array $batchImportSummary = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public $showMassAssignModal = false;

    public $sourceGestorId = '';

    public $destinationGestorId = '';

    public $sourceAccounts = [];

    public $selectedAccounts = [];

    public function updatedSourceGestorId($value)
    {
        $this->authorizeAccountManagement();

        if ((auth()->user()->isCuentas() || auth()->user()->isSupervisor()) && $value != auth()->id()) {
            return;
        }
        if ($value) {
            $this->sourceAccounts = EmailAccount::where('created_by', $value)->orderBy('email', 'asc')->get();
            $this->selectedAccounts = $this->sourceAccounts->pluck('id')->toArray();
        } else {
            $this->sourceAccounts = [];
            $this->selectedAccounts = [];
        }
    }

    public function toggleSelectAll()
    {
        if (count($this->selectedAccounts) === count($this->sourceAccounts)) {
            $this->selectedAccounts = [];
        } else {
            $this->selectedAccounts = $this->sourceAccounts->pluck('id')->toArray();
        }
    }

    public function openMassAssignModal()
    {
        $this->authorizeAccountManagement();
        $this->reset(['sourceGestorId', 'destinationGestorId', 'sourceAccounts', 'selectedAccounts']);
        $this->showMassAssignModal = true;
    }

    public function executeMassAssignment()
    {
        $this->authorizeAccountManagement();

        if ((auth()->user()->isCuentas() || auth()->user()->isSupervisor()) && $this->sourceGestorId != auth()->id()) {
            abort(403);
        }
        $this->validate([
            'sourceGestorId' => 'required|exists:users,id',
            'destinationGestorId' => 'required|exists:users,id|different:sourceGestorId',
            'selectedAccounts' => 'required|array|min:1',
            'selectedAccounts.*' => 'exists:email_accounts,id',
        ], [
            'destinationGestorId.different' => 'El gestor destino no puede ser el mismo que el gestor origen.',
            'selectedAccounts.required' => 'Debes seleccionar al menos una cuenta para reasignar.',
        ]);

        EmailAccount::whereIn('id', $this->selectedAccounts)->update(['created_by' => $this->destinationGestorId]);
        Profile::whereIn('email_account_id', $this->selectedAccounts)->update(['created_by' => $this->destinationGestorId]);

        $this->showMassAssignModal = false;
        session()->flash('status', 'Cuentas y sus perfiles asociados han sido reasignados correctamente.');
    }

    public function openBatchImportModal(): void
    {
        $this->authorizeAccountManagement();
        $this->resetValidation();
        $this->reset([
            'batchFile',
            'batchCollaboratorId',
            'batchOverrideCollaborator',
            'batchImportSummary',
        ]);
        $this->batchCreateMissingCollaborators = true;
        $this->showBatchImportModal = true;
    }

    public function downloadBatchTemplate()
    {
        $this->authorizeAccountManagement();

        return response()->streamDownload(function (): void {
            $tempPath = tempnam(sys_get_temp_dir(), 'tracex_plantilla_perfiles_').'.xlsx';
            try {
                app(BatchAccountProfileImporter::class)->createTemplate($tempPath);
                readfile($tempPath);
            } finally {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }, 'plantilla_importacion_perfiles_facebook.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importBatchXlsx(): void
    {
        $this->authorizeAccountManagement();
        $this->resetValidation('batchFile');

        $this->validate([
            'batchFile' => 'required|file|max:20480',
            'batchCollaboratorId' => 'nullable|integer|exists:users,id',
        ], [
            'batchFile.required' => 'Selecciona un archivo .xlsx o .csv para importar.',
            'batchFile.max' => 'El archivo no debe superar los 20 MB.',
        ]);

        $originalName = (string) $this->batchFile->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            $this->addError('batchFile', 'El archivo debe ser de formato Excel (.xlsx) o CSV (.csv).');

            return;
        }

        try {
            $summary = app(BatchAccountProfileImporter::class)->importFromFile(
                $this->batchFile->getRealPath(),
                auth()->user(),
                [
                    'default_collaborator_id' => $this->batchCollaboratorId !== '' ? (int) $this->batchCollaboratorId : null,
                    'override_collaborator' => $this->batchOverrideCollaborator,
                    'create_missing_collaborators' => $this->batchCreateMissingCollaborators,
                    'created_by' => auth()->id(),
                ],
                $extension,
                $originalName
            );
        } catch (Throwable $e) {
            $this->addError('batchFile', $e->getMessage());

            return;
        }

        $this->batchImportSummary = $summary;
        $this->reset('batchFile');
        $this->resetPage();

        session()->flash(
            'status',
            "Importación por lote completada: {$summary['emails_created']} correos nuevos, {$summary['profiles_created']} perfiles creados, {$summary['profiles_updated']} perfiles actualizados y {$summary['collaborator_links']} vínculos con colaboradores."
        );
    }

    public function openAssignEmailModal(int $id): void
    {
        $this->authorizeAccountManagement();
        $this->resetValidation();

        $account = EmailAccount::with(['profiles.users'])->findOrFail($id);
        if (! $this->canManageEmail($account)) {
            abort(403);
        }

        $fbProfile = $account->profiles
            ->first(fn (Profile $p): bool => strtolower($p->social_network) === 'facebook')
            ?? $account->profiles->first();

        $this->assigningEmailId = $account->id;
        $this->assigningEmailAddress = $account->email;
        $this->assignProfileId = $fbProfile ? (string) $fbProfile->id : '';
        $this->assignProfileName = $fbProfile?->name ?? ($account->alias ?? '');
        $this->assignFacebookIdentifier = $fbProfile?->facebook_identifier ?? '';
        $this->assignCollaboratorIds = $account->profiles
            ->flatMap(fn (Profile $p) => $p->users->pluck('id'))
            ->map(fn ($uid): int => (int) $uid)
            ->unique()
            ->values()
            ->all();

        $this->showAssignEmailModal = true;
    }

    public function updatedAssignProfileId($value): void
    {
        if ($value !== '' && is_numeric($value)) {
            $profile = Profile::with('users:id')->find((int) $value);
            if ($profile) {
                $this->assignProfileName = $profile->name;
                $this->assignFacebookIdentifier = (string) ($profile->facebook_identifier ?? '');
                if ($this->assignCollaboratorIds === []) {
                    $this->assignCollaboratorIds = $profile->users->pluck('id')->map(fn ($id): int => (int) $id)->all();
                }
            }
        }
    }

    public function saveEmailAssignment(): void
    {
        $this->authorizeAccountManagement();
        abort_if($this->assigningEmailId === null, 422);

        $account = EmailAccount::findOrFail($this->assigningEmailId);
        if (! $this->canManageEmail($account)) {
            abort(403);
        }

        $this->validate([
            'assignProfileId' => 'nullable|integer|exists:profiles,id',
            'assignProfileName' => 'nullable|string|max:255',
            'assignFacebookIdentifier' => 'nullable|string|max:512',
            'assignCollaboratorIds' => 'array',
            'assignCollaboratorIds.*' => 'integer|exists:users,id',
        ]);

        $rawFb = trim($this->assignFacebookIdentifier);
        $normalizedFb = $rawFb !== '' ? FacebookProfileIdentifier::normalize($rawFb) : null;
        if ($rawFb !== '' && ($normalizedFb === null || mb_strlen($normalizedFb, 'UTF-8') > 191)) {
            $this->addError('assignFacebookIdentifier', 'Ingresa un ID numérico o un enlace válido de Facebook.');

            return;
        }

        if ($normalizedFb !== null) {
            $alreadyUsed = Profile::query()
                ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                ->where('facebook_identifier', $normalizedFb)
                ->where('email_account_id', '!=', $account->id)
                ->when($this->assignProfileId !== '', fn ($q) => $q->whereKeyNot((int) $this->assignProfileId))
                ->exists();

            if ($alreadyUsed) {
                $this->addError('assignFacebookIdentifier', 'Este ID o enlace de Facebook ya está asignado a otro perfil.');

                return;
            }
        }

        $profile = app(BatchAccountProfileImporter::class)->syncEmailProfileAndCollaborators(
            $account,
            auth()->user(),
            array_map('intval', $this->assignCollaboratorIds),
            [
                'profile_id' => $this->assignProfileId !== '' ? (int) $this->assignProfileId : null,
                'profile_name' => $this->assignProfileName,
                'facebook_identifier' => $rawFb,
                'replace_collaborators' => true,
            ]
        );

        $this->showAssignEmailModal = false;
        $profileLabel = $profile ? " y perfil '{$profile->name}'" : '';
        session()->flash('status', "Cuenta {$account->email}{$profileLabel} vinculada correctamente al colaborador.");
    }

    public function create()
    {
        $this->authorizeAccountManagement();
        $this->resetValidation();
        $this->reset([
            'emailId',
            'alias',
            'email',
            'password',
            'status',
            'facebook_profile_name',
            'facebook_identifier',
            'facebook_password',
            'collaborator_id',
        ]);
        $this->status = 'active';
        $this->created_by = auth()->user()->isAdmin() ? '' : auth()->id();
        $this->showModal = true;
    }

    private function canManageEmail(EmailAccount $account): bool
    {
        if (auth()->user()->isAdmin()) {
            return true;
        }

        if (auth()->user()->isUser()) {
            return false;
        }

        if ($account->created_by === auth()->id()) {
            return true;
        }

        if (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];

            if (in_array((int) $account->created_by, $monitoredIds, true)) {
                return true;
            }

            if ($account->profiles()
                ->whereHas('users', fn ($query) => $query->whereIn('users.id', $monitoredIds))
                ->exists()) {
                return true;
            }

            if (! $account->profiles()->whereHas('users')->exists()) {
                return $account->created_by === null
                    || (bool) ($account->creator && $account->creator->isAdmin());
            }

            return false;
        }

        if ($account->created_by === null) {
            return true;
        }

        return (bool) ($account->creator && $account->creator->isAdmin());
    }

    private function canViewEmail(EmailAccount $account): bool
    {
        if (auth()->user()->isAdmin() || $this->canManageEmail($account)) {
            return true;
        }

        return auth()->user()->isUser()
            && $account->profiles()->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))->exists();
    }

    private function authorizeAccountManagement(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);
    }

    public function edit($id)
    {
        $this->resetValidation();
        $account = EmailAccount::with(['profiles.users'])->findOrFail($id);

        if (! $this->canManageEmail($account)) {
            abort(403);
        }

        $fbProfile = $account->profiles
            ->first(fn (Profile $p): bool => strtolower($p->social_network) === 'facebook')
            ?? $account->profiles->first();

        $this->emailId = $account->id;
        $this->alias = $account->alias;
        $this->email = $account->email;
        try {
            $this->password = $account->password; // Will be decrypted automatically thanks to casting
        } catch (Throwable $e) {
            $this->password = '';
        }
        $this->status = $account->status ?: 'active';
        $this->created_by = $account->created_by;
        $this->facebook_profile_name = $fbProfile?->name ?? '';
        $this->facebook_identifier = (string) ($fbProfile?->facebook_identifier ?? '');
        try {
            $this->facebook_password = (string) ($fbProfile?->password ?? '');
        } catch (Throwable) {
            $this->facebook_password = '';
        }
        $firstCollaboratorId = $account->profiles
            ->flatMap(fn (Profile $p) => $p->users->pluck('id'))
            ->first();
        $this->collaborator_id = $firstCollaboratorId ? (string) $firstCollaboratorId : '';

        $this->showModal = true;
    }

    public function save()
    {
        $this->authorizeAccountManagement();

        $rules = [
            'alias' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:email_accounts,email,'.$this->emailId,
            'password' => 'required|string|max:255',
            'status' => 'required|in:active,suspended',
            'facebook_profile_name' => 'nullable|string|max:255',
            'facebook_identifier' => 'nullable|string|max:512',
            'facebook_password' => 'nullable|string|max:255',
            'collaborator_id' => 'nullable|integer|exists:users,id',
        ];

        if (auth()->user()->isAdmin()) {
            $rules['created_by'] = 'required|exists:users,id';
        }

        $this->validate($rules);

        $rawFb = trim($this->facebook_identifier);
        $normalizedFb = $rawFb !== '' ? FacebookProfileIdentifier::normalize($rawFb) : null;
        if ($rawFb !== '' && ($normalizedFb === null || mb_strlen($normalizedFb, 'UTF-8') > 191)) {
            $this->addError('facebook_identifier', 'Ingresa un ID o enlace válido de Facebook.');

            return;
        }

        $data = [
            'alias' => $this->alias ?: ($this->facebook_profile_name !== '' ? $this->facebook_profile_name : null),
            'email' => strtolower(trim((string) $this->email)),
            'password' => $this->password, // Encrypted casting will encrypt this on save
            'status' => $this->status,
        ];

        if (auth()->user()->isAdmin()) {
            $data['created_by'] = $this->created_by;
        } elseif (! $this->emailId) {
            $data['created_by'] = auth()->id();
        }

        if ($this->emailId) {
            $account = EmailAccount::find($this->emailId);
            if (! $this->canManageEmail($account)) {
                abort(403);
            }
            $account->update($data);
            $statusMsg = 'Cuenta actualizada correctamente.';
        } else {
            $account = EmailAccount::create($data);
            $statusMsg = 'Cuenta creada correctamente.';
        }

        $hasProfileOrCollaboratorInput = trim($this->facebook_profile_name) !== ''
            || $rawFb !== ''
            || trim($this->facebook_password) !== ''
            || $this->collaborator_id !== '';

        if ($hasProfileOrCollaboratorInput) {
            app(BatchAccountProfileImporter::class)->syncEmailProfileAndCollaborators(
                $account,
                auth()->user(),
                $this->collaborator_id !== '' ? [(int) $this->collaborator_id] : [],
                [
                    'profile_name' => $this->facebook_profile_name,
                    'facebook_identifier' => $rawFb,
                    'facebook_password' => $this->facebook_password,
                    'status' => $this->status,
                    'created_by' => (int) ($account->created_by ?: auth()->id()),
                ]
            );
        }

        session()->flash('status', $statusMsg);
        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $account = EmailAccount::findOrFail($id);
        if (! $this->canManageEmail($account)) {
            abort(403);
        }
        $account->status = $account->status === 'active' ? 'suspended' : 'active';
        $account->save();
    }

    public function revealPassword($id)
    {
        $account = EmailAccount::findOrFail($id);
        if (! $this->canManageEmail($account)) {
            abort(403);
        }
        $this->revealedPassword = $account->password; // Decrypted string
        $this->showPasswordModal = true;
    }

    public $showProfilesModal = false;

    public $selectedAccountForProfiles = null;

    public function viewProfiles($id)
    {
        $account = EmailAccount::with('profiles.users')->findOrFail($id);
        if (! $this->canViewEmail($account)) {
            abort(403);
        }
        if (auth()->user()->isUser()) {
            $account->setRelation('profiles', $account->profiles()
                ->with('users')
                ->whereHas('users', fn ($query) => $query->where('users.id', auth()->id()))
                ->get());
        }
        $this->selectedAccountForProfiles = $account;
        $this->showProfilesModal = true;
    }

    public $statusFilter = '';

    public $gestorFilter = '';

    public $profilesFilter = '';

    public $filterDateFrom = '';

    public $filterDateTo = '';

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingGestorFilter()
    {
        $this->resetPage();
    }

    public function updatingProfilesFilter()
    {
        $this->resetPage();
    }

    public function updatingFilterDateFrom()
    {
        $this->resetPage();
    }

    public function updatingFilterDateTo()
    {
        $this->resetPage();
    }

    public function buildQuery()
    {
        $query = EmailAccount::with(['creator', 'profiles.users']);

        if (auth()->user()->isUser()) {
            $query->with(['profiles' => fn ($profileQuery) => $profileQuery
                ->with('users')
                ->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()))]);
            $query->whereHas('profiles.users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()));
        } elseif (auth()->user()->isCuentas()) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
            $query->where(function ($q) use ($monitoredIds) {
                $q->where('created_by', auth()->id())
                    ->orWhereIn('created_by', $monitoredIds)
                    ->orWhereHas('profiles.users', fn ($userQuery) => $userQuery->whereIn('users.id', $monitoredIds))
                    ->orWhere(function ($unassignedQuery) {
                        $unassignedQuery->where(function ($ownerQuery) {
                            $ownerQuery->whereNull('created_by')
                                ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
                        })->whereDoesntHave('profiles.users');
                    });
            });
        } elseif ($this->gestorFilter) {
            $query->where('created_by', $this->gestorFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->profilesFilter === 'with') {
            $query->has('profiles');
        } elseif ($this->profilesFilter === 'without') {
            $query->doesntHave('profiles');
        }

        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', '%'.$this->search.'%')
                    ->orWhere('alias', 'like', '%'.$this->search.'%');
            });
        }

        return $query->latest();
    }

    public function exportCsv()
    {
        $this->authorizeExport();
        $accounts = $this->buildQuery()->get();

        return response()->streamDownload(function () use ($accounts) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($file, ['ID', 'Alias', 'Email', 'Estado', 'Gestor Asignado', 'Perfiles Vinculados', 'Fecha de Creación']);

            foreach ($accounts as $a) {
                fputcsv($file, [
                    $a->id,
                    $a->alias ?? 'N/A',
                    $a->email,
                    $a->status === 'active' ? 'Activa' : 'Suspendida',
                    $a->creator ? $a->creator->name : 'N/A',
                    $a->profiles->count(),
                    $a->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, 'reporte_cuentas_correo_'.date('Y-m-d').'.csv');
    }

    public function exportPdf()
    {
        $this->authorizeExport();
        $accounts = $this->buildQuery()->get();

        $filters = [
            'search' => $this->search,
            'status' => $this->statusFilter === 'active' ? 'Activas' : ($this->statusFilter === 'suspended' ? 'Suspendidas' : 'Todas'),
            'profiles' => $this->profilesFilter === 'with' ? 'Con perfiles' : ($this->profilesFilter === 'without' ? 'Sin perfiles' : 'Todos'),
            'filterDateFrom' => $this->filterDateFrom,
            'filterDateTo' => $this->filterDateTo,
        ];

        if ($this->gestorFilter) {
            $gestor = User::find($this->gestorFilter);
            $filters['gestor'] = $gestor ? $gestor->name : null;
        }

        $pdf = Pdf::loadView('pdf.emails', [
            'accounts' => $accounts,
            'filters' => $filters,
            'generator' => auth()->user(),
        ])->setPaper('letter', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'reporte_cuentas_correo_'.date('Y-m-d').'.pdf');
    }

    private function authorizeExport(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);
    }

    public function render()
    {
        $collaboratorsList = auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas()
            ? User::query()
                ->where('is_active', true)
                ->whereIn('role', ['user', 'cuentas'])
                ->when(auth()->user()->isSupervisor(), function ($query) {
                    $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
                    $query->where(function ($subQuery) use ($monitoredIds) {
                        $subQuery->whereIn('id', $monitoredIds)
                            ->orWhere(fn ($unassigned) => $unassigned->where('role', 'user')->whereNull('supervisor_id'));
                    });
                })
                ->orderBy('name')
                ->get()
            : collect();

        $availableFacebookProfiles = auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas()
            ? Profile::query()
                ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                ->when(auth()->user()->isSupervisor(), function ($query) {
                    $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
                    $query->where(function ($q) use ($monitoredIds) {
                        $q->where('created_by', auth()->id())
                            ->orWhereIn('created_by', $monitoredIds)
                            ->orWhereHas('users', fn ($uq) => $uq->whereIn('users.id', $monitoredIds))
                            ->orWhereDoesntHave('users');
                    });
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email_account_id', 'facebook_identifier'])
            : collect();

        return view('livewire.admin.emails.index', [
            'accounts' => $this->buildQuery()->paginate(15),
            'cuentasUsers' => auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas() ? User::whereIn('role', ['admin', 'supervisor', 'cuentas'])->where('is_active', true)->orderBy('name')->get() : [],
            'collaboratorsList' => $collaboratorsList,
            'availableFacebookProfiles' => $availableFacebookProfiles,
        ])->layout('layouts.app');
    }
}
