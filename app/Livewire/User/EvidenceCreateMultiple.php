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

    public $ai_status = '';
    public $ai_error = '';

    #[Validate(['images.*' => 'image|max:5120'])]
    public $images = [];

    public function updatedImages()
    {
        $this->ai_status = '';
        $this->ai_error = '';

        if (empty($this->images)) {
            return;
        }

        $credentialsPath = storage_path('app/google-credentials.json');
        if (!file_exists($credentialsPath)) {
            $this->ai_error = 'Modo Inteligencia Artificial inactivo: Falta el archivo google-credentials.json en storage/app.';
            return;
        }

        try {
            $image = $this->images[count($this->images) - 1]; // Toma la última subida
            $imageClient = new \Google\Cloud\Vision\V1\ImageAnnotatorClient([
                'credentials' => $credentialsPath
            ]);

            $imageContent = file_get_contents($image->getRealPath());
            $response = $imageClient->documentTextDetection($imageContent);
            $texts = $response->getTextAnnotations();

            if ($texts && count($texts) > 0) {
                $rawText = $texts[0]->getDescription();
                
                // Cruza el texto con los nombres de los perfiles del usuario
                $matchedIds = [];
                $assignedProfiles = auth()->user()->assignedProfiles()->where('status', 'active')->get();
                
                foreach ($assignedProfiles as $profile) {
                    if (stripos($rawText, $profile->name) !== false) {
                        $matchedIds[] = $profile->id;
                    }
                }

                // Autocompleta el Tipo de Evidencia buscando palabras clave
                $typeId = null;
                if (preg_match('/\b(like|gusta|encanta|reaccion)\b/i', $rawText)) {
                    $typeId = EvidenceType::where('name', 'like', '%like%')->orWhere('name', 'like', '%gusta%')->first()?->id;
                } elseif (preg_match('/\b(coment|comment|respondi|escribi)\b/i', $rawText)) {
                    $typeId = EvidenceType::where('name', 'like', '%coment%')->first()?->id;
                } elseif (preg_match('/\b(comparti|share)\b/i', $rawText)) {
                    $typeId = EvidenceType::where('name', 'like', '%comparti%')->first()?->id;
                }

                $this->profile_ids = array_unique(array_merge($this->profile_ids, $matchedIds));
                if ($typeId && empty($this->evidence_type_id)) {
                    $this->evidence_type_id = $typeId;
                }

                $this->ai_status = 'IA Automática: ' . count($matchedIds) . ' perfiles detectados en la imagen.';
            } else {
                $this->ai_status = 'IA Automática: No se detectó texto legible en la imagen.';
            }

            $imageClient->close();

        } catch (\Exception $e) {
            $this->ai_error = 'Error en la IA: ' . $e->getMessage();
        }
    }

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
