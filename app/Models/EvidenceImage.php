<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['evidence_id', 'screenshot_path', 'screenshot_hash', 'screenshot_mime', 'screenshot_size', 'is_suspect'])]
class EvidenceImage extends Model
{
    protected function casts(): array
    {
        return [
            'is_suspect' => 'boolean',
        ];
    }

    public function evidence()
    {
        return $this->belongsTo(Evidence::class);
    }
}
