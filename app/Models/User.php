<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];
    protected $hidden = ['password', 'remember_token'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function evidences()
    {
        return $this->hasMany(Evidence::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isCuentas(): bool
    {
        return $this->role === 'cuentas';
    }

    public function assignedProfiles()
    {
        return $this->belongsToMany(Profile::class);
    }

    /** Perfiles de usuarios monitoreados por el módulo de actividad. */
    public function usuariosMonitoreados()
    {
        return $this->hasMany(Perfil::class, 'user_id');
    }

    public function emailAccounts()
    {
        return $this->hasMany(EmailAccount::class, 'created_by');
    }

    public function createdProfiles()
    {
        return $this->hasMany(Profile::class, 'created_by');
    }
}
