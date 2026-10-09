<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    public $timestamps = false;
    protected $guarded = ['id', 'hora_local'];
    protected $casts = [
        'texto'    => 'encrypted',
        'contexto' => 'encrypted',
        'fecha'    => 'datetime',
    ];
}
