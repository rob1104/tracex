<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'facebook_user_id',
    'import_log_id',
    'content',
    'character_count',
    'word_count',
    'published_at',
    'post_url',
    'metadata',
])]
class FacebookComment extends Model
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
            'character_count' => 'integer',
            'word_count' => 'integer',
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
}
