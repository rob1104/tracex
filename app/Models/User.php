<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'supervisor_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function evidences()
    {
        return $this->hasMany(Evidence::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function canMonitorCollaborators(): bool
    {
        return $this->isAdmin() || $this->isSupervisor();
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isCuentas(): bool
    {
        return $this->role === 'cuentas';
    }

    public function assignedProfiles()
    {
        return $this->belongsToMany(Profile::class);
    }

    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class, 'created_by');
    }

    public function createdProfiles()
    {
        return $this->hasMany(Profile::class, 'created_by');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function monitoredCollaborators(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id')->where('role', 'user');
    }

    /**
     * Return the IDs of collaborators assigned to this supervisor, or null for unrestricted roles.
     *
     * @return list<int>|null
     */
    public function monitoredCollaboratorIds(): ?array
    {
        if (! $this->isSupervisor()) {
            return null;
        }

        $ids = $this->monitoredCollaborators()
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        return $ids !== [] ? $ids : [0];
    }

    public function hasAssignedCollaborators(): bool
    {
        return $this->isSupervisor() && $this->monitoredCollaborators()->exists();
    }

    public function scopeForAdminHierarchy(Builder $query, ?self $actor = null): Builder
    {
        $targetUser = $actor ?? auth()->user();
        $collaboratorIds = $targetUser?->monitoredCollaboratorIds();

        if ($collaboratorIds !== null) {
            $query->whereIn($query->qualifyColumn('id'), $collaboratorIds);
        }

        return $query;
    }
}
