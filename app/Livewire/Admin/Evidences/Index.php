<?php

namespace App\Livewire\Admin\Evidences;

use App\Models\Evidence;
use App\Models\EvidenceType;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $searchUser = '';

    public $filterType = '';

    public $filterDateFrom = '';

    public $filterDateTo = '';

    public $filterSuspect = false;
    
    public $previewImage = null;

    public function showImage($url)
    {
        $this->previewImage = $url;
    }

    public function closeImage()
    {
        $this->previewImage = null;
    }

    public function updating($field)
    {
        $this->resetPage();
    }

    public function delete($id)
    {
        $evidence = Evidence::find($id);
        if ($evidence) {
            $evidence->delete();
            session()->flash('status', 'Evidencia eliminada correctamente.');
        }
    }

    private function buildQuery()
    {
        $query = Evidence::with(['user', 'evidenceType', 'images'])->latest();

        if ($this->searchUser) {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', '%'.$this->searchUser.'%')
                    ->orWhere('email', 'like', '%'.$this->searchUser.'%');
            });
        }

        if ($this->filterType) {
            $query->where('evidence_type_id', $this->filterType);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        if ($this->filterSuspect) {
            $query->whereHas('images', function ($q) {
                $q->where('is_suspect', true);
            });
        }

        return $query;
    }

    public function exportCsv()
    {
        $evidences = $this->buildQuery()->get();

        return response()->streamDownload(function () use ($evidences) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($file, ['ID', 'Usuario', 'Correo', 'Red Social', 'Tipo de Evidencia', 'Comentario', 'Fecha']);
            
            foreach ($evidences as $e) {
                fputcsv($file, [
                    $e->id,
                    $e->user->name,
                    $e->user->email,
                    $e->social_network,
                    optional($e->evidenceType)->name,
                    $e->comment,
                    $e->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, 'reporte_evidencias_' . date('Y-m-d') . '.csv');
    }

    public function exportPdf()
    {
        $evidences = $this->buildQuery()->get();
        
        $filters = [
            'searchUser' => $this->searchUser,
            'filterType' => $this->filterType ? EvidenceType::find($this->filterType)?->name : null,
            'filterDateFrom' => $this->filterDateFrom,
            'filterDateTo' => $this->filterDateTo,
            'filterSuspect' => $this->filterSuspect,
        ];
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.evidences', [
            'evidences' => $evidences,
            'filters' => $filters,
            'generator' => auth()->user()
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'reporte_evidencias_' . date('Y-m-d') . '.pdf');
    }

    public function render()
    {
        return view('livewire.admin.evidences.index', [
            'evidences' => $this->buildQuery()->paginate(15),
            'types' => EvidenceType::all(),
        ])->layout('layouts.app');
    }
}
