<?php

namespace App\Livewire\User;

use App\Models\Evidence;
use App\Models\EvidenceImage;
use App\Models\EvidenceType;
use App\Models\Profile;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EvidenceCreateMultiple extends Component
{
    use WithFileUploads;

    public $evidence_type_id = '';

    public $profile_ids = []; // Multiple Profiles selection

    public $comment = '';

    #[Validate(['images.*' => 'image|max:5120'])]
    public $images = [];

    public function render()
    {
        return view('livewire.user.evidence-create-multiple', [
            'types' => EvidenceType::where('is_active', true)->get(),
            'profiles' => auth()->user()->assignedProfiles()->where('status', 'active')->orderBy('name')->get(),
        ])->layout('layouts.app');
    }

    public function save()
    {
        $this->validate([
            'evidence_type_id' => 'required|exists:evidence_types,id',
            'profile_ids' => 'required|array|min:1',
            'profile_ids.*' => 'exists:profiles,id',
            'images' => 'required|array|min:1',
            'comment' => 'nullable|string',
        ], [
            'evidence_type_id.required' => 'Selecciona un tipo de evidencia.',
            'profile_ids.required' => 'Debes seleccionar al menos un perfil.',
            'profile_ids.min' => 'Debes seleccionar al menos un perfil.',
            'images.required' => 'La captura de pantalla es obligatoria.',
            'images.*.image' => 'El archivo seleccionado no es una imagen válida.',
            'images.*.max' => 'La imagen seleccionada supera el tamaño máximo permitido (5MB).',
        ]);

        $savedImagesData = [];

        // Save the images first, to reuse them for all evidences
        foreach ($this->images as $image) {
            $path = $image->store('evidences', 'public');
            $hash = hash_file('sha256', $image->getRealPath());
            $isSuspect = EvidenceImage::where('screenshot_hash', $hash)->exists();

            $savedImagesData[] = [
                'path' => $path,
                'hash' => $hash,
                'mime' => $image->getMimeType(),
                'size' => $image->getSize(),
                'is_suspect' => $isSuspect,
            ];
        }

        // Now create one evidence record per selected profile
        foreach ($this->profile_ids as $profileId) {
            $profile = Profile::find($profileId);

            if (! $profile) {
                continue;
            }

            $evidence = Evidence::create([
                'user_id' => auth()->id(),
                'evidence_type_id' => $this->evidence_type_id,
                'profile_id' => $profile->id,
                'social_network' => $profile->social_network,
                'comment' => $this->comment,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            foreach ($savedImagesData as $imgData) {
                EvidenceImage::create([
                    'evidence_id' => $evidence->id,
                    'screenshot_path' => $imgData['path'],
                    'screenshot_hash' => $imgData['hash'],
                    'screenshot_mime' => $imgData['mime'],
                    'screenshot_size' => $imgData['size'],
                    'is_suspect' => $imgData['is_suspect'],
                ]);
            }
        }

        session()->flash('status', count($this->profile_ids).' evidencias registradas masivamente.');

        return $this->redirect('/evidencias/historial', navigate: true);
    }
}
