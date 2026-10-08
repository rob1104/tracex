<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['profile_id', 'import_log_id', 'age_range', 'joined_at', 'metadata'])]
class FacebookUser extends Model
{
    use HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function importLog(): BelongsTo
    {
        return $this->belongsTo(FacebookImportLog::class, 'import_log_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FacebookComment::class, 'facebook_user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FacebookReaction::class, 'facebook_user_id');
    }
}
