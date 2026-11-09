<?php

namespace App\Livewire\User;

use App\Jobs\AnalyzeFacebookTextEvidenceImport;
use App\Jobs\ProcessFacebookTextEvidenceImport;
use App\Models\EvidenceTextImport as EvidenceTextImportRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class EvidenceTextImport extends Component
{
    private const MAX_TEXT_BYTES = 5 * 1024 * 1024;

    public string $profileId = '';

    public string $pastedText = '';

    public function startAnalysis(): void
    {
        $this->validate([
            'profileId' => ['required', 'integer'],
            'pastedText' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > self::MAX_TEXT_BYTES) {
                        $fail('El texto pegado no puede superar los 5 MB.');
                    }
                },
            ],
        ], [
            'profileId.required' => 'Selecciona el perfil de Facebook al que corresponde el texto.',
            'pastedText.required' => 'Pega el contenido de la exportación de Facebook.',
        ]);

        abort_unless($this->availableProfiles()->whereKey($this->profileId)->exists(), 403);

        $import = EvidenceTextImportRecord::create([
            'user_id' => auth()->id(),
            'profile_id' => (int) $this->profileId,
            'original_file_name' => 'Texto pegado',
            'status' => 'queued_preview',
        ]);

        $sourcePath = 'facebook-text-imports/'.$import->id.'/source-'.Str::uuid().'.txt';

        if (! Storage::disk('local')->put($sourcePath, $this->pastedText)) {
            $import->delete();
            $this->addError('pastedText', 'No se pudo preparar el texto para analizarlo. Inténtalo de nuevo.');

            return;
        }

        $import->update(['source_path' => $sourcePath]);

        AnalyzeFacebookTextEvidenceImport::dispatch($import->id)
            ->onConnection('facebook_text_imports');

        $this->reset(['profileId', 'pastedText']);
        session()->flash('status', 'El texto quedó en cola para analizarse. La vista previa aparecerá aquí al terminar.');
    }

    public function confirmImport(int $importId): void
    {
        $import = EvidenceTextImportRecord::query()
            ->where('user_id', auth()->id())
            ->findOrFail($importId);

        if ($import->status !== 'awaiting_confirmation' || $import->recognized_count < 1) {
            return;
        }

        $import->update([
            'status' => 'queued',
            'error_message' => null,
        ]);

        ProcessFacebookTextEvidenceImport::dispatch($import->id)
            ->onConnection('facebook_text_imports');
    }

    public function discardImport(int $importId): void
    {
        $import = EvidenceTextImportRecord::query()
            ->where('user_id', auth()->id())
            ->findOrFail($importId);

        if (! in_array($import->status, ['awaiting_confirmation', 'failed'], true)) {
            return;
        }

        Storage::disk('local')->delete(array_values(array_filter([
            $import->source_path,
            $import->parsed_path,
        ])));

        $import->update([
            'status' => 'cancelled',
            'source_path' => null,
            'parsed_path' => null,
            'preview_records' => null,
            'preview_unparsed' => null,
        ]);
    }

    public function render(): View
    {
        $imports = EvidenceTextImportRecord::query()
            ->with('profile')
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.user.evidence-text-import', [
            'profiles' => $this->availableProfiles()->orderBy('name')->get(),
            'imports' => $imports,
            'hasPendingImports' => $imports->contains(fn (EvidenceTextImportRecord $import): bool => in_array($import->status, [
                'queued_preview', 'analyzing', 'queued', 'processing',
            ], true)),
        ])->layout('layouts.app');
    }

    private function availableProfiles(): BelongsToMany
    {
        return auth()->user()->assignedProfiles()
            ->where('profiles.status', 'active')
            ->whereRaw('LOWER(profiles.social_network) = ?', ['facebook']);
    }
}
