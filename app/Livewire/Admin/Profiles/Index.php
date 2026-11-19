<?php

namespace App\Livewire\Admin\Profiles;

use App\Models\EmailAccount;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\BatchAccountProfileImporter;
use App\Services\FacebookImportProfileNameResolver;
use App\Services\FacebookProfileIdentifier;
use App\Services\ProfileAssignmentService;
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

    public $showModal = false;

    public $showPasswordModal = false;

    public $showAssignModal = false;

    public $profileId = null;

    #[Validate('required|exists:email_accounts,id')]
    public $email_account_id = '';

    #[Validate('required|string|max:255')]
    public $name = '';

    public $facebook_identifier = '';

    #[Validate('nullable|string|max:255')]
    public $password = '';

    #[Validate('required|string|max:255')]
    public $social_network = '';

    #[Validate('required|in:active,suspended,restricted')]
    public $status = 'active';

    public $revealedPassword = '';

    public $created_by = '';

    public string $collaborator_id = '';

    public $filter_email_account_id = '';

    public $filter_status = '';

    public $filter_user_id = '';

    public $filter_network = '';

    public $filterDateFrom = '';

    public $filterDateTo = '';

    // For Assignment Modal
    public $assignProfile = null;

    public $assignedUsers = [];

    // For Collaborator Profiles View Modal
    public bool $showColabProfilesModal = false;

    public string $selectedColabViewId = '';

    public $viewingColabProfiles = [];

    // For Mass Assignment Modal
    public $showMassAssignModal = false;

    public $sourceColabId = '';

    public $destinationColabId = '';

    public $sourceProfiles = [];

    public $selectedProfiles = [];

    public $showClaimImportModal = false;

    public $claimImportId = null;

    public $claimProfileId = '';

    public $claimUserId = '';

    public bool $showBatchImportModal = false;

    public $batchFile = null;

    public string $batchCollaboratorId = '';

    public bool $batchOverrideCollaborator = false;

    public bool $batchCreateMissingCollaborators = true;

    /** @var array<string, mixed>|null */
    public ?array $batchImportSummary = null;

    public function openColabProfilesModal(): void
    {
        $this->authorizeProfileManagement();
        $this->selectedColabViewId = '';
        $this->viewingColabProfiles = [];
        $this->showColabProfilesModal = true;
    }

    public function updatedSelectedColabViewId($value): void
    {
        $this->authorizeProfileManagement();

        if ($value) {
            $this->viewingColabProfiles = Profile::with('emailAccount')
                ->whereHas('users', fn ($q) => $q->where('users.id', $value))
                ->orderBy('name')
                ->get();
        } else {
            $this->viewingColabProfiles = [];
        }
    }

    public function updatedEmailAccountId($value): void
    {
        if ($value) {
            $account = EmailAccount::with('profiles.users')->find($value);
            if ($account) {
                $existingUserIds = $account->profiles
                    ->flatMap(fn (Profile $p) => $p->users->pluck('id'))
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                if ($existingUserIds !== []) {
                    if ($this->collaborator_id === '') {
                        $this->collaborator_id = (string) $existingUserIds[0];
                    }
                    if ($this->assignedUsers === []) {
                        $this->assignedUsers = $existingUserIds;
                    }
                }
                if ($this->name === '' && filled($account->alias)) {
                    $this->name = (string) $account->alias;
                }
            }
        }
    }

    public function openBatchImportModal(): void
    {
        $this->authorizeProfileManagement();
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
        $this->authorizeProfileManagement();

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
        $this->authorizeProfileManagement();
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

    public function updatedSourceColabId($value)
    {
        $this->authorizeProfileManagement();

        if ($value) {
            $query = Profile::whereHas('users', function ($q) use ($value) {
                $q->where('users.id', $value);
            })->orderBy('name', 'asc');

            if (auth()->user()->isCuentas()) {
                $query->where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()))
                        ->orWhereNull('created_by')
                        ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
                });
            } elseif (auth()->user()->isSupervisor()) {
                $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
                $query->where(function ($q) use ($monitoredIds) {
                    $q->where('created_by', auth()->id())
                        ->orWhereIn('created_by', $monitoredIds)
                        ->orWhereHas('users', fn ($uq) => $uq->whereIn('users.id', $monitoredIds));
                });
            }

            $this->sourceProfiles = $query->get();
            $this->selectedProfiles = $this->sourceProfiles->pluck('id')->toArray();
        } else {
            $this->sourceProfiles = [];
            $this->selectedProfiles = [];
        }
    }

    public function toggleSelectAll()
    {
        if (count($this->selectedProfiles) === count($this->sourceProfiles)) {
            $this->selectedProfiles = [];
        } else {
            $this->selectedProfiles = $this->sourceProfiles->pluck('id')->toArray();
        }
    }

    public function openMassAssignModal()
    {
        $this->authorizeProfileManagement();
        $this->reset(['sourceColabId', 'destinationColabId', 'sourceProfiles', 'selectedProfiles']);
        $this->showMassAssignModal = true;
    }

    public function executeMassAssignment()
    {
        $this->authorizeProfileManagement();

        $this->validate([
            'sourceColabId' => 'required|exists:users,id',
            'destinationColabId' => 'required|exists:users,id|different:sourceColabId',
            'selectedProfiles' => 'required|array|min:1',
            'selectedProfiles.*' => 'exists:profiles,id',
        ], [
            'destinationColabId.different' => 'El colaborador destino no puede ser el mismo que el origen.',
            'selectedProfiles.required' => 'Debes seleccionar al menos un perfil para reasignar.',
        ]);

        foreach ($this->selectedProfiles as $profileId) {
            $profile = Profile::find($profileId);
            if ($profile) {
                if (! $this->canManageProfile($profile)) {
                    continue;
                }
                $profile->users()->detach($this->sourceColabId);
                $profile->users()->syncWithoutDetaching([$this->destinationColabId]);
            }
        }

        $this->showMassAssignModal = false;
        session()->flash('status', 'Perfiles reasignados correctamente entre colaboradores.');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterEmailAccountId()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->authorizeProfileManagement();
        $this->resetValidation();
        $this->reset([
            'profileId',
            'email_account_id',
            'name',
            'facebook_identifier',
            'password',
            'social_network',
            'status',
            'collaborator_id',
            'assignedUsers',
        ]);
        $this->social_network = 'Facebook';
        $this->status = 'active';
        $this->created_by = auth()->user()->isAdmin() ? '' : auth()->id();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $this->assignedUsers = [];
        $profile = Profile::with('users:id')->findOrFail($id);

        if (! $this->canManageProfile($profile)) {
            abort(403);
        }

        $this->profileId = $profile->id;
        $this->email_account_id = $profile->email_account_id;
        $this->name = $profile->name;
        $this->facebook_identifier = $profile->facebook_identifier;
        try {
            $this->password = $profile->password;
        } catch (Throwable $e) {
            $this->password = '';
        }
        $this->social_network = $profile->social_network;
        $this->status = $profile->status ?: 'active';
        $this->created_by = $profile->created_by;
        $firstUserId = $profile->users->pluck('id')->first();
        $this->collaborator_id = $firstUserId ? (string) $firstUserId : '';
        $this->showModal = true;
    }

    public function save()
    {
        $this->authorizeProfileManagement();

        $rules = [
            'email_account_id' => 'required|exists:email_accounts,id',
            'name' => 'required|string|max:255',
            'facebook_identifier' => 'nullable|string|max:512',
            'password' => 'nullable|string|max:255',
            'social_network' => 'required|string|max:255',
            'status' => 'required|in:active,suspended,restricted',
            'collaborator_id' => 'nullable|integer|exists:users,id',
            'assignedUsers' => 'array',
            'assignedUsers.*' => 'integer|distinct',
        ];

        if (auth()->user()->isAdmin()) {
            $rules['created_by'] = 'required|exists:users,id';
        }

        $this->validate($rules);

        $selectedUserIds = collect($this->assignedUsers)
            ->when($this->collaborator_id !== '', fn ($c) => $c->push((int) $this->collaborator_id))
            ->map(fn ($userId): int => (int) $userId)
            ->unique()
            ->values();

        if ($selectedUserIds->isNotEmpty()) {
            $eligibleUsersQuery = User::query()
                ->whereIn('id', $selectedUserIds)
                ->where('is_active', true)
                ->whereIn('role', ['user', 'cuentas']);

            if (auth()->user()->isSupervisor()) {
                $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
                $eligibleUsersQuery->where(function ($subQuery) use ($monitoredIds) {
                    $subQuery->whereIn('id', $monitoredIds)
                        ->orWhere(fn ($unassigned) => $unassigned->where('role', 'user')->whereNull('supervisor_id'));
                });
            }

            if ($eligibleUsersQuery->count() !== $selectedUserIds->count()) {
                $this->addError('assignedUsers', 'Selecciona colaboradores activos disponibles.');

                return;
            }
        }

        $rawFacebookIdentifier = trim((string) $this->facebook_identifier);
        $facebookIdentifier = FacebookProfileIdentifier::normalize($rawFacebookIdentifier);

        if ($rawFacebookIdentifier !== '' && strtolower($this->social_network) !== 'facebook') {
            $this->addError('facebook_identifier', 'Este identificador solo se usa en perfiles de Facebook.');

            return;
        }

        if ($rawFacebookIdentifier !== '' && ($facebookIdentifier === null || mb_strlen($facebookIdentifier) > 191)) {
            $this->addError('facebook_identifier', 'Ingresa un ID o una URL valida de Facebook.');

            return;
        }

        $data = [
            'email_account_id' => $this->email_account_id,
            'name' => $this->name,
            'facebook_identifier' => strtolower($this->social_network) === 'facebook' ? $facebookIdentifier : null,
            'password' => $this->password,
            'social_network' => $this->social_network,
            'status' => $this->status,
        ];

        if (auth()->user()->isAdmin()) {
            $data['created_by'] = $this->created_by;
        } elseif (! $this->profileId) {
            $data['created_by'] = auth()->id();
        }

        $collaboratorIdsToAttach = $selectedUserIds->all();
        if ($collaboratorIdsToAttach !== [] && auth()->user()->isSupervisor()) {
            User::query()
                ->whereIn('id', $collaboratorIdsToAttach)
                ->where('role', 'user')
                ->whereNull('supervisor_id')
                ->update(['supervisor_id' => auth()->id()]);
        } elseif ($collaboratorIdsToAttach === []) {
            $emailAccount = EmailAccount::with('profiles.users')->find($this->email_account_id);
            if ($emailAccount) {
                $collaboratorIdsToAttach = $emailAccount->profiles
                    ->flatMap(fn (Profile $p) => $p->users->pluck('id'))
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if ($this->profileId) {
            $profile = Profile::find($this->profileId);
            if (! $this->canManageProfile($profile)) {
                abort(403);
            }
            $profile->update($data);
            $targetProfile = $profile;
            if ($collaboratorIdsToAttach !== []) {
                $targetProfile->users()->syncWithoutDetaching($collaboratorIdsToAttach);
            }
            session()->flash('status', 'Perfil actualizado correctamente.');
        } else {
            $targetProfile = Profile::create($data);
            if (auth()->user()->isCuentas()) {
                $collaboratorIdsToAttach[] = auth()->id();
            }
            $targetProfile->users()->sync(array_unique($collaboratorIdsToAttach));
            session()->flash('status', 'Perfil creado correctamente.');
        }

        if ($collaboratorIdsToAttach !== []) {
            Profile::query()
                ->where('email_account_id', $this->email_account_id)
                ->whereKeyNot($targetProfile->id)
                ->get()
                ->each(fn (Profile $sibling) => $sibling->users()->syncWithoutDetaching($collaboratorIdsToAttach));
        }

        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $profile = Profile::findOrFail($id);
        $isAssignedCollaborator = auth()->user()->isUser()
            && $profile->users()->where('users.id', auth()->id())->exists();

        if (! $this->canManageProfile($profile) && ! $isAssignedCollaborator) {
            abort(403);
        }
        $profile->status = $profile->status === 'active' ? 'suspended' : 'active';
        $profile->save();
    }

    public function revealPassword($id)
    {
        $profile = Profile::findOrFail($id);
        if (! $this->canManageProfile($profile)) {
            abort(403);
        }
        $this->revealedPassword = $profile->password;
        $this->showPasswordModal = true;
    }

    // Assignment logic
    public function openAssignModal($id)
    {
        $this->authorizeProfileManagement();
        $this->assignProfile = Profile::findOrFail($id);
        if (! $this->canManageProfile($this->assignProfile)) {
            abort(403);
        }
        $this->assignedUsers = $this->assignProfile->users()->pluck('users.id')->toArray();
        $this->showAssignModal = true;
    }

    public function saveAssignments()
    {
        $this->authorizeProfileManagement();

        if ($this->assignProfile) {
            if (! $this->canManageProfile($this->assignProfile)) {
                abort(403);
            }

            $cleanIds = collect($this->assignedUsers)
                ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (auth()->user()->isSupervisor() && $cleanIds !== []) {
                User::query()
                    ->where('role', 'user')
                    ->whereNull('supervisor_id')
                    ->whereIn('id', $cleanIds)
                    ->update(['supervisor_id' => auth()->id()]);
            }

            $this->assignProfile->users()->sync($cleanIds);

            if ($this->assignProfile->email_account_id && $cleanIds !== []) {
                Profile::query()
                    ->where('email_account_id', $this->assignProfile->email_account_id)
                    ->whereKeyNot($this->assignProfile->id)
                    ->get()
                    ->each(fn (Profile $sibling) => $sibling->users()->syncWithoutDetaching($cleanIds));
            }

            session()->flash('status', 'Asignaciones guardadas correctamente para: '.$this->assignProfile->name);
        }
        $this->showAssignModal = false;
    }

    public function updatingFilterStatus($value)
    {
        if ($value === 'unassigned') {
            $this->filter_user_id = '';
        }

        $this->resetPage();
    }

    public function updatingFilterUserId($value)
    {
        if ($value !== '' && $this->filter_status === 'unassigned') {
            $this->filter_status = '';
        }

        $this->resetPage();
    }

    public function updatingFilterNetwork()
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

    public function openClaimImportModal($id): void
    {
        $this->authorizeImportManagement();

        $importLog = $this->pendingImportsQuery()->findOrFail($id);
        $this->claimImportId = $importLog->id;
        $this->claimProfileId = $importLog->profile_id && $this->facebookProfilesQuery()->whereKey($importLog->profile_id)->exists()
            ? (string) $importLog->profile_id
            : '';
        $this->claimUserId = $importLog->user_id && User::query()
            ->whereKey($importLog->user_id)
            ->whereIn('role', ['user', 'cuentas'])
            ->where('is_active', true)
            ->exists()
            ? (string) $importLog->user_id
            : '';
        $this->resetValidation();
        $this->showClaimImportModal = true;
    }

    public function claimImport(): void
    {
        $this->authorizeImportManagement();
        $this->validate([
            'claimImportId' => 'required|integer|exists:facebook_import_logs,id',
            'claimProfileId' => 'required|integer|exists:profiles,id',
            'claimUserId' => 'required|integer|exists:users,id',
        ]);

        $importLog = $this->pendingImportsQuery()->findOrFail($this->claimImportId);
        $profile = $this->facebookProfilesQuery()->findOrFail($this->claimProfileId);
        $user = User::query()
            ->whereKey($this->claimUserId)
            ->whereIn('role', ['user', 'cuentas'])
            ->where('is_active', true)
            ->firstOrFail();

        $evidenceCount = app(ProfileAssignmentService::class)->claimPendingImport($importLog, $profile, $user);

        $this->reset(['showClaimImportModal', 'claimImportId', 'claimProfileId', 'claimUserId']);
        $this->resetPage('pendingImportsPage');
        session()->flash('status', "Carga asignada correctamente. Se generaron {$evidenceCount} evidencias.");
    }

    private function canManageProfile(Profile $profile): bool
    {
        if (auth()->user()->isAdmin()) {
            return true;
        }

        if (auth()->user()->isUser()) {
            return false;
        }

        if ($profile->created_by === auth()->id()) {
            return true;
        }

        if (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];

            if (in_array((int) $profile->created_by, $monitoredIds, true)
                || $profile->users()->whereIn('users.id', $monitoredIds)->exists()) {
                return true;
            }

            if (! $profile->users()->exists()) {
                return $profile->created_by === null
                    || (bool) ($profile->creator && $profile->creator->isAdmin());
            }

            return false;
        }

        if ($profile->users()->where('users.id', auth()->id())->exists()) {
            return true;
        }

        if ($profile->created_by === null) {
            return true;
        }

        return (bool) ($profile->creator && $profile->creator->isAdmin());
    }

    private function authorizeProfileManagement(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);
    }

    private function authorizeImportManagement(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);
    }

    private function facebookProfilesQuery()
    {
        $query = Profile::query()
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->orderBy('name');

        if (auth()->user()->isUser()) {
            $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()));
        } elseif (auth()->user()->isCuentas()) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()))
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
            $query->where(function ($q) use ($monitoredIds) {
                $q->where('created_by', auth()->id())
                    ->orWhereIn('created_by', $monitoredIds)
                    ->orWhereHas('users', fn ($uq) => $uq->whereIn('users.id', $monitoredIds));
            });
        }

        return $query;
    }

    private function pendingImportsQuery()
    {
        $query = FacebookImportLog::pendingAssignment();

        if (auth()->user()->isUser()) {
            $query->whereRaw('1 = 0');
        } elseif (auth()->user()->isCuentas()) {
            $query->where(function ($pendingQuery) {
                $pendingQuery->whereNull('profile_id')
                    ->orWhereHas('profile', function ($profileQuery) {
                        $profileQuery->where(function ($q) {
                            $q->where('created_by', auth()->id())
                                ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()))
                                ->orWhereNull('created_by')
                                ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
                        });
                    });
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
            $query->where(function ($pendingQuery) use ($monitoredIds) {
                $pendingQuery->whereNull('profile_id')
                    ->orWhereHas('profile', function ($profileQuery) use ($monitoredIds) {
                        $profileQuery->where(function ($q) use ($monitoredIds) {
                            $q->where('created_by', auth()->id())
                                ->orWhereIn('created_by', $monitoredIds)
                                ->orWhereHas('users', fn ($uq) => $uq->whereIn('users.id', $monitoredIds));
                        });
                    });
            });
        }

        return $query;
    }

    public function buildQuery()
    {
        $query = Profile::with(['emailAccount', 'users', 'creator']);

        if (auth()->user()->isUser()) {
            $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()))
                ->with(['users' => fn ($userQuery) => $userQuery->where('users.id', auth()->id())]);
        } elseif (auth()->user()->isCuentas()) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()))
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
            });
        } elseif (auth()->user()->canMonitorCollaborators()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds();
            if ($monitoredIds !== null) {
                $query->where(function ($q) use ($monitoredIds) {
                    $q->where('created_by', auth()->id())
                        ->orWhereIn('created_by', $monitoredIds)
                        ->orWhereHas('users', fn ($userQuery) => $userQuery->whereIn('users.id', $monitoredIds))
                        ->orWhere(function ($unassignedQuery) {
                            $unassignedQuery->where(function ($ownerQuery) {
                                $ownerQuery->whereNull('created_by')
                                    ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
                            })->whereDoesntHave('users');
                        });
                });
            }
            if ($this->filter_user_id) {
                $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $this->filter_user_id));
            }
        } elseif ($this->filter_user_id) {
            $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $this->filter_user_id));
        }

        if ($this->filter_email_account_id) {
            $query->where('email_account_id', $this->filter_email_account_id);
        }

        if ($this->filter_status === 'unassigned') {
            $query->whereDoesntHave('users');
        } elseif ($this->filter_status) {
            $query->where('status', $this->filter_status);
        }

        if ($this->filter_network) {
            $query->where('social_network', $this->filter_network);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        return $query->latest();
    }

    public function exportCsv()
    {
        $this->authorizeExport();
        $profiles = $this->buildQuery()->get();

        return response()->streamDownload(function () use ($profiles) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($file, ['ID', 'Nombre', 'Red Social', 'Cuenta de Correo', 'Usuario creador', 'Colaboradores asignados', 'Estatus', 'Fecha de creación']);

            foreach ($profiles as $p) {
                fputcsv($file, [
                    $p->id,
                    $p->name,
                    $p->social_network,
                    $p->emailAccount->email ?? 'N/A',
                    $p->creator->name ?? 'N/A',
                    $p->users->pluck('name')->implode(', '),
                    $p->status,
                    $p->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, 'reporte_perfiles_'.date('Y-m-d').'.csv');
    }

    public function exportPdf()
    {
        $this->authorizeExport();
        $profiles = $this->buildQuery()->get();

        $filters = [
            'status' => match ($this->filter_status) {
                'active' => 'Activa',
                'restricted' => 'Restringida',
                'suspended' => 'Suspendida',
                'unassigned' => 'Sin asignar',
                default => 'Todos',
            },
            'network' => $this->filter_network ?: 'Todas',
            'filterDateFrom' => $this->filterDateFrom,
            'filterDateTo' => $this->filterDateTo,
            'user' => 'Todos',
        ];

        if ($this->filter_user_id) {
            $user = User::find($this->filter_user_id);
            $filters['user'] = $user ? $user->name : 'Todos';
        }

        if ($this->filter_email_account_id) {
            $email = EmailAccount::find($this->filter_email_account_id);
            $filters['email'] = $email ? $email->email : null;
        }

        $pdf = Pdf::loadView('pdf.profiles', [
            'profiles' => $profiles,
            'filters' => $filters,
            'generator' => auth()->user(),
        ])->setPaper('letter', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'reporte_perfiles_'.date('Y-m-d').'.pdf');
    }

    private function authorizeExport(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);
    }

    public function render()
    {
        $emailQuery = EmailAccount::query();

        if (auth()->user()->isUser()) {
            $emailQuery->whereHas('profiles.users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()));
        } elseif (auth()->user()->isCuentas()) {
            $gestorId = auth()->id();
            $emailQuery->where(function ($q) use ($gestorId) {
                $q->where('created_by', $gestorId)
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', function ($cq) {
                        $cq->where('role', 'admin');
                    });
            });

            if ((clone $emailQuery)->where('status', 'active')->count() === 0) {
                $emailQuery = EmailAccount::query();
            }
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
            $emailQuery->where(function ($q) use ($monitoredIds) {
                $q->where('created_by', auth()->id())
                    ->orWhereIn('created_by', $monitoredIds)
                    ->orWhereHas('profiles.users', fn ($uq) => $uq->whereIn('users.id', $monitoredIds))
                    ->orWhere(function ($unassignedQuery) {
                        $unassignedQuery->where(function ($ownerQuery) {
                            $ownerQuery->whereNull('created_by')
                                ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
                        })->whereDoesntHave('profiles.users');
                    });
            });

            if ((clone $emailQuery)->where('status', 'active')->count() === 0) {
                $emailQuery = EmailAccount::query()->whereDoesntHave('profiles.users');
            }
        }

        if ($this->email_account_id) {
            $emailQuery->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere('id', $this->email_account_id);
            });
        } else {
            $emailQuery->where('status', 'active');
        }

        // Available networks for filter
        $networksQuery = Profile::select('social_network')->distinct();
        if (auth()->user()->isUser()) {
            $networksQuery->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()));
        } elseif (auth()->user()->isCuentas()) {
            $networksQuery->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()))
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
            $networksQuery->where(function ($q) use ($monitoredIds) {
                $q->where('created_by', auth()->id())
                    ->orWhereIn('created_by', $monitoredIds)
                    ->orWhereHas('users', fn ($uq) => $uq->whereIn('users.id', $monitoredIds));
            });
        }

        $pendingImportsQuery = $this->pendingImportsQuery()
            ->with(['profile', 'user', 'facebookUsers'])
            ->withCount(['comments', 'reactions'])
            ->latest('downloaded_at');

        $profilesQuery = $this->buildQuery()
            ->withCount([
                'facebookImportLogs as facebook_exports_count',
                'facebookComments as facebook_comments_count',
                'facebookReactions as facebook_reactions_count',
                'evidences as facebook_shares_count' => fn ($query) => $query
                    ->whereIn('origin', ['google_drive', 'local_upload'])
                    ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                    ->whereHas('evidenceType', fn ($typeQuery) => $typeQuery->where('name', 'Compartido')),
            ])
            ->withMax('facebookImportLogs as latest_facebook_export_at', 'downloaded_at');

        $profiles = $profilesQuery->paginate(15);
        $assignedUserIds = $profiles->getCollection()
            ->flatMap(fn (Profile $profile) => $profile->users->pluck('id'))
            ->unique()
            ->values();

        if (auth()->user()->isUser()) {
            $assignedUserIds = collect([auth()->id()]);
        }

        $facebookProfilesQuery = Profile::query()
            ->with('users:id,name')
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $assignedUserIds));

        if (auth()->user()->isCuentas()) {
            $facebookProfilesQuery->where('created_by', auth()->id());
        }

        $facebookProfilesByUser = [];
        foreach ($facebookProfilesQuery->get() as $facebookProfile) {
            foreach ($facebookProfile->users as $assignedUser) {
                $facebookProfilesByUser[$assignedUser->id][] = [
                    'id' => $facebookProfile->id,
                    'name' => $facebookProfile->name,
                ];
            }
        }

        $usersList = auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas()
            ? User::where('is_active', true)
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

        $pendingImports = $pendingImportsQuery->paginate(10, pageName: 'pendingImportsPage');

        return view('livewire.admin.profiles.index', [
            'profiles' => $profiles,
            'facebookProfilesByUser' => $facebookProfilesByUser,
            'pendingImports' => $pendingImports,
            'detectedImportProfileNames' => app(FacebookImportProfileNameResolver::class)
                ->resolveMany($pendingImports->getCollection()),
            'facebookProfiles' => $this->facebookProfilesQuery()->get(),
            'emails' => $emailQuery->orderBy('email')->get(),
            'networks' => $networksQuery->orderBy('social_network')->pluck('social_network'),
            'cuentasUsers' => auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas() ? User::whereIn('role', ['admin', 'supervisor', 'cuentas'])->where('is_active', true)->orderBy('name')->get() : [],
            'usersList' => $usersList,
        ])->layout('layouts.app');
    }
}
