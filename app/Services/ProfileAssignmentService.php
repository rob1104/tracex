<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfileAssignmentService
{
    /**
     * Resolve the matched Profile and assigned collaborator User for a given exported profile payload.
     *
     * @param  array{nombre?: ?string, uri?: ?string, identifier?: ?string}  $exportedProfile
     * @return array{profile: ?Profile, user: ?User}
     */
    public function resolve(array $exportedProfile): array
    {
        $name = trim((string) ($exportedProfile['nombre'] ?? ''));
        $identifier = FacebookProfileIdentifier::normalize($exportedProfile['identifier'] ?? null);
        $facebookProfiles = Profile::query()
            ->with(['users' => function ($q) {
                $q->where('is_active', true)->orderBy('users.id', 'asc');
            }, 'creator'])
            ->whereRaw('LOWER(social_network) = ?', ['facebook']);

        $profile = null;

        if ($identifier !== null) {
            $profilesByIdentifier = (clone $facebookProfiles)
                ->where('facebook_identifier', $identifier)
                ->limit(2)
                ->get();

            if ($profilesByIdentifier->count() > 1) {
                return ['profile' => null, 'user' => null];
            }

            $profile = $profilesByIdentifier->first();
        }

        if (! $profile && $name !== '') {
            $normalizedName = mb_strtolower($name, 'UTF-8');
            $matchingProfiles = (clone $facebookProfiles)
                ->whereRaw('LOWER(name) = ?', [$normalizedName])
                ->limit(2)
                ->get();

            if ($matchingProfiles->count() !== 1) {
                return ['profile' => null, 'user' => null];
            }

            $profile = $matchingProfiles->first();
            $storedIdentifier = FacebookProfileIdentifier::normalize($profile->facebook_identifier);

            if ($identifier !== null && $storedIdentifier !== null && $storedIdentifier !== $identifier) {
                return ['profile' => null, 'user' => null];
            }

            if ($identifier !== null && $storedIdentifier === null) {
                $profile->facebook_identifier = $identifier;
                $profile->save();
            }
        }

        if (! $profile) {
            return ['profile' => null, 'user' => null];
        }

        // Resolve assigned collaborator (User):
        // 1. Primary: first active assigned collaborator in profile_user
        // 2. Fallback: profile creator (created_by)
        $user = $profile->users->first() ?? $profile->creator;

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
     * Bind a pending import log to a profile and user, and generate Evidence rows from its comments and reactions.
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

            // Fetch EvidenceType IDs
            $commentTypeId = EvidenceType::where('name', 'Comentario')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Comentario'])->id;

            $reactionTypeId = EvidenceType::where('name', 'Like / Reacción')->value('id')
                ?? EvidenceType::where('name', 'like', '%Reacci%')->value('id')
                ?? EvidenceType::firstOrCreate(['name' => 'Like / Reacción'])->id;

            $importLog->evidences()->delete();

            $evidences = [];
            $count = 0;
            $timestamp = now();

            foreach ($importLog->comments as $comment) {
                $context = null;
                if (is_array($comment->metadata) && ! empty($comment->metadata['context'])) {
                    $context = $comment->metadata['context'];
                }

                $evidences[] = [
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'evidence_type_id' => $commentTypeId,
                    'facebook_import_log_id' => $importLog->id,
                    'origin' => 'google_drive',
                    'social_network' => 'Facebook',
                    'comment' => $context ? "{$context}: {$comment->content}" : $comment->content,
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
                $evidences[] = [
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'evidence_type_id' => $reactionTypeId,
                    'facebook_import_log_id' => $importLog->id,
                    'origin' => 'google_drive',
                    'social_network' => 'Facebook',
                    'comment' => "Reacción [{$reaction->reaction_type}]".($reaction->title ? " en: {$reaction->title}" : ''),
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

            if ($evidences !== []) {
                Evidence::insert($evidences);
            }

            $importLog->update([
                'status' => 'completed',
                'error_message' => null,
            ]);

            return $count;
        });
    }
}
