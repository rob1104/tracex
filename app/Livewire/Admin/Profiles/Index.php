<?php

namespace App\Livewire\Admin\Profiles;

use App\Models\EmailAccount;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\FacebookProfileIdentifier;
use App\Services\ProfileAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

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

    public $filter_email_account_id = '';

    public $filter_status = '';

    public $filter_gestor = '';

    public $filter_network = '';

    public $filterDateFrom = '';

    public $filterDateTo = '';

    // For Assignment Modal
    public $assignProfile = null;

    public $assignedUsers = [];

    // For Mass Assignment Modal
    public $showMassAssignModal = false;

    public $sourceColabId = '';

    public $destinationColabId = '';

    public $sourceProfiles = [];

    public $selectedProfiles = [];

    // For Colab Profiles View Modal
    public $showColabProfilesModal = false;

    public $selectedColabViewId = '';

    public $viewingColabProfiles = [];

    public $showClaimImportModal = false;

    public $claimImportId = null;

    public $claimProfileId = '';

    public $claimUserId = '';

    public function updatedSelectedColabViewId($value)
    {
        if ($value) {
            $query = Profile::whereHas('users', function ($q) use ($value) {
                $q->where('users.id', $value);
            })->with('emailAccount')->orderBy('name', 'asc');

            if (auth()->user()->isCuentas()) {
                $query->where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()));
                });
            }

            $this->viewingColabProfiles = $query->get();
        } else {
            $this->viewingColabProfiles = [];
        }
    }

    public function openColabProfilesModal()
    {
        $this->reset(['selectedColabViewId', 'viewingColabProfiles']);
        $this->showColabProfilesModal = true;
    }

    public function updatedSourceColabId($value)
    {
        if ($value) {
            $query = Profile::whereHas('users', function ($q) use ($value) {
                $q->where('users.id', $value);
            })->orderBy('name', 'asc');

            if (auth()->user()->isCuentas()) {
                $query->where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()));
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
        $this->reset(['sourceColabId', 'destinationColabId', 'sourceProfiles', 'selectedProfiles']);
        $this->showMassAssignModal = true;
    }

    public function executeMassAssignment()
    {
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
                if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id() && ! $profile->users()->where('users.id', auth()->id())->exists()) {
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
        $this->reset(['profileId', 'email_account_id', 'name', 'facebook_identifier', 'password', 'social_network', 'status']);
        $this->created_by = auth()->user()->isAdmin() ? '' : auth()->id();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $profile = Profile::findOrFail($id);

        if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id() && ! $profile->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }

        $this->profileId = $profile->id;
        $this->email_account_id = $profile->email_account_id;
        $this->name = $profile->name;
        $this->facebook_identifier = $profile->facebook_identifier;
        $this->password = $profile->password;
        $this->social_network = $profile->social_network;
        $this->status = $profile->status;
        $this->created_by = $profile->created_by;
        $this->showModal = true;
    }

    public function save()
    {
        $rules = [
            'email_account_id' => 'required|exists:email_accounts,id',
            'name' => 'required|string|max:255',
            'facebook_identifier' => 'nullable|string|max:512',
            'password' => 'nullable|string|max:255',
            'social_network' => 'required|string|max:255',
            'status' => 'required|in:active,suspended,restricted',
        ];

        if (auth()->user()->isAdmin()) {
            $rules['created_by'] = 'required|exists:users,id';
        }

        $this->validate($rules);

        $rawFacebookIdentifier = trim((string) $this->facebook_identifier);
        $facebookIdentifier = FacebookProfileIdentifier::normalize($rawFacebookIdentifier);

        if ($rawFacebookIdentifier !== '' && strtolower($this->social_network) !== 'facebook') {
            $this->addError('facebook_identifier', 'Este identificador solo se usa en perfiles de Facebook.');

            return;
        }

        if ($rawFacebookIdentifier !== '' && ($facebookIdentifier === null || mb_strlen($facebookIdentifier) > 191)) {
            $this->addError('facebook_identifier', 'Ingresa un ID numerico o una URL valida de Facebook.');

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

        if ($this->profileId) {
            $profile = Profile::find($this->profileId);
            if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id() && ! $profile->users()->where('users.id', auth()->id())->exists()) {
                abort(403);
            }
            $profile->update($data);
            session()->flash('status', 'Perfil actualizado correctamente.');
        } else {
            $newProfile = Profile::create($data);
            if (auth()->user()->isCuentas()) {
                $newProfile->users()->syncWithoutDetaching([auth()->id()]);
            }
            session()->flash('status', 'Perfil creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $profile = Profile::findOrFail($id);
        if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id() && ! $profile->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }
        $profile->status = $profile->status === 'active' ? 'suspended' : 'active';
        $profile->save();
    }

    public function revealPassword($id)
    {
        $profile = Profile::findOrFail($id);
        if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id() && ! $profile->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }
        $this->revealedPassword = $profile->password;
        $this->showPasswordModal = true;
    }

    // Assignment logic
    public function openAssignModal($id)
    {
        $this->assignProfile = Profile::findOrFail($id);
        if (auth()->user()->isCuentas() && $this->assignProfile->created_by !== auth()->id() && ! $this->assignProfile->users()->where('users.id', auth()->id())->exists()) {
            abort(403);
        }
        $this->assignedUsers = $this->assignProfile->users()->pluck('users.id')->toArray();
        $this->showAssignModal = true;
    }

    public function saveAssignments()
    {
        if ($this->assignProfile) {
            if (auth()->user()->isCuentas() && $this->assignProfile->created_by !== auth()->id() && ! $this->assignProfile->users()->where('users.id', auth()->id())->exists()) {
                abort(403);
            }
            $this->assignProfile->users()->sync($this->assignedUsers);
            session()->flash('status', 'Asignaciones guardadas correctamente para: '.$this->assignProfile->name);
        }
        $this->showAssignModal = false;
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function updatingFilterGestor()
    {
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

    private function authorizeImportManagement(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isCuentas(), 403);
    }

    private function facebookProfilesQuery()
    {
        $query = Profile::query()
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->orderBy('name');

        if (auth()->user()->isCuentas()) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()));
            });
        }

        return $query;
    }

    private function pendingImportsQuery()
    {
        $query = FacebookImportLog::pendingAssignment();

        if (auth()->user()->isCuentas()) {
            $query->whereHas('profile', function ($profileQuery) {
                $profileQuery->where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()));
                });
            });
        }

        return $query;
    }

    public function buildQuery()
    {
        $query = Profile::with(['emailAccount', 'users', 'creator']);

        if (auth()->user()->isCuentas()) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()));
            });
        } elseif ($this->filter_gestor) {
            $query->where('created_by', $this->filter_gestor);
        }

        if ($this->filter_email_account_id) {
            $query->where('email_account_id', $this->filter_email_account_id);
        }

        if ($this->filter_status) {
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

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('social_network', 'like', '%'.$this->search.'%')
                    ->orWhere('facebook_identifier', 'like', '%'.$this->search.'%');
            });
        }

        return $query->latest();
    }

    public function exportCsv()
    {
        $profiles = $this->buildQuery()->get();

        return response()->streamDownload(function () use ($profiles) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($file, ['ID', 'Nombre', 'Red Social', 'Cuenta de Correo', 'Gestor Asignado', 'Colaboradores Asignados', 'Estatus', 'Fecha de Creación']);

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
        $profiles = $this->buildQuery()->get();

        $filters = [
            'search' => $this->search,
            'status' => $this->filter_status ?: 'Todos',
            'network' => $this->filter_network ?: 'Todas',
            'filterDateFrom' => $this->filterDateFrom,
            'filterDateTo' => $this->filterDateTo,
        ];

        if ($this->filter_gestor) {
            $gestor = User::find($this->filter_gestor);
            $filters['gestor'] = $gestor ? $gestor->name : null;
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

    public function render()
    {
        $emailQuery = EmailAccount::query();

        if (auth()->user()->isCuentas()) {
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
        if (auth()->user()->isCuentas()) {
            $networksQuery->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($uq) => $uq->where('users.id', auth()->id()));
            });
        }

        $pendingImportsQuery = $this->pendingImportsQuery()
            ->with(['profile', 'user', 'facebookUsers'])
            ->withCount(['comments', 'reactions'])
            ->latest('downloaded_at');

        return view('livewire.admin.profiles.index', [
            'profiles' => $this->buildQuery()->paginate(15),
            'pendingImports' => $pendingImportsQuery->paginate(10, pageName: 'pendingImportsPage'),
            'facebookProfiles' => $this->facebookProfilesQuery()->get(),
            'emails' => $emailQuery->orderBy('email')->get(),
            'networks' => $networksQuery->orderBy('social_network')->pluck('social_network'),
            'cuentasUsers' => auth()->user()->isAdmin() || auth()->user()->isCuentas() ? User::whereIn('role', ['admin', 'cuentas'])->where('is_active', true)->orderBy('name')->get() : [],
            'usersList' => User::where('is_active', true)->whereIn('role', ['user', 'cuentas'])->orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
