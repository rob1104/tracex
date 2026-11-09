<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Models\EvidenceTextImport;
use App\Models\EvidenceType;
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

    public function handle(): void
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
            'duplicate_count' => 0,
            'error_message' => null,
        ]);

        $typeIds = $this->evidenceTypeIds();
        $input = fopen($path, 'rb');

        if ($input === false) {
            throw new RuntimeException('No se pudo abrir el resultado del análisis.');
        }

        $batch = [];
        $processedCount = 0;
        $importedCount = $import->imported_count;
        $duplicateCount = 0;

        try {
            while (($line = fgets($input)) !== false) {
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                $batch[] = $record;

                if (count($batch) >= 100) {
                    [$added, $duplicates] = $this->persistBatch($batch, $import, $typeIds);
                    $processedCount += count($batch);
                    $importedCount += $added;
                    $duplicateCount += $duplicates;
                    $batch = [];

                    $import->update([
                        'processed_count' => $processedCount,
                        'imported_count' => $importedCount,
                        'duplicate_count' => $duplicateCount,
                    ]);
                }
            }

            if ($batch !== []) {
                [$added, $duplicates] = $this->persistBatch($batch, $import, $typeIds);
                $processedCount += count($batch);
                $importedCount += $added;
                $duplicateCount += $duplicates;
            }
        } finally {
            fclose($input);
        }

        $import->update([
            'status' => 'completed',
            'processed_count' => $processedCount,
            'imported_count' => $importedCount,
            'duplicate_count' => $duplicateCount,
        ]);

        Storage::disk('local')->delete($import->parsed_path);
    }

    /** @return array{0:int, 1:int} */
    private function persistBatch(array $records, EvidenceTextImport $import, array $typeIds): array
    {
        $added = 0;
        $duplicates = 0;

        DB::transaction(function () use ($records, $import, $typeIds, &$added, &$duplicates): void {
            foreach ($records as $record) {
                if (($record['kind'] ?? null) !== 'evidence') {
                    continue;
                }

                $typeId = $typeIds[$record['type_key']] ?? null;

                if ($typeId === null) {
                    throw new RuntimeException('No se encontró el tipo de evidencia para una actividad detectada.');
                }

                $alreadyExists = Evidence::query()
                    ->where('profile_id', $import->profile_id)
                    ->where('evidence_type_id', $typeId)
                    ->whereNull('source_fingerprint')
                    ->where('comment', $record['text'])
                    ->where('external_published_at', $record['occurred_at'])
                    ->exists();

                if ($alreadyExists) {
                    $duplicates++;

                    continue;
                }

                $fingerprint = hash('sha256', implode("\0", [
                    $import->profile_id,
                    $typeId,
                    $record['dedupe_text'] ?? $record['text'],
                    $record['occurred_at'],
                ]));

                $inserted = DB::table('evidence')->insertOrIgnore([
                    'user_id' => $import->user_id,
                    'evidence_type_id' => $typeId,
                    'profile_id' => $import->profile_id,
                    'facebook_import_log_id' => null,
                    'origin' => 'facebook_txt',
                    'external_published_at' => $record['occurred_at'],
                    'source_fingerprint' => $fingerprint,
                    'social_network' => 'Facebook',
                    'comment' => $record['text'],
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => $record['occurred_at'],
                    'updated_at' => now(),
                ]);

                if ($inserted === 1) {
                    $added++;
                } else {
                    $duplicates++;
                }
            }
        });

        return [$added, $duplicates];
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
