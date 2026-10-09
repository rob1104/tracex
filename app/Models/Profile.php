<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $fillable = ['email_account_id', 'name', 'password', 'social_network', 'status', 'created_by'];
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function facebookUsers(): HasMany
    {
        return $this->hasMany(FacebookUser::class);
    }
}
