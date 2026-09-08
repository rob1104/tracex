<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;

class EvidencePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin() && in_array($ability, ['viewAny', 'view', 'update', 'delete', 'forceDelete', 'restore'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isUser();
    }

    public function view(User $user, Evidence $evidence): bool
    {
        return $user->id === $evidence->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isUser();
    }

    public function update(User $user, Evidence $evidence): bool
    {
        return false;
    }

    public function delete(User $user, Evidence $evidence): bool
    {
        return false;
    }

    public function restore(User $user, Evidence $evidence): bool
    {
        return false;
    }

    public function forceDelete(User $user, Evidence $evidence): bool
    {
        return false;
    }
}
