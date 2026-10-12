<?php

namespace App\Services;

use App\Models\EmailAccount;
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
        $name = trim((string) ($exportedProfile['nombre'] ?? ''));
        $identifier = FacebookProfileIdentifier::normalize($exportedProfile['identifier'] ?? null);
        $uriIdentifier = FacebookProfileIdentifier::normalize($exportedProfile['uri'] ?? null);
        $identifiers = array_values(array_unique(array_filter([$identifier, $uriIdentifier])));
        $emails = array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', (array) ($exportedProfile['emails'] ?? []))))));

        // 1. Resolution via Exported Emails (Primary)
        if ($emails !== []) {
            $emailAccount = EmailAccount::query()
                ->whereIn('email', $emails)
                ->with(['assignedUser', 'creator', 'profiles' => function ($q) {
                    $q->whereRaw('LOWER(social_network) = ?', ['facebook'])
                        ->with(['users' => fn ($uq) => $uq->where('is_active', true)->orderBy('users.id', 'asc')]);
                }])
                ->first();

            if ($emailAccount) {
                $user = ($emailAccount->assignedUser && $emailAccount->assignedUser->is_active)
                    ? $emailAccount->assignedUser
                    : null;

                $profile = $emailAccount->profiles->first();

                if (! $user && $profile) {
                    $user = $profile->users->first();
                }

                if (! $user) {
                    $directUser = User::query()
                        ->whereIn('email', $emails)
                        ->where('is_active', true)
                        ->first();

                    if ($directUser) {
                        $user = $directUser;
                        if (! $emailAccount->assigned_user_id) {
                            $emailAccount->assigned_user_id = $directUser->id;
                            $emailAccount->save();
                        }
                    }
                }

                if (! $profile) {
                    // Si el perfil de Facebook no está registrado en el sistema, no se auto-crea ni asocia
                    // automáticamente; se mantiene sin asociación hasta que un administrador lo asigne.
                    return [
                        'profile' => null,
                        'user' => null,
                    ];
                }

                $storedIdentifier = FacebookProfileIdentifier::normalize($profile->facebook_identifier);
                if ($identifiers !== [] && $storedIdentifier === null) {
                    $profile->facebook_identifier = $identifier ?? $uriIdentifier;
                    $profile->save();
                }

                if ($user) {
                    $profile->users()->syncWithoutDetaching([$user->id]);
                    $profile->unsetRelation('users');
                }

                $user = $user ?? $profile->creator ?? $emailAccount->creator;

                return [
                    'profile' => $profile,
                    'user' => $user,
                ];
            }
        }

        // 2. Fallback Resolution via Facebook Identifier or Name
        $facebookProfiles = Profile::query()
            ->with(['users' => function ($q) {
                $q->where('is_active', true)->orderBy('users.id', 'asc');
            }, 'creator'])
            ->whereRaw('LOWER(social_network) = ?', ['facebook']);

        $profile = null;

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

            if ($identifiers !== [] && $storedIdentifier !== null && ! in_array($storedIdentifier, $identifiers, true)) {
                return ['profile' => null, 'user' => null];
            }

            if ($identifiers !== [] && $storedIdentifier === null) {
                $profile->facebook_identifier = $identifier ?? $uriIdentifier;
                $profile->save();
            }
        }

        if (! $profile) {
            return ['profile' => null, 'user' => null];
        }

        // If direct user with export email exists, bind to this profile
        $directUser = null;
        if ($emails !== []) {
            $directUser = User::query()
                ->whereIn('email', $emails)
                ->where('is_active', true)
                ->first();

            if ($directUser) {
                $profile->users()->syncWithoutDetaching([$directUser->id]);
            }
        }

        // Resolve assigned collaborator (User):
        // 1. Direct matched user by email
        // 2. First active assigned collaborator in profile_user
        // 3. Fallback: profile creator (created_by)
        $user = $directUser ?? $profile->users->first() ?? $profile->creator;

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

            $evidences = [];
            $count = 0;
            $shareCount = 0;
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
                    $shareCount++;

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
