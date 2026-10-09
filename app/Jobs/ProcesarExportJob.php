<?php

namespace App\Jobs;

use App\Models\Importacion;
use App\Models\Perfil;
use App\Services\AsignadorImportaciones;
use App\Services\FacebookExportParser;
use App\Support\IdentidadFacebook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class ProcesarExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600;

    private const LOTE = 500;
    private const MAX_DESCOMPRIMIDO = 2 * 1024 * 1024 * 1024; // 2 GB, protección contra zip bombs

    public function __construct(public Importacion $importacion) {}

    public function handle(AsignadorImportaciones $asignador): void
    {
        $imp = $this->importacion;
        $dir = $imp->directorioLocal();

        $this->descomprimirZips($dir);

        // Una carpeta con varias exportaciones (ej. Sofía y Juan juntos) se separa
        // en una importación por cuenta, para que nunca se mezclen.
        $partes = $this->partes($dir);
        if (count($partes) > 1) {
            $this->dividir($imp, $partes);

            return;
        }

        $parser = new FacebookExportParser($dir);
        $perfilFb = $parser->perfil();

        // Dos huellas: el ID estable de la cuenta y el enlace del perfil
        // (este último es el que el usuario captura al registrar el perfil)
        $hashFb = IdentidadFacebook::huella($parser->identificador());
        $hashUrl = IdentidadFacebook::huella(IdentidadFacebook::normalizarEnlace($perfilFb['uri']));

        $fechas = [];
        $totales = [];

        DB::transaction(function () use ($imp, $parser, &$fechas, &$totales) {
            // Si es un reintento, limpia lo que haya quedado del intento anterior
            foreach (['comentarios', 'reacciones', 'compartidos'] as $t) {
                DB::table($t)->where('importacion_id', $imp->id)->delete();
            }

            $totales['comentarios'] = $this->insertar('comentarios', $parser->comentarios(), function ($c) use ($imp, &$fechas) {
                $fechas[] = $c['fecha'];
                $texto = trim($c['texto']);

                return [
                    'importacion_id' => $imp->id,
                    'texto'          => Crypt::encryptString($texto),
                    'contexto'       => $c['contexto'] ? Crypt::encryptString($c['contexto']) : null,
                    'fecha'          => $c['fecha'],
                    'longitud'       => min(mb_strlen($texto), 65535),
                    'num_palabras'   => $texto === '' ? 0 : count(preg_split('/\s+/u', $texto)),
                    'huella'         => sha1("c|{$c['fecha']}|{$texto}"),
                ];
            });

            $totales['reacciones'] = $this->insertar('reacciones', $parser->reacciones(), function ($r) use ($imp, &$fechas) {
                $fechas[] = $r['fecha'];

                return [
                    'importacion_id' => $imp->id,
                    'tipo'           => $r['tipo'],
                    'objetivo'       => $r['objetivo'] ? Crypt::encryptString($r['objetivo']) : null,
                    'fecha'          => $r['fecha'],
                    // Sin el título: cambia según el idioma del export y haría ver distinta la misma reacción
                    'huella'         => sha1("r|{$r['fecha']}|{$r['tipo']}"),
                ];
            });

            $totales['compartidos'] = $this->insertar('compartidos', $parser->compartidos(), function ($s) use ($imp, &$fechas) {
                $fechas[] = $s['fecha'];

                return [
                    'importacion_id' => $imp->id,
                    'descripcion'    => $s['descripcion'] ? Crypt::encryptString($s['descripcion']) : null,
                    'enlace'         => $s['enlace'] ? Crypt::encryptString($s['enlace']) : null,
                    'fecha'          => $s['fecha'],
                    'huella'         => sha1("s|{$s['fecha']}|" . ($s['enlace'] ?? $s['descripcion'])),
                ];
            });
        });

        $imp->update([
            'hash_fb'      => $hashFb,
            'hash_url'     => $hashUrl,
            'nombre_fb'    => $perfilFb['nombre'],
            'desde'        => $fechas ? substr(min($fechas), 0, 10) : null,
            'hasta'        => $fechas ? substr(max($fechas), 0, 10) : null,
            'totales'      => $totales,
            'estado'       => 'sin_dueno',
            'procesado_en' => now(),
            'error'        => ($hashFb || $hashUrl) ? null
                : 'El export no incluye la información del perfil; no se puede saber de quién es.',
        ]);

        $this->asignarAutomaticamente($imp, $asignador);

        File::deleteDirectory($dir);
    }

    public function failed(Throwable $e): void
    {
              $this->importacion->update(['estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }

    /**
     * - Se busca el perfil por el ID ya fijado de su cuenta y, si no, por el enlace de perfil
     *   que capturó el usuario → se asigna sola.
     * - Si no coincide con ningún perfil, queda "sin_dueno": aparece en "Cargas por asignar"
     *   y se reclama sola si después se registra ese perfil con su enlace.
     */
    private function asignarAutomaticamente(Importacion $imp, AsignadorImportaciones $asignador): void
    {
        $perfil = ($imp->hash_fb ? Perfil::where('hash_fb', $imp->hash_fb)->first() : null)
            ?? ($imp->hash_url ? Perfil::where('hash_url', $imp->hash_url)->first() : null);

        if (! $perfil) {
            return;
        }

        try {
            $asignador->asignar($imp, $perfil);
        } catch (ValidationException $e) {
            // Ej. el enlace coincide pero la cuenta es otra: se revisa a mano
            $imp->update(['estado' => 'pendiente', 'error' => $e->errors()['perfil'][0] ?? null]);
        }
    }

    /**
     * Inserta en lotes ignorando filas repetidas dentro de la misma carga
     * (índice único importacion_id + huella). Regresa cuántas filas se guardaron.
     */
    private function insertar(string $tabla, iterable $filas, callable $mapear): int
    {
        $lote = [];
        $total = 0;

        foreach ($filas as $fila) {
            $lote[] = $mapear($fila);

            if (count($lote) === self::LOTE) {
                $total += DB::table($tabla)->insertOrIgnore($lote);
                $lote = [];
            }
        }

        if ($lote) {
            $total += DB::table($tabla)->insertOrIgnore($lote);
        }

        return $total;
    }

    /**
     * Detecta cuántas exportaciones hay en la carpeta.
     * - Si en la raíz están las carpetas típicas del export, es una sola.
     * - Si hay varias subcarpetas (o ZIPs ya descomprimidos) y cada una trae
     *   archivos de Facebook, cada una es una exportación distinta.
     */
    private function partes(string $dir): array
    {
        $subcarpetas = File::directories($dir);

        $carpetasDelExport = ['your_facebook_activity', 'connections', 'personal_information',
            'comments_and_reactions', 'friends_and_followers', 'posts', 'profile_information'];

        foreach ($subcarpetas as $sub) {
            if (in_array(basename($sub), $carpetasDelExport, true)) {
                return [$dir];
            }
        }

        $conDatos = array_values(array_filter($subcarpetas, function ($sub) {
            foreach (File::allFiles($sub) as $archivo) {
                if (preg_match('/^(profile_information|comments.*|likes_and_reactions.*|your_posts.*|your_friends)\.json$/', $archivo->getFilename())) {
                    return true;
                }
            }

            return false;
        }));

        return count($conDatos) > 1 ? $conDatos : [$dir];
    }

    /** Crea una importación por exportación encontrada y las encola por separado. */
    private function dividir(Importacion $imp, array $partes): void
    {
        foreach ($partes as $i => $ruta) {
            $parte = Importacion::create([
                'user_id'          => $imp->user_id,
                'drive_folder_id'  => "{$imp->drive_folder_id}#" . ($i + 1),
                'nombre_carpeta'   => "{$imp->nombre_carpeta} / " . basename($ruta),
                'recibido_en'      => $imp->recibido_en,
                'estado' => 'en_cola',
            ]);

            File::moveDirectory($ruta, $parte->directorioLocal());
            self::dispatch($parte);
        }

        $imp->update(['estado' => 'dividida', 'totales' => ['partes' => count($partes)]]);
    }

    private function descomprimirZips(string $dir): void
    {
        foreach (File::allFiles($dir) as $archivo) {
            if (strtolower($archivo->getExtension()) !== 'zip') {
                continue;
            }

            $zip = new ZipArchive();
            if ($zip->open($archivo->getPathname()) !== true) {
                throw new RuntimeException("No se pudo abrir {$archivo->getFilename()}");
            }

            $tamano = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $tamano += $zip->statIndex($i)['size'];
            }
            if ($tamano > self::MAX_DESCOMPRIMIDO) {
                $zip->close();
                throw new RuntimeException("{$archivo->getFilename()} es demasiado grande al descomprimir.");
            }

            $zip->extractTo($archivo->getPath() . '/' . $archivo->getFilenameWithoutExtension());
            $zip->close();
            File::delete($archivo->getPathname());
        }
    }
}
