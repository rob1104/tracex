<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perfil extends Model
{
    use HasUuids; // UUID ordenado por tiempo

    protected $table = 'perfiles';
    protected $fillable = ['user_id', 'alias', 'url_fb', 'hash_url', 'hash_fb', 'ultimo_export_en'];
    protected $hidden = ['hash_url', 'hash_fb'];
    protected $casts = [
        'url_fb'           => 'encrypted',
        'ultimo_export_en' => 'datetime',
    ];

    public function usuario(): BelongsTo         { return $this->belongsTo(User::class, 'user_id'); }
    public function importaciones(): HasMany   { return $this->hasMany(Importacion::class); }
    public function comentarios(): HasMany     { return $this->hasMany(Comentario::class); }
    public function reacciones(): HasMany      { return $this->hasMany(Reaccion::class); }
    public function compartidos(): HasMany     { return $this->hasMany(Compartido::class); }
}
