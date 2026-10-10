<?php

namespace App\Livewire\Admin\EvidenceTypes;

use App\Models\EvidenceType;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileAssignmentService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $showModal = false;

    public $typeId = null;

    public $name = '';

    public $description = '';

    public $showImportAssignmentModal = false;

    public $claimImportId = null;

    public $claimProfileId = '';

    public $claimUserId = '';

    public function create()
    {
        $this->reset(['typeId', 'name', 'description']);
        $this->showModal = true;
    }

    public function edit($id)
    {
        $type = EvidenceType::findOrFail($id);
        $this->typeId = $type->id;
        $this->name = $type->name;
        $this->description = $type->description;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:evidence_types,name,'.$this->typeId,
            'description' => 'nullable|string',
        ]);

        $data = [
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ($this->typeId) {
            EvidenceType::find($this->typeId)->update($data);
            session()->flash('status', 'Tipo de evidencia actualizado correctamente.');
        } else {
            EvidenceType::create($data);
            session()->flash('status', 'Tipo de evidencia creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive($id)
    {
        $type = EvidenceType::findOrFail($id);
        $type->is_active = ! $type->is_active;
        $type->save();
    }

    public function openImportAssignmentModal(int $id): void
    {
        $import = FacebookImportLog::pendingAssignment()->findOrFail($id);
        $this->claimImportId = $import->id;
        $this->claimProfileId = $import->profile_id && $this->facebookProfilesQuery()->whereKey($import->profile_id)->exists()
            ? (string) $import->profile_id
            : '';
        $this->claimUserId = '';
        $this->resetValidation();
        $this->showImportAssignmentModal = true;
    }

    public function assignPendingImport(): void
    {
        $this->validate([
            'claimImportId' => 'required|integer|exists:facebook_import_logs,id',
            'claimProfileId' => 'required|integer|exists:profiles,id',
            'claimUserId' => 'required|integer|exists:users,id',
        ]);

        $import = FacebookImportLog::pendingAssignment()->findOrFail($this->claimImportId);
        $profile = $this->facebookProfilesQuery()->findOrFail($this->claimProfileId);
        $user = User::query()
            ->whereKey($this->claimUserId)
            ->where('role', 'user')
            ->where('is_active', true)
            ->firstOrFail();

        $evidenceCount = app(ProfileAssignmentService::class)->claimPendingImport($import, $profile, $user);

        $this->reset(['showImportAssignmentModal', 'claimImportId', 'claimProfileId', 'claimUserId']);
        $this->resetPage('facebookImportsPage');
        session()->flash('status', "Exportación asignada. Se generaron {$evidenceCount} evidencias.");
    }

    private function facebookProfilesQuery()
    {
        return Profile::query()
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->orderBy('name');
    }

    public function render()
    {
        return view('livewire.admin.evidence-types.index', [
            'types' => EvidenceType::all(),
            'pendingImports' => FacebookImportLog::pendingAssignment()
                ->with(['profile', 'facebookUsers'])
                ->withCount(['comments', 'reactions'])
                ->latest('downloaded_at')
                ->paginate(10, pageName: 'facebookImportsPage'),
            'facebookProfiles' => $this->facebookProfilesQuery()->get(),
            'collaborators' => User::query()
                ->where('role', 'user')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app');
    }
}
