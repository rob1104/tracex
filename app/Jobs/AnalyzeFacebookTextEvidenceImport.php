<?php

namespace App\Jobs;

use App\Models\EvidenceTextImport;
use App\Models\EvidenceType;
use App\Services\FacebookTextEvidenceIdentity;
use App\Services\FacebookTextEvidenceParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnalyzeFacebookTextEvidenceImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public int $importId) {}

    public function handle(
        FacebookTextEvidenceParser $parser,
        FacebookTextEvidenceIdentity $identity,
    ): void {
        $import = EvidenceTextImport::find($this->importId);

        if (! $import || $import->status === 'cancelled') {
            return;
        }

        $import->update([
            'status' => 'analyzing',
            'error_message' => null,
            'processed_count' => 0,
        ]);

        $disk = Storage::disk('local');
        $sourcePath = $import->source_path;

        if (! $sourcePath || ! $import->profile_id) {
            throw new \RuntimeException('La importación no tiene texto pendiente de análisis.');
        }

        $parsedPath = 'facebook-text-imports/'.$import->id.'/activities.jsonl';
        $parsedAbsolutePath = $disk->path($parsedPath);
        $directory = dirname($parsedAbsolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $output = fopen($parsedAbsolutePath, 'wb');

        if ($output === false) {
            throw new \RuntimeException('No se pudo preparar el resultado del análisis.');
        }

        $totalEntries = 0;
        $recognizedCount = 0;
        $unrecognizedCount = 0;
        $previewRecords = [];
        $previewUnparsed = [];
        $duplicatePreview = [];
        $seenFingerprints = [];
        $typeIds = [
            'reaction' => EvidenceType::query()->where('name', 'like', '%Reacci%')->value('id'),
            'comment' => EvidenceType::query()->where('name', 'Comentario')->value('id'),
            'share' => EvidenceType::query()->where('name', 'Compartido')->value('id'),
        ];

        try {
            foreach ($parser->parseFile($disk->path($sourcePath)) as $record) {
                if ($record['kind'] === 'evidence') {
                    $typeId = $typeIds[$record['type_key']] ?? null;
                    $matches = [];

                    if ($typeId !== null) {
                        $fingerprint = $identity->fingerprint($import->profile_id, (int) $typeId, $record);
                        $existing = $identity->findExisting($import->profile_id, (int) $typeId, $record);

                        if ($existing) {
                            $matches['database'] = [
                                'evidence_id' => $existing->id,
                                'user_name' => $existing->user?->name ?? 'Colaborador anterior',
                                'type_name' => $existing->evidenceType?->name ?? $record['type_label'],
                                'origin' => $existing->origin,
                                'occurred_at' => $existing->external_published_at?->format('Y-m-d H:i:s'),
                                'text' => $existing->comment ?? '',
                            ];
                        }

                        if (isset($seenFingerprints[$fingerprint])) {
                            $matches['same_import'] = [
                                'source_line' => $seenFingerprints[$fingerprint]['source_line'],
                                'text' => $seenFingerprints[$fingerprint]['text'],
                            ];
                        } else {
                            $seenFingerprints[$fingerprint] = [
                                'source_line' => $record['source_line'],
                                'text' => $record['text'],
                            ];
                        }
                    }

                    if ($matches !== []) {
                        $record['duplicate_matches'] = $matches;
                        $duplicatePreview[] = [
                            'source_line' => $record['source_line'],
                            'type_label' => $record['type_label'],
                            'occurred_at' => $record['occurred_at'],
                            'text' => $record['text'],
                            'matches' => $matches,
                        ];
                    }
                }

                fwrite($output, json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $totalEntries++;

                if ($record['kind'] === 'evidence') {
                    $recognizedCount++;

                    if (count($previewRecords) < 30) {
                        $previewRecords[] = [
                            'type_label' => $record['type_label'],
                            'occurred_at' => $record['occurred_at'],
                            'text' => mb_substr($record['text'], 0, 700),
                        ];
                    }
                } else {
                    $unrecognizedCount++;

                    if (count($previewUnparsed) < 50) {
                        $previewUnparsed[] = [
                            'source_line' => $record['source_line'],
                            'reason' => $record['reason'],
                            'text' => mb_substr($record['text'], 0, 1000),
                        ];
                    }
                }

                if ($totalEntries % 200 === 0) {
                    $import->update([
                        'processed_count' => $totalEntries,
                        'recognized_count' => $recognizedCount,
                        'unrecognized_count' => $unrecognizedCount,
                    ]);
                }
            }
        } finally {
            fclose($output);
        }

        $import->update([
            'status' => 'awaiting_confirmation',
            'source_path' => null,
            'parsed_path' => $parsedPath,
            'total_entries' => $totalEntries,
            'processed_count' => $totalEntries,
            'recognized_count' => $recognizedCount,
            'unrecognized_count' => $unrecognizedCount,
            'preview_records' => $previewRecords,
            'preview_unparsed' => $previewUnparsed,
            'duplicate_preview' => $duplicatePreview,
            'duplicate_decisions' => [],
        ]);

        $disk->delete($sourcePath);
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

        Storage::disk('local')->delete(array_values(array_filter([
            $import->source_path,
            $import->parsed_path,
        ])));
    }
}
