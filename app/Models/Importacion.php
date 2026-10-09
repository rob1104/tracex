<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Importacion extends Model
{
    use HasUuids;

    protected $table = 'importaciones';
    protected $guarded = ['id'];
    protected $casts = [
        'nombre_fb'        => 'encrypted',
        'totales'          => 'array',
        'desde'            => 'date',
        'hasta'            => 'date',
        'recibido_en'      => 'datetime',
        'borrado_drive_en' => 'datetime',
        'procesado_en'     => 'datetime',
    ];

    public function usuario(): BelongsTo  { return $this->belongsTo(User::class, 'user_id'); }
    public function perfil(): BelongsTo { return $this->belongsTo(Perfil::class); }

    public function directorioLocal(): string
    {
        return storage_path("app/imports/{$this->id}");
    }
}
