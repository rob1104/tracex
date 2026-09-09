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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function create()
    {
        $this->reset(['emailId', 'alias', 'email', 'password', 'status']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $account = EmailAccount::findOrFail($id);
        $this->emailId = $account->id;
        $this->alias = $account->alias;
        $this->email = $account->email;
        $this->password = $account->password; // Will be decrypted automatically thanks to casting
        $this->status = $account->status;
        $this->showModal = true;
    }

    public function save()
    {
        // Validation handles unique email depending on create/update manually here
        $this->validate([
            'alias' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:email_accounts,email,'.$this->emailId,
            'password' => 'required|string|max:255',
            'status' => 'required|in:active,suspended',
        ]);

        $data = [
            'alias' => $this->alias,
            'email' => $this->email,
            'password' => $this->password, // Encrypted casting will encrypt this on save
            'status' => $this->status,
        ];

        if ($this->emailId) {
            EmailAccount::find($this->emailId)->update($data);
            session()->flash('status', 'Cuenta actualizada correctamente.');
        } else {
            $data['created_by'] = auth()->id();
            EmailAccount::create($data);
            session()->flash('status', 'Cuenta creada correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleStatus($id)
    {
        $account = EmailAccount::findOrFail($id);
        $account->status = $account->status === 'active' ? 'suspended' : 'active';
        $account->save();
    }

    public function revealPassword($id)
    {
        $account = EmailAccount::findOrFail($id);
        $this->revealedPassword = $account->password; // Decrypted string
        $this->showPasswordModal = true;
    }

    public function render()
    {
        $query = EmailAccount::query();

        if ($this->search) {
            $query->where('email', 'like', '%'.$this->search.'%')
                ->orWhere('alias', 'like', '%'.$this->search.'%');
        }

        return view('livewire.admin.emails.index', [
            'accounts' => $query->latest()->paginate(15),
        ])->layout('layouts.app');
    }
}
