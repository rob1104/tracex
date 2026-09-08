<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'evidence_type_id', 'social_network', 'comment', 'ip_address', 'user_agent'])]
class Evidence extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evidenceType()
    {
        return $this->belongsTo(EvidenceType::class);
    }

    public function images()
    {
        return $this->hasMany(EvidenceImage::class);
    }
}
