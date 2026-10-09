<?php

namespace App\Services;

use Carbon\Carbon;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Lee la carpeta de un export de Facebook (JSON) y devuelve filas planas.
 * Busca los archivos por nombre, sin depender de la ruta exacta,
 * porque Facebook cambia la estructura de carpetas entre versiones.
 */
class FacebookExportParser
{
    private const ZONA = 'America/Mexico_City';

    private const REACCIONES = [
        'LIKE'  => 'me_gusta',
        'LOVE'  => 'me_encanta',
        'CARE'  => 'me_importa',
        'HAHA'  => 'me_divierte',
        'WOW'   => 'me_asombra',
        'SORRY' => 'me_entristece',
        'ANGER' => 'me_enoja',
    ];

    public function __construct(private string $directorio) {}

    /** Nombre y URL del perfil, si el export incluye la información del perfil. */
    public function perfil(): array
    {
        $p = $this->datosPerfil();

        return [
            'nombre' => $p['name']['full_name'] ?? null,
            'uri'    => $p['profile_uri'] ?? null,
        ];
    }

    /**
     * Identificador estable de la cuenta, sacado de adentro del export
     * (el nombre de la carpeta "meta-..." solo trae la fecha).
     *  1. ID numérico, si la URL del perfil es profile.php?id=...
     *  2. Fecha de creación de la cuenta (no cambia aunque cambie el usuario o el nombre)
     *  3. URL del perfil, como último recurso
     * Regresa null si el export no incluye la información del perfil.
     */
    public function identificador(): ?string
    {
        $p = $this->datosPerfil();
        $uri = $p['profile_uri'] ?? null;

        if ($uri && preg_match('/[?&]id=(\d+)/', $uri, $m)) {
            return "id:{$m[1]}";
        }

        if (! empty($p['registration_timestamp'])) {
            return "registro:{$p['registration_timestamp']}";
        }

        return $uri ? "uri:{$uri}" : null;
    }

    private ?array $perfilCache = null;

    private function datosPerfil(): array
    {
        if ($this->perfilCache !== null) {
            return $this->perfilCache;
        }

        foreach ($this->archivos('/^profile_information\.json$/') as $ruta) {
            $datos = $this->leer($ruta);

            return $this->perfilCache = $datos['profile_v2'] ?? $datos['profile'] ?? [];
        }

        return $this->perfilCache = [];
    }

    public function comentarios(): iterable
    {
        foreach ($this->archivos('/^comments.*\.json$/') as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['comments_v2', 'comments']) as $c) {
                $comentario = $this->primero($c['data'] ?? [], 'comment');
                $texto = $comentario['comment'] ?? null;
                $ts = $comentario['timestamp'] ?? $c['timestamp'] ?? null;

                if ($texto === null || $ts === null) {
                    continue; // comentarios solo con foto o sticker
                }

                yield [
                    'texto'    => $texto,
                    'contexto' => $c['title'] ?? null,
                    'fecha'    => $this->fecha($ts),
                ];
            }
        }
    }

    public function reacciones(): iterable
    {
        foreach ($this->archivos('/^(likes_and_reactions|reactions).*\.json$/') as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['reactions_v2', 'reactions']) as $r) {
                if (! isset($r['timestamp'])) {
                    continue;
                }
                $codigo = $this->primero($r['data'] ?? [], 'reaction')['reaction'] ?? 'LIKE';

                yield [
                    'tipo'     => self::REACCIONES[strtoupper($codigo)] ?? mb_strtolower($codigo),
                    'objetivo' => $r['title'] ?? null,
                    'fecha'    => $this->fecha($r['timestamp']),
                ];
            }
        }
    }

    public function compartidos(): iterable
    {
        foreach ($this->archivos('/^(your_posts|posts).*\.json$/') as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['status_updates_v2', 'posts']) as $p) {
                if (! isset($p['timestamp'])) {
                    continue;
                }
                $enlace = $this->buscarEnlace($p['attachments'] ?? []);
                $titulo = $p['title'] ?? '';

                // Solo lo que es compartido (enlace externo o título "compartió")
                if (! $enlace && ! preg_match('/compart|shared/iu', $titulo)) {
                    continue;
                }

                yield [
                    'descripcion' => $this->primero($p['data'] ?? [], 'post')['post'] ?? ($titulo ?: null),
                    'enlace'      => $enlace,
                    'fecha'       => $this->fecha($p['timestamp']),
                ];
            }
        }
    }

    private function archivos(string $patron): array
    {
        $encontrados = [];
        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directorio, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && preg_match($patron, $archivo->getFilename())) {
                $encontrados[] = $archivo->getPathname();
            }
        }
        sort($encontrados);

        return $encontrados;
    }

    private function leer(string $ruta): array
    {
        $datos = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);

        return $this->arreglarCodificacion($datos);
    }

    /**
     * Facebook escribe UTF-8 como si fuera Latin-1 escapado ("cafÃ©").
     * Reinterpretar los bytes lo convierte de vuelta en "café".
     */
    private function arreglarCodificacion(mixed $valor): mixed
    {
        if (is_array($valor)) {
            return array_map(fn ($v) => $this->arreglarCodificacion($v), $valor);
        }

        if (is_string($valor)) {
            $corregido = mb_convert_encoding($valor, 'ISO-8859-1', 'UTF-8');

            return mb_check_encoding($corregido, 'UTF-8') ? $corregido : $valor;
        }

        return $valor;
    }

    /** El archivo puede ser una lista directa o un objeto con la lista adentro. */
    private function lista(array $datos, array $llaves): array
    {
        foreach ($llaves as $llave) {
            if (isset($datos[$llave]) && is_array($datos[$llave])) {
                return $datos[$llave];
            }
        }

        return array_is_list($datos) ? $datos : [];
    }

    private function primero(array $data, string $llave): array
    {
        foreach ($data as $item) {
            if (isset($item[$llave])) {
                return is_array($item[$llave]) ? $item[$llave] : [$llave => $item[$llave]];
            }
        }

        return [];
    }

    private function buscarEnlace(array $adjuntos): ?string
    {
        foreach ($adjuntos as $adjunto) {
            foreach ($adjunto['data'] ?? [] as $d) {
                if (! empty($d['external_context']['url'])) {
                    return $d['external_context']['url'];
                }
            }
        }

        return null;
    }

    private function fecha(int|string $timestamp): string
    {
        return Carbon::createFromTimestamp((int) $timestamp, self::ZONA)->format('Y-m-d H:i:s');
    }
}
