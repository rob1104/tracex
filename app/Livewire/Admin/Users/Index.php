<?php

namespace App\Livewire\Admin\Users;

use App\Models\EmailAccount;
use App\Models\User;
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

    public $password = '';

    public $showAccountsModal = false;

    public $viewingUserAccounts = [];

    public $viewingUserName = '';

    public function viewAccounts($id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'cuentas') {
            $this->viewingUserName = $user->name;
            $this->viewingUserAccounts = EmailAccount::withCount('profiles')->where('created_by', $user->id)->orderBy('email', 'asc')->get();
            $this->showAccountsModal = true;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->reset(['userId', 'name', 'email', 'role', 'password']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->password = '';
        $this->showModal = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$this->userId,
            'role' => 'required|in:admin,user,cuentas',
        ];

        if (! $this->userId || $this->password) {
            $rules['password'] = 'required|min:6';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            User::find($this->userId)->update($data);
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

    public function buildQuery()
    {
        $query = User::query();

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%');
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

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.users', [
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
        return view('livewire.admin.users.index', [
            'users' => $this->buildQuery()->paginate(15),
        ])->layout('layouts.app');
    }
}
