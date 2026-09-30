<?php

namespace App\Livewire\Admin\Profiles;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
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

    #[Validate('nullable|string|max:255')]
    public $password = '';

    #[Validate('required|string|max:255')]
    public $social_network = '';

    #[Validate('required|in:active,suspended,restricted')]
    public $status = 'active';

    public $revealedPassword = '';

    public $created_by = '';
    
    public $filter_email_account_id = '';

    // For Assignment Modal
    public $assignProfile = null;

    public $assignedUsers = [];

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
        $this->reset(['profileId', 'email_account_id', 'name', 'password', 'social_network', 'status']);
        $this->created_by = auth()->user()->isAdmin() ? '' : auth()->id();
        $this->showModal = true;
    }

    public function edit($id)
    {
        $profile = Profile::findOrFail($id);

        if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id()) abort(403);

        $this->profileId = $profile->id;
        $this->email_account_id = $profile->email_account_id;
        $this->name = $profile->name;
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
            'password' => 'nullable|string|max:255',
            'social_network' => 'required|string|max:255',
            'status' => 'required|in:active,suspended,restricted',
        ];

        if (auth()->user()->isAdmin()) {
            $rules['created_by'] = 'required|exists:users,id';
        }

        $this->validate($rules);

        $data = [
            'email_account_id' => $this->email_account_id,
            'name' => $this->name,
            'password' => $this->password,
            'social_network' => $this->social_network,
            'status' => $this->status,
        ];

        if (auth()->user()->isAdmin()) {
            $data['created_by'] = $this->created_by;
        } else {
            $data['created_by'] = auth()->id();
        }

        if ($this->profileId) {
            $profile = Profile::find($this->profileId);
            if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id()) abort(403);
            $profile->update($data);
            session()->flash('status', 'Perfil actualizado correctamente.');
        } else {
            Profile::create($data);
            session()->flash('status', 'Perfil creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $profile = Profile::findOrFail($id);
        if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id()) abort(403);
        $profile->status = $profile->status === 'active' ? 'suspended' : 'active';
        $profile->save();
    }

    public function revealPassword($id)
    {
        $profile = Profile::findOrFail($id);
        if (auth()->user()->isCuentas() && $profile->created_by !== auth()->id()) abort(403);
        $this->revealedPassword = $profile->password;
        $this->showPasswordModal = true;
    }

    // Assignment logic
    public function openAssignModal($id)
    {
        $this->assignProfile = Profile::findOrFail($id);
        if (auth()->user()->isCuentas() && $this->assignProfile->created_by !== auth()->id()) abort(403);
        $this->assignedUsers = $this->assignProfile->users()->pluck('users.id')->toArray();
        $this->showAssignModal = true;
    }

    public function saveAssignments()
    {
        if ($this->assignProfile) {
            if (auth()->user()->isCuentas() && $this->assignProfile->created_by !== auth()->id()) abort(403);
            $this->assignProfile->users()->sync($this->assignedUsers);
            session()->flash('status', 'Asignaciones guardadas correctamente para: '.$this->assignProfile->name);
        }
        $this->showAssignModal = false;
    }

    public function render()
    {
        $query = Profile::with(['emailAccount', 'users', 'creator']);

        if (auth()->user()->isCuentas()) {
            $query->where('created_by', auth()->id());
        }

        if ($this->filter_email_account_id) {
            $query->where('email_account_id', $this->filter_email_account_id);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('social_network', 'like', '%'.$this->search.'%');
            });
        }
        
        $emailQuery = EmailAccount::where('status', 'active');
        if (auth()->user()->isCuentas()) {
            $emailQuery->where('created_by', auth()->id());
        }

        return view('livewire.admin.profiles.index', [
            'profiles' => $query->latest()->paginate(15),
            'emails' => $emailQuery->orderBy('email')->get(),
            'usersList' => User::where('is_active', true)->where('role', 'user')->orderBy('name')->get(),
            'cuentasUsers' => auth()->user()->isAdmin() ? User::where('role', 'cuentas')->orderBy('name')->get() : [],
        ])->layout('layouts.app');
    }
}
