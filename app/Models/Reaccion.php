<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reaccion extends Model
{
    public $timestamps = false;
    protected $table = 'reacciones';
    protected $guarded = ['id'];
    protected $casts = [
        'objetivo' => 'encrypted',
        'fecha'    => 'datetime',
    ];

    // Etiquetas para mostrar en la vista
    public const ETIQUETAS = [
        'me_gusta'     => 'Me gusta',
        'me_encanta'   => 'Me encanta',
        'me_importa'   => 'Me importa',
        'me_divierte'  => 'Me divierte',
        'me_asombra'   => 'Me asombra',
        'me_entristece'=> 'Me entristece',
        'me_enoja'     => 'Me enoja',
    ];
}
