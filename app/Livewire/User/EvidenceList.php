<?php

namespace App\Livewire\User;

use App\Models\EvidenceType;
use Livewire\Component;
use Livewire\WithPagination;

class EvidenceList extends Component
{
    use WithPagination;

    public $previewImage = null;

    public $filterType = '';

    public $filterDateFrom = '';

    public $filterDateTo = '';

    public function updating($field)
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['filterType', 'filterDateFrom', 'filterDateTo']);
        $this->resetPage();
    }

    public function showImage($url)
    {
        $this->previewImage = $url;
    }

    public function closeImage()
    {
        $this->previewImage = null;
    }

    public function render()
    {
        $query = auth()->user()->evidences()->with(['evidenceType', 'images', 'profile'])->latest();

        if ($this->filterType) {
            $query->where('evidence_type_id', $this->filterType);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        return view('livewire.user.evidence-list', [
            'evidences' => $query->paginate(10),
            'types' => EvidenceType::all(),
        ])->layout('layouts.app');
    }
}
