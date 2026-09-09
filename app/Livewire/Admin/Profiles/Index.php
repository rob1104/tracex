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

    #[Validate('required|in:active,suspended')]
    public $status = 'active';

    public $revealedPassword = '';

    // For Assignment Modal
    public $assignProfile = null;

    public $assignedUsers = [];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->reset(['profileId', 'email_account_id', 'name', 'password', 'social_network', 'status']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $profile = Profile::findOrFail($id);
        $this->profileId = $profile->id;
        $this->email_account_id = $profile->email_account_id;
        $this->name = $profile->name;
        $this->password = $profile->password;
        $this->social_network = $profile->social_network;
        $this->status = $profile->status;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'email_account_id' => $this->email_account_id,
            'name' => $this->name,
            'password' => $this->password,
            'social_network' => $this->social_network,
            'status' => $this->status,
        ];

        if ($this->profileId) {
            Profile::find($this->profileId)->update($data);
            session()->flash('status', 'Perfil actualizado correctamente.');
        } else {
            $data['created_by'] = auth()->id();
            Profile::create($data);
            session()->flash('status', 'Perfil creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $profile = Profile::findOrFail($id);
        $profile->status = $profile->status === 'active' ? 'suspended' : 'active';
        $profile->save();
    }

    public function revealPassword($id)
    {
        $profile = Profile::findOrFail($id);
        $this->revealedPassword = $profile->password;
        $this->showPasswordModal = true;
    }

    // Assignment logic
    public function openAssignModal($id)
    {
        $this->assignProfile = Profile::findOrFail($id);
        $this->assignedUsers = $this->assignProfile->users()->pluck('users.id')->toArray();
        $this->showAssignModal = true;
    }

    public function saveAssignments()
    {
        if ($this->assignProfile) {
            $this->assignProfile->users()->sync($this->assignedUsers);
            session()->flash('status', 'Asignaciones guardadas correctamente para: '.$this->assignProfile->name);
        }
        $this->showAssignModal = false;
    }

    public function render()
    {
        $query = Profile::with(['emailAccount', 'users']);

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('social_network', 'like', '%'.$this->search.'%');
        }

        return view('livewire.admin.profiles.index', [
            'profiles' => $query->latest()->paginate(15),
            'emails' => EmailAccount::where('status', 'active')->orderBy('email')->get(),
            'usersList' => User::where('is_active', true)->where('role', 'user')->orderBy('name')->get(),
        ])->layout('layouts.app');
    }
}
