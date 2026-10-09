<?php

namespace App\Services;

use App\Models\Importacion;
use App\Models\Perfil;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Liga una importación (ej. una semana de actividad) a un perfil monitoreado.
 * Lo usan el Job (asignación automática) y la pantalla de perfiles (asignación manual).
 *
 * En una instalación local, cualquier usuario puede asignar cargas
 * a cualquier perfil registrado en ella.
 */
class AsignadorImportaciones
{
    private const TABLAS = ['comentarios', 'reacciones', 'compartidos'];

    /**
     * Al registrar un perfil con su enlace de perfil, toma las cargas que ya habían
     * llegado sin coincidir con nadie y que corresponden a ese enlace.
     * Regresa cuántas cargas se le asignaron.
     */
    public function reclamarSinDueno(Perfil $perfil): int
    {
        $cargas = Importacion::where('estado', 'sin_dueno')
            ->where('hash_url', $perfil->hash_url)
            ->orderBy('recibido_en')
            ->get();

        $asignadas = 0;

        foreach ($cargas as $carga) {
            try {
                $this->asignar($carga, $perfil->refresh());
                $asignadas++;
            } catch (ValidationException $e) {
                $carga->update(['estado' => 'pendiente', 'error' => $e->errors()['perfil'][0] ?? null]);
            }
        }

        return $asignadas;
    }

    /** @return array{nuevos:int, omitidos:int} */
    public function asignar(Importacion $importacion, Perfil $perfil): array
    {
        if ($importacion->hash_fb) {
            // El perfil ya tiene otra cuenta de Facebook ligada
            if ($perfil->hash_fb && $perfil->hash_fb !== $importacion->hash_fb) {
                throw ValidationException::withMessages([
                    'perfil' => "Esta carga es de otra cuenta de Facebook, no de {$perfil->alias}.",
                ]);
            }

            // La cuenta ya está ligada a otro perfil
            $otro = Perfil::where('hash_fb', $importacion->hash_fb)->whereKeyNot($perfil->id)->first();
            if ($otro) {
                throw ValidationException::withMessages([
                    'perfil' => "Esta cuenta de Facebook ya está asignada a {$otro->alias}.",
                ]);
            }
        }

        return DB::transaction(function () use ($importacion, $perfil) {
            // Si llegan dos cargas del mismo perfil al mismo tiempo, la segunda espera
            // a que termine la primera; así ve sus registros y no los duplica.
            $perfil = Perfil::whereKey($perfil->id)->lockForUpdate()->firstOrFail();

            $resultado = ['nuevos' => 0, 'omitidos' => 0];

            foreach (self::TABLAS as $tabla) {
                // Quita de la carga nueva lo que el perfil ya tenía (semanas que se enciman,
                // o el mismo export enviado dos veces). Se cuenta como "ya existía".
                $resultado['omitidos'] += DB::delete(
                    "DELETE n FROM {$tabla} n
                     JOIN {$tabla} o ON o.huella = n.huella AND o.perfil_id = ?
                     WHERE n.importacion_id = ?",
                    [$perfil->id, $importacion->id]
                );

                // Lo que queda es nuevo. El índice único (perfil_id, huella) es la última
                // barrera: si aun así hubiera un repetido, la transacción completa se revierte.
                $resultado['nuevos'] += DB::table($tabla)
                    ->where('importacion_id', $importacion->id)
                    ->update(['perfil_id' => $perfil->id]);
            }

            $perfil->hash_fb ??= $importacion->hash_fb;
            $perfil->ultimo_export_en = now();
            $perfil->save();

            $importacion->update([
                'user_id'   => $perfil->user_id,
                'perfil_id' => $perfil->id,
                'estado'    => $resultado['nuevos'] === 0 ? 'duplicado' : 'asignado',
                'error'     => null,
                'totales'   => array_merge($importacion->totales ?? [], $resultado),
            ]);

            return $resultado;
        });
    }
}
