<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'drive_file_id',
    'file_name',
    'file_size_bytes',
    'status',
    'downloaded_at',
    'drive_deleted_at',
    'total_comments_extracted',
    'total_reactions_extracted',
    'error_message',
    'metadata',
])]
class FacebookImportLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
            'drive_deleted_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'total_comments_extracted' => 'integer',
            'total_reactions_extracted' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function facebookUsers(): HasMany
    {
        return $this->hasMany(FacebookUser::class, 'import_log_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FacebookComment::class, 'import_log_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FacebookReaction::class, 'import_log_id');
    }
}
