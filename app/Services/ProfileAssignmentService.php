<?php

namespace App\Services;

use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;

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

        if ($name === '') {
            return ['profile' => null, 'user' => null];
        }

        $normalizedName = mb_strtolower($name, 'UTF-8');

        // Look for exact case-insensitive match on Facebook profiles
        $matchingProfiles = Profile::query()
            ->with(['users' => function ($q) {
                $q->where('is_active', true)->orderBy('users.id', 'asc');
            }, 'creator'])
            ->whereRaw('LOWER(name) = ?', [$normalizedName])
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->limit(2)
            ->get();

        // If not found or ambiguous (multiple profiles with identical name), return null
        if ($matchingProfiles->count() !== 1) {
            return ['profile' => null, 'user' => null];
        }

        /** @var Profile $profile */
        $profile = $matchingProfiles->first();

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
}
