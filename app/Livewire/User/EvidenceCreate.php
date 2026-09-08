<?php

namespace App\Livewire\User;

use App\Models\Evidence;
use App\Models\EvidenceImage;
use App\Models\EvidenceType;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EvidenceCreate extends Component
{
    use WithFileUploads;

    public $evidence_type_id = '';

    public $social_network = 'Facebook';

    public $comment = '';

    #[Validate(['images.*' => 'image|max:5120'])]
    public $images = [];

    public function render()
    {
        return view('livewire.user.evidence-create', [
            'types' => EvidenceType::where('is_active', true)->get(),
        ])->layout('layouts.app');
    }

    public function save()
    {
        $this->validate([
            'evidence_type_id' => 'required|exists:evidence_types,id',
            'social_network' => 'required|string|max:255',
            'images' => 'required|array|min:1',
            'comment' => 'nullable|string',
        ], [
            'evidence_type_id.required' => 'Selecciona un tipo de evidencia.',
            'social_network.required' => 'Selecciona la red social.',
            'images.required' => 'La captura de pantalla es obligatoria.',
            'images.*.image' => 'El archivo seleccionado no es una imagen válida.',
            'images.*.max' => 'La imagen seleccionada supera el tamaño máximo permitido (5MB).',
        ]);

        $evidence = Evidence::create([
            'user_id' => auth()->id(),
            'evidence_type_id' => $this->evidence_type_id,
            'social_network' => $this->social_network,
            'comment' => $this->comment,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        foreach ($this->images as $image) {
            $path = $image->store('evidences', 'public');
            $hash = hash_file('sha256', $image->getRealPath());

            $isSuspect = EvidenceImage::where('screenshot_hash', $hash)->exists();

            EvidenceImage::create([
                'evidence_id' => $evidence->id,
                'screenshot_path' => $path,
                'screenshot_hash' => $hash,
                'screenshot_mime' => $image->getMimeType(),
                'screenshot_size' => $image->getSize(),
                'is_suspect' => $isSuspect,
            ]);
        }

        session()->flash('status', 'Evidencia registrada correctamente.');

        return $this->redirect('/dashboard', navigate: true);
    }
}
