<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'profile_id',
    'original_file_name',
    'source_path',
    'parsed_path',
    'status',
    'total_entries',
    'recognized_count',
    'unrecognized_count',
    'processed_count',
    'imported_count',
    'duplicate_count',
    'preview_records',
    'preview_unparsed',
    'error_message',
])]
class EvidenceTextImport extends Model
{
    protected function casts(): array
    {
        return [
            'total_entries' => 'integer',
            'recognized_count' => 'integer',
            'unrecognized_count' => 'integer',
            'processed_count' => 'integer',
            'imported_count' => 'integer',
            'duplicate_count' => 'integer',
            'preview_records' => 'array',
            'preview_unparsed' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
