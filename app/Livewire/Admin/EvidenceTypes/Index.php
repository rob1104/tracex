<?php

namespace App\Livewire\Admin\EvidenceTypes;

use App\Models\EvidenceType;
use Livewire\Component;

class Index extends Component
{
    public $showModal = false;

    public $typeId = null;

    public $name = '';

    public $description = '';

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

    public function render()
    {
        return view('livewire.admin.evidence-types.index', [
            'types' => EvidenceType::all(),
        ])->layout('layouts.app');
    }
}
