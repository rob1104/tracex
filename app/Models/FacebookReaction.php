<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'facebook_user_id',
    'import_log_id',
    'reaction_type',
    'published_at',
    'title',
    'target_url',
    'metadata',
])]
class FacebookReaction extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function facebookUser(): BelongsTo
    {
        return $this->belongsTo(FacebookUser::class, 'facebook_user_id');
    }

    public function importLog(): BelongsTo
    {
        return $this->belongsTo(FacebookImportLog::class, 'import_log_id');
    }

    public function scopeBetweenDates(Builder $query, \DateTimeInterface|string $from, \DateTimeInterface|string $to): Builder
    {
        return $query->whereBetween('published_at', [$from, $to]);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('reaction_type', $type);
    }
}
