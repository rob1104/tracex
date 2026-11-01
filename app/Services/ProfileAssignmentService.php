<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\FacebookImportLog;
use App\Models\FacebookUser;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileAssignmentService
{
    /**
     * Resolve the matched Profile and assigned collaborator User for a given exported profile payload.
     *
     * @param  array{nombre?: ?string, uri?: ?string, identifier?: ?string, emails?: array<string>}  $exportedProfile
     * @return array{profile: ?Profile, user: ?User}
     */
    public function resolve(array $exportedProfile): array
    {
        $identifier = FacebookProfileIdentifier::normalize($exportedProfile['identifier'] ?? null);
        $uriIdentifier = FacebookProfileIdentifier::normalize($exportedProfile['uri'] ?? null);
        $identifiers = array_values(array_unique(array_filter([$identifier, $uriIdentifier])));

        $facebookProfiles = Profile::query()
            ->with(['users' => function ($q) {
                $q->where('users.is_active', true)->where('users.role', 'user')->orderBy('users.id', 'asc');
            }])
            ->whereRaw('LOWER(social_network) = ?', ['facebook']);

        $profile = null;

        // Facebook's stable identifier is the only automatic profile matching key.
        foreach ($identifiers as $candidateIdentifier) {
            $profilesByIdentifier = (clone $facebookProfiles)
                ->where('facebook_identifier', $candidateIdentifier)
                ->limit(2)
                ->get();

            if ($profilesByIdentifier->count() > 1) {
                return ['profile' => null, 'user' => null];
            }

            if ($profilesByIdentifier->isNotEmpty()) {
                $profile = $profilesByIdentifier->first();
                break;
            }
        }

        // Si el perfil de Facebook no está registrado en el sistema, no se auto-crea ni asocia
        // automáticamente; se mantiene sin asociación hasta que un administrador lo asigne.
        if (! $profile) {
            return ['profile' => null, 'user' => null];
        }

        $user = $profile->users->first();

        return [
            'profile' => $profile,
            'user' => $user,
        ];
    }

    /**
     * Explicitly bind an import log to a specific profile and collaborator user.
     */
    public function assignImportLog(FacebookImportLog $importLog, Profile $profile, User $user): void
    {
        $importLog->update([
            'profile_id' => $profile->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Bind a pending import log to a profile and user, and generate Evidence rows from its Facebook activity.
     *
     * @return int Number of generated Evidence rows.
     */
    public function claimPendingImport(FacebookImportLog $importLog, Profile $profile, User $user): int
    {
        return DB::transaction(function () use ($importLog, $profile, $user): int {
            $importLog = FacebookImportLog::query()->lockForUpdate()->findOrFail($importLog->id);

            if ($importLog->status !== 'pending_assignment') {
                throw ValidationException::withMessages([
                    'pendingImport' => 'Esta carga ya fue asignada o ya no está pendiente.',
                ]);
            }

            $profile->users()->syncWithoutDetaching([$user->id]);
            $this->assignImportLog($importLog, $profile, $user);
            FacebookUser::query()
                ->where('import_log_id', $importLog->id)
                ->update(['profile_id' => $profile->id]);

            // Fetch EvidenceType IDs
            $commentTypeId = EvidenceType::where('name', 'Comentario')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Comentario'])->id;

            $reactionTypeId = EvidenceType::where('name', 'Like / Reacción')->value('id')
                ?? EvidenceType::where('name', 'like', '%Reacci%')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Like / Reacción'])->id;

            $importLog->evidences()->delete();

            $existingEvidenceKeys = [];
            Evidence::query()
                ->where('profile_id', $profile->id)
                ->where('origin', 'google_drive')
                ->select(['evidence_type_id', 'external_published_at', 'comment'])
                ->get()
                ->each(function (Evidence $evidence) use (&$existingEvidenceKeys): void {
                    $publishedKey = $evidence->external_published_at instanceof \DateTimeInterface
                        ? $evidence->external_published_at->format('Y-m-d H:i:s')
                        : (string) $evidence->external_published_at;
                    $existingEvidenceKeys["{$evidence->evidence_type_id}|{$publishedKey}|{$evidence->comment}"] = true;
                });

            $evidences = [];
            $count = 0;
            $shareCount = 0;
            $timestamp = now();

            foreach ($importLog->comments as $comment) {
                $context = null;
                if (is_array($comment->metadata) && ! empty($comment->metadata['context'])) {
                    $context = $comment->metadata['context'];
                }

                $evidenceComment = $context ? "{$context}: {$comment->content}" : $comment->content;
                $publishedKey = $comment->published_at instanceof \DateTimeInterface
                    ? $comment->published_at->format('Y-m-d H:i:s')
                    : (string) $comment->published_at;
                $evidenceKey = "{$commentTypeId}|{$publishedKey}|{$evidenceComment}";

                if (isset($existingEvidenceKeys[$evidenceKey])) {
                    continue;
                }
                $existingEvidenceKeys[$evidenceKey] = true;

                $evidences[] = [
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'evidence_type_id' => $commentTypeId,
                    'facebook_import_log_id' => $importLog->id,
                    'origin' => 'google_drive',
                    'social_network' => 'Facebook',
                    'comment' => $evidenceComment,
                    'external_published_at' => $comment->published_at,
                    'ip_address' => null,
                    'user_agent' => 'Google Drive Sync Manual Claim',
                    'created_at' => $comment->published_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ];
                $count++;

                if (count($evidences) === 500) {
                    Evidence::insert($evidences);
                    $evidences = [];
                }
            }

            foreach ($importLog->reactions as $reaction) {
                $targetLabel = $reaction->title ?: $reaction->target_url;
                $evidenceComment = "Reacción [{$reaction->reaction_type}]".($targetLabel ? " en: {$targetLabel}" : '');
                $publishedKey = $reaction->published_at instanceof \DateTimeInterface
                    ? $reaction->published_at->format('Y-m-d H:i:s')
                    : (string) $reaction->published_at;
                $evidenceKey = "{$reactionTypeId}|{$publishedKey}|{$evidenceComment}";

                if (isset($existingEvidenceKeys[$evidenceKey])) {
                    continue;
                }
                $existingEvidenceKeys[$evidenceKey] = true;

                $evidences[] = [
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'evidence_type_id' => $reactionTypeId,
                    'facebook_import_log_id' => $importLog->id,
                    'origin' => 'google_drive',
                    'social_network' => 'Facebook',
                    'comment' => $evidenceComment,
                    'external_published_at' => $reaction->published_at,
                    'ip_address' => null,
                    'user_agent' => 'Google Drive Sync Manual Claim',
                    'created_at' => $reaction->published_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ];
                $count++;

                if (count($evidences) === 500) {
                    Evidence::insert($evidences);
                    $evidences = [];
                }
            }

            $importMetadata = is_array($importLog->metadata) ? $importLog->metadata : [];
            $shares = is_array($importMetadata['facebook_shares'] ?? null)
                ? $importMetadata['facebook_shares']
                : [];

            if ($shares !== []) {
                $shareTypeId = EvidenceType::where('name', 'Compartido')->value('id')
                    ?? EvidenceType::firstOrCreate(['name' => 'Compartido'])->id;

                foreach ($shares as $share) {
                    $description = trim((string) ($share['descripcion'] ?? ''));
                    $url = trim((string) ($share['enlace'] ?? ''));
                    if ($description === '' && $url === '') {
                        continue;
                    }

                    $shareDescription = $description !== '' ? $description : 'Compartió una publicación de Facebook';
                    if ($url !== '') {
                        $shareDescription .= "\n".$url;
                    }
                    $publishedAt = $share['fecha'] ?? $timestamp;
                    $publishedKey = $publishedAt instanceof \DateTimeInterface
                        ? $publishedAt->format('Y-m-d H:i:s')
                        : (string) $publishedAt;
                    $evidenceKey = "{$shareTypeId}|{$publishedKey}|{$shareDescription}";

                    $shareCount++;

                    if (isset($existingEvidenceKeys[$evidenceKey])) {
                        continue;
                    }
                    $existingEvidenceKeys[$evidenceKey] = true;

                    $evidences[] = [
                        'user_id' => $user->id,
                        'profile_id' => $profile->id,
                        'evidence_type_id' => $shareTypeId,
                        'facebook_import_log_id' => $importLog->id,
                        'origin' => 'google_drive',
                        'social_network' => 'Facebook',
                        'comment' => $shareDescription,
                        'external_published_at' => $publishedAt,
                        'ip_address' => null,
                        'user_agent' => 'Google Drive Sync Manual Claim',
                        'created_at' => $publishedAt,
                        'updated_at' => $timestamp,
                    ];
                    $count++;

                    if (count($evidences) === 500) {
                        Evidence::insert($evidences);
                        $evidences = [];
                    }
                }
            }

            if ($evidences !== []) {
                Evidence::insert($evidences);
            }

            unset($importMetadata['facebook_shares']);
            $importMetadata['facebook_shares_count'] = $shareCount;

            $importLog->update([
                'status' => 'completed',
                'error_message' => null,
                'metadata' => $importMetadata,
            ]);

            return $count;
        });
    }
}
