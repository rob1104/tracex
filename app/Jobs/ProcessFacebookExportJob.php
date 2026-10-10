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

        if ($importLog->status === 'completed') {
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
            DB::transaction(function () use ($importLog): void {
                $parser = new FacebookExportParser($this->localPath);
                $profileDetails = $parser->perfil();

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

                // 3. Clear previous data for idempotency
                $importLog->comments()->delete();
                $importLog->reactions()->delete();
                $importLog->facebookUsers()->delete();
                $importLog->evidences()->delete();

                $facebookUser = FacebookUser::create([
                    'profile_id' => $matchedProfile?->id ?? $this->findProfileId($profileDetails['nombre'] ?? null),
                    'import_log_id' => $importLog->id,
                    'age_range' => null,
                    'joined_at' => null,
                    'metadata' => [
                        'name' => $profileDetails['nombre'] ?? null,
                        'profile_uri' => $profileDetails['uri'] ?? null,
                        'export_identifier' => $parser->identificador(),
                    ],
                ]);

                $comments = [];
                $reactions = [];
                $evidences = [];
                $commentCount = 0;
                $reactionCount = 0;
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
                        $evidences[] = [
                            'user_id' => $assignedUser->id,
                            'profile_id' => $matchedProfile->id,
                            'evidence_type_id' => $commentTypeId,
                            'facebook_import_log_id' => $importLog->id,
                            'origin' => 'google_drive',
                            'social_network' => 'Facebook',
                            'comment' => ! empty($comment['contexto']) ? "{$comment['contexto']}: {$content}" : $content,
                            'external_published_at' => $comment['fecha'],
                            'ip_address' => null,
                            'user_agent' => 'Google Drive Sync',
                            'created_at' => $comment['fecha'],
                            'updated_at' => $timestamp,
                        ];
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
                        'target_url' => null,
                        'metadata' => '[]',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];

                    // Option A: Ingest into evidence table if user & profile resolved
                    if ($assignedUser && $matchedProfile) {
                        $evidences[] = [
                            'user_id' => $assignedUser->id,
                            'profile_id' => $matchedProfile->id,
                            'evidence_type_id' => $reactionTypeId,
                            'facebook_import_log_id' => $importLog->id,
                            'origin' => 'google_drive',
                            'social_network' => 'Facebook',
                            'comment' => "Reacción [{$type}]".(! empty($reaction['objetivo']) ? " en: {$reaction['objetivo']}" : ''),
                            'external_published_at' => $reaction['fecha'],
                            'ip_address' => null,
                            'user_agent' => 'Google Drive Sync',
                            'created_at' => $reaction['fecha'],
                            'updated_at' => $timestamp,
                        ];
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

                if ($evidences !== []) {
                    Evidence::insert($evidences);
                }

                $importLog->update([
                    'profile_id' => $matchedProfile?->id,
                    'user_id' => $assignedUser?->id,
                    'total_comments_extracted' => $commentCount,
                    'total_reactions_extracted' => $reactionCount,
                    'status' => 'completed',
                    'error_message' => null,
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
        if ($importLog && $importLog->status !== 'completed') {
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

    protected function findProfileId(?string $exportedName): ?int
    {
        $normalizedName = mb_strtolower(trim((string) $exportedName));
        if ($normalizedName === '') {
            return null;
        }

        $profileIds = Profile::query()
            ->whereRaw('LOWER(name) = ?', [$normalizedName])
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->limit(2)
            ->pluck('id');

        return $profileIds->count() === 1 ? (int) $profileIds->first() : null;
    }
}
