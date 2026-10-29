<?php

namespace App\Jobs;

use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\FacebookComment;
use App\Models\FacebookImportLog;
use App\Models\FacebookReaction;
use App\Models\FacebookUser;
use App\Models\Profile;
use App\Services\FacebookExportParser;
use App\Services\FacebookProfileIdentifier;
use App\Services\ProfileAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessFacebookExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $localPath,
        public int $importLogId
    ) {}

    public function handle(): void
    {
        $importLog = FacebookImportLog::find($this->importLogId);

        if (! $importLog) {
            Log::warning('Facebook import log not found; skipping processing.', [
                'import_log_id' => $this->importLogId,
            ]);
            $this->cleanLocalPath();

            return;
        }

        if (in_array($importLog->status, ['completed', 'pending_assignment'], true)) {
            $this->cleanLocalPath();

            return;
        }

        if (! File::isDirectory($this->localPath)) {
            throw new RuntimeException("Export directory does not exist: {$this->localPath}");
        }

        $importLog->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            // Validate and parse before deleting data from a previous processing attempt.
            $parser = new FacebookExportParser($this->localPath, $importLog->file_name);
            $parser->validarEstructura();
            $profileDetails = $parser->perfil();
            $exportIdentifier = FacebookProfileIdentifier::normalize($parser->identificador());
            $profileDetails['identifier'] = $exportIdentifier;

            $hasComments = false;
            foreach ($parser->comentarios() as $_) {
                $hasComments = true;
                break;
            }

            $hasReactions = false;
            foreach ($parser->reacciones() as $_) {
                $hasReactions = true;
                break;
            }

            $hasShares = false;
            foreach ($parser->compartidos() as $_) {
                $hasShares = true;
                break;
            }

            if (! $hasComments && ! $hasReactions && ! $hasShares) {
                throw new RuntimeException('Estructura de exportación inválida: no se extrajeron comentarios ni reacciones válidas para importar.');
            }

            DB::transaction(function () use ($importLog, $profileDetails, $exportIdentifier, $parser): void {

                // 1. Resolve Profile and Collaborator User
                $assignmentService = app(ProfileAssignmentService::class);
                $resolution = $assignmentService->resolve($profileDetails);
                $matchedProfile = $resolution['profile'];
                $assignedUser = $resolution['user'];

                // 2. Fetch EvidenceType IDs for Comentario and Reaccion
                $commentTypeId = EvidenceType::where('name', 'Comentario')->value('id')
                    ?? EvidenceType::firstOrCreate(['name' => 'Comentario'])->id;

                $reactionTypeId = EvidenceType::where('name', 'Like / Reacción')->value('id')
                    ?? EvidenceType::where('name', 'like', '%Reacci%')->value('id')
                    ?? EvidenceType::firstOrCreate(['name' => 'Like / Reacción'])->id;

                $shareTypeId = EvidenceType::where('name', 'Compartido')->value('id')
                    ?? EvidenceType::firstOrCreate(['name' => 'Compartido'])->id;

                // 3. Clear previous data for idempotency
                $importLog->comments()->delete();
                $importLog->reactions()->delete();
                $importLog->facebookUsers()->delete();
                $importLog->evidences()->delete();

                $existingEvidenceKeys = [];
                if ($assignedUser && $matchedProfile) {
                    Evidence::query()
                        ->where('profile_id', $matchedProfile->id)
                        ->where('origin', 'google_drive')
                        ->select(['evidence_type_id', 'external_published_at', 'comment'])
                        ->get()
                        ->each(function (Evidence $evidence) use (&$existingEvidenceKeys): void {
                            $publishedKey = $evidence->external_published_at instanceof \DateTimeInterface
                                ? $evidence->external_published_at->format('Y-m-d H:i:s')
                                : (string) $evidence->external_published_at;
                            $existingEvidenceKeys["{$evidence->evidence_type_id}|{$publishedKey}|{$evidence->comment}"] = true;
                        });
                }

                $facebookUser = FacebookUser::create([
                    'profile_id' => $matchedProfile?->id,
                    'import_log_id' => $importLog->id,
                    'age_range' => null,
                    'joined_at' => null,
                    'metadata' => [
                        'name' => $profileDetails['nombre'] ?? null,
                        'profile_uri' => $profileDetails['uri'] ?? null,
                        'export_identifier' => $exportIdentifier,
                    ],
                ]);

                $comments = [];
                $reactions = [];
                $evidences = [];
                $shares = [];
                $commentCount = 0;
                $reactionCount = 0;
                $shareCount = 0;
                $timestamp = now();

                // Process Comments
                foreach ($parser->comentarios() as $comment) {
                    $content = trim((string) ($comment['texto'] ?? ''));
                    if ($content === '') {
                        continue;
                    }

                    $wordMatches = [];
                    preg_match_all('/[\p{L}\p{N}_]+/u', $content, $wordMatches);

                    $comments[] = [
                        'facebook_user_id' => $facebookUser->id,
                        'import_log_id' => $importLog->id,
                        'content' => $content,
                        'character_count' => mb_strlen($content, 'UTF-8'),
                        'word_count' => count($wordMatches[0] ?? []),
                        'published_at' => $comment['fecha'],
                        'post_url' => null,
                        'metadata' => json_encode([
                            'context' => $comment['contexto'] ?? null,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    // Option A: Ingest into evidence table if user & profile resolved
                    if ($assignedUser && $matchedProfile) {
                        $evidenceComment = ! empty($comment['contexto']) ? "{$comment['contexto']}: {$content}" : $content;
                        $evidenceKey = "{$commentTypeId}|{$comment['fecha']}|{$evidenceComment}";

                        if (! isset($existingEvidenceKeys[$evidenceKey])) {
                            $existingEvidenceKeys[$evidenceKey] = true;
                            $evidences[] = [
                                'user_id' => $assignedUser->id,
                                'profile_id' => $matchedProfile->id,
                                'evidence_type_id' => $commentTypeId,
                                'facebook_import_log_id' => $importLog->id,
                                'origin' => 'google_drive',
                                'social_network' => 'Facebook',
                                'comment' => $evidenceComment,
                                'external_published_at' => $comment['fecha'],
                                'ip_address' => null,
                                'user_agent' => 'Google Drive Sync',
                                'created_at' => $comment['fecha'],
                                'updated_at' => $timestamp,
                            ];
                        }
                    }

                    $commentCount++;

                    if (count($comments) === 500) {
                        FacebookComment::insert($comments);
                        $comments = [];
                    }

                    if (count($evidences) === 500) {
                        Evidence::insert($evidences);
                        $evidences = [];
                    }
                }

                if ($comments !== []) {
                    FacebookComment::insert($comments);
                }

                // Process Reactions
                foreach ($parser->reacciones() as $reaction) {
                    $type = trim((string) ($reaction['tipo'] ?? ''));
                    if ($type === '') {
                        continue;
                    }

                    $reactions[] = [
                        'facebook_user_id' => $facebookUser->id,
                        'import_log_id' => $importLog->id,
                        'reaction_type' => $type,
                        'published_at' => $reaction['fecha'],
                        'title' => $reaction['objetivo'] ?? null,
                        'target_url' => $reaction['url'] ?? null,
                        'metadata' => '[]',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    // Option A: Ingest into evidence table if user & profile resolved
                    if ($assignedUser && $matchedProfile) {
                        $evidenceComment = "Reacción [{$type}]".(! empty($reaction['objetivo']) ? " en: {$reaction['objetivo']}" : '');
                        $evidenceKey = "{$reactionTypeId}|{$reaction['fecha']}|{$evidenceComment}";

                        if (! isset($existingEvidenceKeys[$evidenceKey])) {
                            $existingEvidenceKeys[$evidenceKey] = true;
                            $evidences[] = [
                                'user_id' => $assignedUser->id,
                                'profile_id' => $matchedProfile->id,
                                'evidence_type_id' => $reactionTypeId,
                                'facebook_import_log_id' => $importLog->id,
                                'origin' => 'google_drive',
                                'social_network' => 'Facebook',
                                'comment' => $evidenceComment,
                                'external_published_at' => $reaction['fecha'],
                                'ip_address' => null,
                                'user_agent' => 'Google Drive Sync',
                                'created_at' => $reaction['fecha'],
                                'updated_at' => $timestamp,
                            ];
                        }
                    }

                    $reactionCount++;

                    if (count($reactions) === 500) {
                        FacebookReaction::insert($reactions);
                        $reactions = [];
                    }

                    if (count($evidences) === 500) {
                        Evidence::insert($evidences);
                        $evidences = [];
                    }
                }

                if ($reactions !== []) {
                    FacebookReaction::insert($reactions);
                }

                // Process shared posts using the existing Evidence model.
                foreach ($parser->compartidos() as $share) {
                    $description = trim((string) ($share['descripcion'] ?? ''));
                    $url = trim((string) ($share['enlace'] ?? ''));
                    $publishedAt = $share['fecha'] ?? $timestamp;

                    if ($description === '' && $url === '') {
                        continue;
                    }

                    $shares[] = [
                        'descripcion' => $description,
                        'enlace' => $url,
                        'fecha' => $publishedAt,
                    ];

                    if ($assignedUser && $matchedProfile) {
                        $shareDescription = $description !== '' ? $description : 'Compartió una publicación de Facebook';
                        if ($url !== '') {
                            $shareDescription .= "\n".$url;
                        }

                        $publishedKey = $publishedAt instanceof \DateTimeInterface
                            ? $publishedAt->format('Y-m-d H:i:s')
                            : (string) $publishedAt;
                        $evidenceKey = "{$shareTypeId}|{$publishedKey}|{$shareDescription}";

                        if (! isset($existingEvidenceKeys[$evidenceKey])) {
                            $existingEvidenceKeys[$evidenceKey] = true;
                            $evidences[] = [
                                'user_id' => $assignedUser->id,
                                'profile_id' => $matchedProfile->id,
                                'evidence_type_id' => $shareTypeId,
                                'facebook_import_log_id' => $importLog->id,
                                'origin' => 'google_drive',
                                'social_network' => 'Facebook',
                                'comment' => $shareDescription,
                                'external_published_at' => $publishedAt,
                                'ip_address' => null,
                                'user_agent' => 'Google Drive Sync',
                                'created_at' => $publishedAt,
                                'updated_at' => $timestamp,
                            ];
                        }
                    }

                    $shareCount++;

                    if (count($evidences) === 500) {
                        Evidence::insert($evidences);
                        $evidences = [];
                    }
                }

                if ($evidences !== []) {
                    Evidence::insert($evidences);
                }

                $metadata = $importLog->metadata ?? [];
                $metadata['facebook_shares_count'] = $shareCount;
                if ($matchedProfile && $assignedUser) {
                    unset($metadata['facebook_shares']);
                } else {
                    $metadata['facebook_shares'] = $shares;
                }

                $importLog->update([
                    'profile_id' => $matchedProfile?->id,
                    'user_id' => $assignedUser?->id,
                    'total_comments_extracted' => $commentCount,
                    'total_reactions_extracted' => $reactionCount,
                    'metadata' => $metadata,
                    'status' => $matchedProfile && $assignedUser ? 'completed' : 'pending_assignment',
                    'error_message' => $matchedProfile && $assignedUser ? null : 'Se requiere asignación manual de perfil y colaborador.',
                ]);
            });
        } catch (Throwable $exception) {
            $importLog->update([
                'status' => 'failed',
                'error_message' => mb_substr('Parsing failed: '.$exception->getMessage(), 0, 60000),
            ]);

            Log::error('Failed to process Facebook export.', [
                'import_log_id' => $importLog->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->cleanLocalPath();

        Log::info('Facebook export processing completed.', [
            'import_log_id' => $importLog->id,
            'comments' => $importLog->total_comments_extracted,
            'reactions' => $importLog->total_reactions_extracted,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::critical('Facebook export job exhausted its attempts.', [
            'import_log_id' => $this->importLogId,
            'error' => $exception?->getMessage(),
        ]);

        $importLog = FacebookImportLog::find($this->importLogId);
        if ($importLog && ! in_array($importLog->status, ['completed', 'pending_assignment'], true)) {
            $importLog->update([
                'status' => 'failed',
                'error_message' => mb_substr(
                    'Parsing failed after maximum attempts: '.($exception?->getMessage() ?? 'Unknown error'),
                    0,
                    60000
                ),
            ]);
        }

        $this->cleanLocalPath();
    }

    protected function cleanLocalPath(): void
    {
        if (File::isDirectory($this->localPath)) {
            File::deleteDirectory($this->localPath);
        } elseif (File::exists($this->localPath)) {
            File::delete($this->localPath);
        }
    }
}
