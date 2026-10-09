<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compartido extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = [
        'descripcion' => 'encrypted',
        'enlace'      => 'encrypted',
        'fecha'       => 'datetime',
    ];
}
