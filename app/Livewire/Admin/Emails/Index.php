<?php

namespace App\Livewire\Admin\Emails;

use App\Models\EmailAccount;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
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
        $this->reset(['sourceGestorId', 'destinationGestorId', 'sourceAccounts', 'selectedAccounts']);
        $this->showMassAssignModal = true;
    }

    public function executeMassAssignment()
    {
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
        \App\Models\Profile::whereIn('email_account_id', $this->selectedAccounts)->update(['created_by' => $this->destinationGestorId]);

        $this->showMassAssignModal = false;
        session()->flash('status', 'Cuentas y sus perfiles asociados han sido reasignados correctamente.');
    }

    public function create()
    {
        $this->reset(['emailId', 'alias', 'email', 'password', 'status']);
        $this->created_by = auth()->user()->isAdmin() ? '' : auth()->id();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $account = EmailAccount::findOrFail($id);
        
        // Cuentas user can only edit their own
        if (auth()->user()->isCuentas() && $account->created_by !== auth()->id()) {
            abort(403);
        }

        $this->emailId = $account->id;
        $this->alias = $account->alias;
        $this->email = $account->email;
        $this->password = $account->password; // Will be decrypted automatically thanks to casting
        $this->status = $account->status;
        $this->created_by = $account->created_by;
        $this->showModal = true;
    }

    public function save()
    {
        $rules = [
            'alias' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:email_accounts,email,'.$this->emailId,
            'password' => 'required|string|max:255',
            'status' => 'required|in:active,suspended',
        ];

        if (auth()->user()->isAdmin()) {
            $rules['created_by'] = 'required|exists:users,id';
        }

        $this->validate($rules);

        $data = [
            'alias' => $this->alias,
            'email' => $this->email,
            'password' => $this->password, // Encrypted casting will encrypt this on save
            'status' => $this->status,
        ];

        if (auth()->user()->isAdmin()) {
            $data['created_by'] = $this->created_by;
        } else {
            $data['created_by'] = auth()->id();
        }

        if ($this->emailId) {
            $account = EmailAccount::find($this->emailId);
            // extra check
            if (auth()->user()->isCuentas() && $account->created_by !== auth()->id()) abort(403);
            $account->update($data);
            session()->flash('status', 'Cuenta actualizada correctamente.');
        } else {
            EmailAccount::create($data);
            session()->flash('status', 'Cuenta creada correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $account = EmailAccount::findOrFail($id);
        if (auth()->user()->isCuentas() && $account->created_by !== auth()->id()) abort(403);
        $account->status = $account->status === 'active' ? 'suspended' : 'active';
        $account->save();
    }

    public function revealPassword($id)
    {
        $account = EmailAccount::findOrFail($id);
        if (auth()->user()->isCuentas() && $account->created_by !== auth()->id()) abort(403);
        $this->revealedPassword = $account->password; // Decrypted string
        $this->showPasswordModal = true;
    }

    public $showProfilesModal = false;

    public $selectedAccountForProfiles = null;

    public function viewProfiles($id)
    {
        $account = EmailAccount::with('profiles')->findOrFail($id);
        if (auth()->user()->isCuentas() && $account->created_by !== auth()->id()) abort(403);
        $this->selectedAccountForProfiles = $account;
        $this->showProfilesModal = true;
    }

    public $statusFilter = '';
    public $gestorFilter = '';
    public $profilesFilter = '';

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

    public function render()
    {
        $query = EmailAccount::with(['creator', 'profiles']);

        if (auth()->user()->isCuentas()) {
            $query->where('created_by', auth()->id());
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

        if ($this->search) {
            $query->where(function($q) {
                $q->where('email', 'like', '%'.$this->search.'%')
                  ->orWhere('alias', 'like', '%'.$this->search.'%');
            });
        }

        return view('livewire.admin.emails.index', [
            'accounts' => $query->latest()->paginate(15),
            'cuentasUsers' => auth()->user()->isAdmin() ? \App\Models\User::whereIn('role', ['admin', 'cuentas'])->where('is_active', true)->orderBy('name')->get() : [],
        ])->layout('layouts.app');
    }
}
