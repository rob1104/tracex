<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Models\EvidenceTextImport;
use App\Models\EvidenceType;
use App\Services\FacebookTextEvidenceIdentity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessFacebookTextEvidenceImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public int $importId) {}

    public function handle(FacebookTextEvidenceIdentity $identity): void
    {
        $import = EvidenceTextImport::find($this->importId);

        if (! $import || $import->status === 'cancelled') {
            return;
        }

        if ($import->status === 'completed') {
            Storage::disk('local')->delete($import->parsed_path);

            return;
        }

        if (! $import->profile_id || ! $import->parsed_path) {
            throw new RuntimeException('La importación no tiene un perfil o vista previa disponible.');
        }

        $path = Storage::disk('local')->path($import->parsed_path);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('No se encontró el archivo temporal preparado para la importación.');
        }

        $import->update([
            'status' => 'processing',
            'processed_count' => 0,
            'imported_count' => 0,
            'duplicate_count' => 0,
            'replaced_count' => 0,
            'error_message' => null,
        ]);

        $typeIds = $this->evidenceTypeIds();
        $input = fopen($path, 'rb');

        if ($input === false) {
            throw new RuntimeException('No se pudo abrir el resultado del análisis.');
        }

        $batch = [];
        $processedCount = 0;
        $importedCount = 0;
        $duplicateCount = 0;
        $replacedCount = 0;

        try {
            while (($line = fgets($input)) !== false) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $batch[] = $record;

                if (count($batch) >= 100) {
                    [$added, $duplicates, $replaced] = $this->persistBatch($batch, $import, $typeIds, $identity);
                    $processedCount += count($batch);
                    $importedCount += $added;
                    $duplicateCount += $duplicates;
                    $replacedCount += $replaced;
                    $batch = [];

                    $import->update([
                        'processed_count' => $processedCount,
                        'imported_count' => $importedCount,
                        'duplicate_count' => $duplicateCount,
                        'replaced_count' => $replacedCount,
                    ]);
                }
            }

            if ($batch !== []) {
                [$added, $duplicates, $replaced] = $this->persistBatch($batch, $import, $typeIds, $identity);
                $processedCount += count($batch);
                $importedCount += $added;
                $duplicateCount += $duplicates;
                $replacedCount += $replaced;
            }
        } finally {
            fclose($input);
        }

        $import->update([
            'status' => 'completed',
            'processed_count' => $processedCount,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
            'replaced_count' => $replacedCount,
        ]);

        Storage::disk('local')->delete($import->parsed_path);
    }

    /** @return array{0:int, 1:int, 2:int} */
    private function persistBatch(
        array $records,
        EvidenceTextImport $import,
        array $typeIds,
        FacebookTextEvidenceIdentity $identity,
    ): array {
        $added = 0;
        $duplicates = 0;
        $replaced = 0;

        DB::transaction(function () use ($records, $import, $typeIds, $identity, &$added, &$duplicates, &$replaced): void {
            foreach ($records as $record) {
                if (($record['kind'] ?? null) !== 'evidence') {
                    continue;
                }

                $typeId = $typeIds[$record['type_key']] ?? null;

                if ($typeId === null) {
                    throw new RuntimeException('No se encontró el tipo de evidencia para una actividad detectada.');
                }

                $sourceLine = (int) ($record['source_line'] ?? 0);
                $matches = $record['duplicate_matches'] ?? [];
                $decision = $import->duplicate_decisions[(string) $sourceLine] ?? null;
                $fingerprint = $identity->fingerprint($import->profile_id, $typeId, $record);

                if ($matches !== [] && $decision === null) {
                    throw new RuntimeException('Hay coincidencias de evidencia que requieren una decisión antes de importarse.');
                }

                if ($matches !== [] && $decision === 'skip') {
                    $duplicates++;

                    continue;
                }

                if ($matches !== [] && $decision === 'replace') {
                    $existingEvidenceId = $matches['database']['evidence_id'] ?? null;

                    if ($existingEvidenceId === null) {
                        throw new RuntimeException('Solo se puede reemplazar una evidencia que ya existe en la base de datos.');
                    }

                    $existingEvidence = Evidence::query()
                        ->where('profile_id', $import->profile_id)
                        ->whereKey($existingEvidenceId)
                        ->lockForUpdate()
                        ->first();

                    if ($existingEvidence) {
                        $existingEvidence->update([
                            'facebook_import_log_id' => null,
                            'evidence_text_import_id' => $import->id,
                            'origin' => 'facebook_txt',
                            'external_published_at' => $record['occurred_at'],
                            'source_fingerprint' => $fingerprint,
                            'social_network' => 'Facebook',
                            'comment' => $record['text'],
                        ]);
                        $replaced++;

                        continue;
                    }

                    $forceImport = true;
                } else {
                    $forceImport = $matches !== [] && $decision === 'import';
                }

                if (! $forceImport) {
                    $existingEvidence = $identity->findExisting($import->profile_id, $typeId, $record);

                    if ($existingEvidence) {
                        if ((int) $existingEvidence->evidence_text_import_id === $import->id) {
                            if ($decision === 'replace') {
                                $replaced++;
                            } else {
                                $added++;
                            }

                            continue;
                        }

                        if ($matches !== []) {
                            $duplicates++;

                            continue;
                        }

                        $forceImport = true;
                    }
                }

                $insertFingerprint = $forceImport
                    ? $identity->forcedFingerprint($fingerprint, $import->id, $sourceLine)
                    : $fingerprint;

                $evidenceData = [
                    'user_id' => $import->user_id,
                    'evidence_type_id' => $typeId,
                    'profile_id' => $import->profile_id,
                    'facebook_import_log_id' => null,
                    'evidence_text_import_id' => $import->id,
                    'origin' => 'facebook_txt',
                    'external_published_at' => $record['occurred_at'],
                    'source_fingerprint' => $insertFingerprint,
                    'social_network' => 'Facebook',
                    'comment' => $record['text'],
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => $record['occurred_at'],
                    'updated_at' => now(),
                ];

                $inserted = DB::table('evidence')->insertOrIgnore($evidenceData);

                if ($inserted === 1) {
                    $added++;
                } else {
                    $alreadyImportedByThisJob = Evidence::query()
                        ->where('source_fingerprint', $insertFingerprint)
                        ->where('evidence_text_import_id', $import->id)
                        ->exists();

                    if ($alreadyImportedByThisJob) {
                        $added++;

                        continue;
                    }

                    if (! $forceImport) {
                        $evidenceData['source_fingerprint'] = $identity->forcedFingerprint($fingerprint, $import->id, $sourceLine);
                        $insertedAsConfirmed = DB::table('evidence')->insertOrIgnore($evidenceData);

                        if ($insertedAsConfirmed === 1) {
                            $added++;

                            continue;
                        }
                    }

                    $duplicates++;
                }
            }
        });

        return [$added, $duplicates, $replaced];
    }

    /** @return array<string, int> */
    private function evidenceTypeIds(): array
    {
        return [
            'reaction' => EvidenceType::query()->where('name', 'like', '%Reacci%')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Like / Reacción'])->id,
            'comment' => EvidenceType::query()->where('name', 'Comentario')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Comentario'])->id,
            'share' => EvidenceType::query()->where('name', 'Compartido')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Compartido'])->id,
        ];
    }

    public function failed(Throwable $exception): void
    {
        $import = EvidenceTextImport::find($this->importId);

        if (! $import) {
            return;
        }

        $import->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
        ]);

        Storage::disk('local')->delete($import->parsed_path);
    }
}
