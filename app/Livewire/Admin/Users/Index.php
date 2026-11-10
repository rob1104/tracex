<?php

namespace App\Livewire\Admin\Users;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $search = '';

    public $showModal = false;

    public $userId = null;

    public $name = '';

    public $email = '';

    public $role = 'user';

    public $supervisorId = '';

    public $password = '';

    public $showAccountsModal = false;

    public $viewingUserAccounts = [];

    public $viewingUserName = '';

    public bool $showCollaboratorsModal = false;

    public ?int $assigningAdminId = null;

    public string $assigningAdminName = '';

    public string $collaboratorSearch = '';

    /** @var array<int, int|string> */
    public array $assignedCollaboratorIds = [];

    public function viewAccounts($id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'cuentas') {
            $this->viewingUserName = $user->name;
            $query = EmailAccount::withCount('profiles')->where('created_by', $user->id);
            if ((clone $query)->count() === 0) {
                $query = EmailAccount::withCount('profiles')->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereNull('created_by')
                        ->orWhereHas('creator', fn ($cq) => $cq->where('role', 'admin'));
                });
            }
            $this->viewingUserAccounts = $query->orderBy('email', 'asc')->get();
            $this->showAccountsModal = true;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->reset(['userId', 'name', 'email', 'role', 'supervisorId', 'password']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->supervisorId = $user->supervisor_id ? (string) $user->supervisor_id : '';
        $this->password = '';
        $this->showModal = true;
    }

    public function openCollaboratorsModal(int $id): void
    {
        $supervisor = User::findOrFail($id);
        abort_unless($supervisor->isSupervisor(), 422);

        $this->assigningAdminId = $supervisor->id;
        $this->assigningAdminName = $supervisor->name;
        $this->collaboratorSearch = '';
        $this->assignedCollaboratorIds = $supervisor->monitoredCollaborators()
            ->pluck('users.id')
            ->map(fn ($collaboratorId): int => (int) $collaboratorId)
            ->all();
        $this->showCollaboratorsModal = true;
    }

    public function saveAdminCollaborators(): void
    {
        abort_if($this->assigningAdminId === null, 422);

        $supervisor = User::findOrFail($this->assigningAdminId);
        abort_unless($supervisor->isSupervisor(), 422);

        $this->validate([
            'assignedCollaboratorIds' => ['array'],
            'assignedCollaboratorIds.*' => ['integer', 'exists:users,id'],
        ]);

        $selectedIds = collect($this->assignedCollaboratorIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        User::query()
            ->where('role', 'user')
            ->where('supervisor_id', $supervisor->id)
            ->when($selectedIds !== [], fn ($query) => $query->whereNotIn('id', $selectedIds))
            ->update(['supervisor_id' => null]);

        if ($selectedIds !== []) {
            User::query()
                ->where('role', 'user')
                ->whereIn('id', $selectedIds)
                ->update(['supervisor_id' => $supervisor->id]);
        }

        $this->showCollaboratorsModal = false;
        session()->flash('status', "Colaboradores asignados correctamente a {$supervisor->name}.");
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$this->userId,
            'role' => 'required|in:admin,supervisor,user,cuentas',
            'supervisorId' => 'nullable|integer|exists:users,id',
        ];

        if (! $this->userId || $this->password) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules);

        $supervisorId = null;
        if ($this->role === 'user' && $this->supervisorId !== '' && $this->supervisorId !== null) {
            $supervisor = User::query()
                ->where('role', 'supervisor')
                ->find((int) $this->supervisorId);

            if (! $supervisor) {
                $this->addError('supervisorId', 'Selecciona un supervisor válido.');

                return;
            }

            $supervisorId = $supervisor->id;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'supervisor_id' => $supervisorId,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            $user = User::findOrFail($this->userId);
            $user->update($data);
            if ($user->role !== 'supervisor') {
                User::query()->where('supervisor_id', $user->id)->update(['supervisor_id' => null]);
            }
            session()->flash('status', 'Usuario actualizado correctamente.');
        } else {
            User::create($data);
            session()->flash('status', 'Usuario creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive($id)
    {
        $user = User::findOrFail($id);
        if ($user->id !== auth()->id()) {
            $user->is_active = ! $user->is_active;
            $user->save();
        }
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return;
        }

        $deletedName = $user->name;

        User::query()->where('supervisor_id', $user->id)->update(['supervisor_id' => null]);
        EmailAccount::query()->where('created_by', $user->id)->update(['created_by' => null]);
        Profile::query()->where('created_by', $user->id)->update(['created_by' => null]);
        $user->assignedProfiles()->detach();

        if ($this->assigningAdminId === $user->id) {
            $this->showCollaboratorsModal = false;
            $this->assigningAdminId = null;
        }

        if ((int) $this->userId === $user->id) {
            $this->showModal = false;
            $this->userId = null;
        }

        $user->delete();

        session()->flash('status', "Usuario {$deletedName} eliminado correctamente.");
    }

    public function buildQuery()
    {
        $query = User::query()
            ->with('supervisor:id,name')
            ->withCount('monitoredCollaborators');

        if ($this->search) {
            $query->where(function ($searchQuery) {
                $searchQuery->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        return $query->latest();
    }

    public function exportCsv()
    {
        $users = $this->buildQuery()->get();

        return response()->streamDownload(function () use ($users) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($file, ['ID', 'Nombre', 'Email', 'Rol', 'Estatus', 'Fecha de Registro']);

            foreach ($users as $u) {
                fputcsv($file, [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->role,
                    $u->is_active ? 'Activo' : 'Inactivo',
                    $u->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, 'reporte_usuarios_'.date('Y-m-d').'.csv');
    }

    public function exportPdf()
    {
        $users = $this->buildQuery()->get();

        $filters = [
            'search' => $this->search ?: 'Todos',
        ];

        $pdf = Pdf::loadView('pdf.users', [
            'users' => $users,
            'filters' => $filters,
            'generator' => auth()->user(),
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'reporte_usuarios_'.date('Y-m-d').'.pdf');
    }

    public function render()
    {
        $collaboratorCandidates = User::query()
            ->with('supervisor:id,name')
            ->where('role', 'user')
            ->when(trim($this->collaboratorSearch) !== '', function ($query): void {
                $term = trim($this->collaboratorSearch);
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('name')
            ->get();

        return view('livewire.admin.users.index', [
            'users' => $this->buildQuery()->paginate(15),
            'supervisors' => User::query()->where('role', 'supervisor')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'collaboratorCandidates' => $collaboratorCandidates,
        ])->layout('layouts.app');
    }
}
