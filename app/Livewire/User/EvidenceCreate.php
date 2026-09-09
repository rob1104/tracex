<?php

namespace App\Livewire\User;

use App\Models\Evidence;
use App\Models\EvidenceImage;
use App\Models\EvidenceType;
use App\Models\Profile;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EvidenceCreate extends Component
{
    use WithFileUploads;

    public $evidence_type_id = '';

    public $profile_id = ''; // Profile selection

    public $social_network = 'Facebook'; // Fallback if no profile is selected or for legacy

    public $comment = '';

    #[Validate(['images.*' => 'image|max:5120'])]
    public $images = [];

    public function updatedProfileId($value)
    {
        if ($value) {
            $profile = Profile::find($value);
            if ($profile) {
                $this->social_network = $profile->social_network;
            }
        }
    }

    public function render()
    {
        return view('livewire.user.evidence-create', [
            'types' => EvidenceType::where('is_active', true)->get(),
            'profiles' => auth()->user()->assignedProfiles()->where('status', 'active')->get(),
        ])->layout('layouts.app');
    }

    public function save()
    {
        // If a profile is selected, ensure we override the social_network just in case
        if ($this->profile_id) {
            $profile = Profile::find($this->profile_id);
            if ($profile) {
                $this->social_network = $profile->social_network;
            }
        }

        $this->validate([
            'evidence_type_id' => 'required|exists:evidence_types,id',
            'profile_id' => 'nullable|exists:profiles,id',
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
            'profile_id' => $this->profile_id ?: null,
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

        return $this->redirect('/evidencias/historial', navigate: true);
    }
}
