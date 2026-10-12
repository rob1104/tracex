<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['alias', 'email', 'password', 'status', 'created_by'])]
class EmailAccount extends Model
{
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function profiles()
    {
        return $this->hasMany(Profile::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
