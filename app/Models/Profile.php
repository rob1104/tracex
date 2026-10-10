<?php

namespace App\Models;

use App\Services\FacebookProfileIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['email_account_id', 'name', 'password', 'facebook_identifier', 'social_network', 'status', 'created_by'])]
class Profile extends Model
{
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function setFacebookIdentifierAttribute(?string $value): void
    {
        $this->attributes['facebook_identifier'] = FacebookProfileIdentifier::normalize($value);
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

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }
}
