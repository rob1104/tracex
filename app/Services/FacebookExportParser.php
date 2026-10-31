<?php

namespace App\Services;

use Carbon\Carbon;
use FilesystemIterator;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Lee la carpeta de un export de Facebook (JSON) y devuelve filas planas.
 * Busca los archivos por nombre, sin depender de la ruta exacta,
 * porque Facebook cambia la estructura de carpetas entre versiones.
 */
class FacebookExportParser
{
    private const ZONA = 'America/Mexico_City';

    private const PATRON_PERFIL = '/^profile_information\.json$/i';

    private const PATRON_COMENTARIOS = '/^(comments|group_comments|your_comments_in_groups).*\.json$/i';

    private const PATRON_REACCIONES = '/^(likes_and_reactions|reactions).*\.json$/i';

    private const PATRON_COMPARTIDOS = '/^(your_posts|posts|group_posts|your_posts_in_groups|shared_memories).*\.json$/i';

    private const PATRON_ACTIVIDAD = '/^your_comment_active_days\.json$/i';

    private const PATRON_AUXILIARES = '/^(profile_update_history|your_devices|the_ways_we_can_send_you_notifications|permissions_you_have_granted_to_apps|rising_fan_badges.*|account_activity|login_protection_data|recently_viewed|time_spent|content_that_has_been_shown_to_you_in_your_feed|your_notifications_tab_activity|your_post_audiences|suggested_friends|your_facebook_activity_history|device_login_cookies|information_about_your_last_login)\.json$/i';

    private const REACCIONES = [
        'LIKE' => 'LIKE',
        'ME GUSTA' => 'LIKE',
        'LOVE' => 'LOVE',
        'ME ENCANTA' => 'LOVE',
        'CARE' => 'CARE',
        'ME IMPORTA' => 'CARE',
        'HAHA' => 'HAHA',
        'ME DIVIERTE' => 'HAHA',
        'WOW' => 'WOW',
        'ME ASOMBRA' => 'WOW',
        'SORRY' => 'SORRY',
        'SAD' => 'SORRY',
        'ME ENTRISTECE' => 'SORRY',
        'ANGER' => 'ANGER',
        'ANGRY' => 'ANGER',
        'ME ENOJA' => 'ANGER',
    ];

    public function __construct(
        private string $directorio,
        private ?string $nombreExportacion = null
    ) {}

    /**
     * Valida que la estructura del directorio de exportación de Facebook sea válida y reconocida.
     * Lanza una excepción si faltan archivos esperados, si el JSON no es válido o si el formato ha cambiado.
     *
     * @throws RuntimeException
     */
    public function validarEstructura(): void
    {
        if (! is_dir($this->directorio)) {
            throw new RuntimeException("El directorio de exportación no existe: {$this->directorio}");
        }

        $archivosPerfil = $this->archivos(self::PATRON_PERFIL);
        $archivosComentarios = $this->archivos(self::PATRON_COMENTARIOS);
        $archivosReacciones = $this->archivos(self::PATRON_REACCIONES);
        $archivosCompartidos = $this->archivos(self::PATRON_COMPARTIDOS);
        $archivosActividad = $this->archivos(self::PATRON_ACTIVIDAD);
        $archivosAuxiliaresMeta = $this->archivos(self::PATRON_AUXILIARES);
        $archivosJsonTotales = $this->archivos('/\.json$/');
        $tieneIdEnEstructura = $this->extraerIdDesdeEstructura() !== null;

        $totalArchivosReconocidos = count($archivosPerfil)
            + count($archivosComentarios)
            + count($archivosReacciones)
            + count($archivosCompartidos)
            + count($archivosActividad)
            + count($archivosAuxiliaresMeta);

        if ($totalArchivosReconocidos === 0 && ! ($tieneIdEnEstructura && count($archivosJsonTotales) > 0)) {
            $todosLosArchivos = array_map('basename', $this->archivos('/.*/'));

            Log::warning('[FacebookExportParser] No recognized Facebook JSON files found in export directory', [
                'directory' => $this->directorio,
                'export_name' => $this->nombreExportacion,
                'all_files_present_in_directory' => $todosLosArchivos,
            ]);

            throw new RuntimeException("Estructura de exportación inválida: no se encontraron archivos reconocidos de Facebook en {$this->directorio}.");
        }

        if ($totalArchivosReconocidos === 0) {
            foreach ($archivosJsonTotales as $ruta) {
                $this->leer($ruta);
            }
        }

        Log::info('[FacebookExportParser] Validating recognized Facebook export files', [
            'directory' => $this->directorio,
            'profile_files' => array_map('basename', $archivosPerfil),
            'comment_files' => array_map('basename', $archivosComentarios),
            'reaction_files' => array_map('basename', $archivosReacciones),
            'share_files' => array_map('basename', $archivosCompartidos),
        ]);

        foreach ($archivosPerfil as $ruta) {
            $datos = $this->leer($ruta);
            $tienePerfil = isset($datos['profile_v2'])
                || isset($datos['profile'])
                || isset($datos['name'])
                || isset($datos['profile_uri'])
                || isset($datos['registration_timestamp'])
                || isset($datos['label_values'])
                || isset($datos['fbid']);

            if (! $tienePerfil) {
                throw new RuntimeException('Formato JSON no reconocido en el archivo de perfil: '.basename($ruta));
            }
        }

        foreach ($archivosComentarios as $ruta) {
            $datos = $this->leer($ruta);
            $esValido = isset($datos['comments_v2'])
                || isset($datos['comments'])
                || isset($datos['group_comments_v2'])
                || isset($datos['group_comments'])
                || isset($datos['label_values'])
                || array_is_list($datos);

            if (! $esValido) {
                throw new RuntimeException('Formato JSON no reconocido en el archivo de comentarios: '.basename($ruta));
            }
        }

        foreach ($archivosReacciones as $ruta) {
            $datos = $this->leer($ruta);
            $esValido = isset($datos['reactions_v2'])
                || isset($datos['reactions'])
                || isset($datos['label_values'])
                || array_is_list($datos);

            if (! $esValido) {
                throw new RuntimeException('Formato JSON no reconocido en el archivo de reacciones: '.basename($ruta));
            }
        }

        foreach ($archivosCompartidos as $ruta) {
            $datos = $this->leer($ruta);
            $esValido = isset($datos['status_updates_v2'])
                || isset($datos['posts'])
                || isset($datos['group_posts_v2'])
                || isset($datos['group_posts'])
                || isset($datos['shared_memories_v2'])
                || isset($datos['shared_memories'])
                || isset($datos['label_values'])
                || array_is_list($datos);

            if (! $esValido) {
                throw new RuntimeException('Formato JSON no reconocido en el archivo de publicaciones: '.basename($ruta));
            }
        }
    }

    /** Nombre y URL del perfil, y correos asociados si el export incluye la información del perfil. */
    public function perfil(): array
    {
        $p = $this->datosPerfil();

        $emails = [];

        if (! empty($p['emails'])) {
            if (is_array($p['emails'])) {
                foreach (['emails', 'previous_emails', 'pending_emails', 'ad_account_emails'] as $key) {
                    if (! empty($p['emails'][$key]) && is_array($p['emails'][$key])) {
                        foreach ($p['emails'][$key] as $email) {
                            if (is_string($email)) {
                                $trimmed = mb_strtolower(trim($email));
                                if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                                    $emails[] = $trimmed;
                                }
                            }
                        }
                    }
                }

                foreach ($p['emails'] as $item) {
                    if (is_string($item)) {
                        $trimmed = mb_strtolower(trim($item));
                        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                            $emails[] = $trimmed;
                        }
                    }
                }
            } elseif (is_string($p['emails'])) {
                $trimmed = mb_strtolower(trim($p['emails']));
                if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $trimmed;
                }
            }
        }

        if (! empty($p['email']) && is_string($p['email'])) {
            $trimmed = mb_strtolower(trim($p['email']));
            if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $trimmed;
            }
        }

        return [
            'nombre' => $p['name']['full_name'] ?? null,
            'uri' => $p['profile_uri'] ?? null,
            'emails' => array_values(array_unique($emails)),
        ];
    }

    /**
     * Identificador estable de la cuenta, sacado de adentro del export
     * (el nombre de la carpeta "meta-..." solo trae la fecha).
     *  1. ID numérico, si la URL del perfil es profile.php?id=...
     *  2. ID numérico extraído de la carpeta facebook-<id>-... o del fbid de actividad
     *  3. Fecha de creación de la cuenta (no cambia aunque cambie el usuario o el nombre)
     *  4. URL del perfil, como último recurso
     * Regresa null si el export no incluye la información del perfil.
     */
    public function identificador(): ?string
    {
        $p = $this->datosPerfil();
        $uri = $p['profile_uri'] ?? null;

        if ($uri && preg_match('/[?&]id=(\d+)/', $uri, $m)) {
            return "id:{$m[1]}";
        }

        $idEstructura = $this->extraerIdDesdeEstructura();
        if ($idEstructura !== null) {
            return "id:{$idEstructura}";
        }

        if (! empty($p['registration_timestamp'])) {
            return "registro:{$p['registration_timestamp']}";
        }

        return $uri ? "uri:{$uri}" : null;
    }

    private function extraerIdDesdeEstructura(): ?string
    {
        $fuentes = array_filter([
            $this->nombreExportacion,
            $this->directorio,
            ...$this->archivos('/\.json$/'),
        ]);

        foreach ($fuentes as $fuente) {
            if (preg_match('/facebook-(\d{5,})(?:[-_\/\\\\]|$)/i', (string) $fuente, $m)) {
                return $m[1];
            }
        }

        foreach ($this->archivos(self::PATRON_ACTIVIDAD) as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['comment_active_days']) as $item) {
                $fbid = trim((string) ($item['fbid'] ?? ''));
                if ($fbid !== '' && ctype_digit($fbid)) {
                    return $fbid;
                }
            }
        }

        return null;
    }

    private ?array $perfilCache = null;

    private function datosPerfil(): array
    {
        if ($this->perfilCache !== null) {
            return $this->perfilCache;
        }

        foreach (array_reverse($this->archivos(self::PATRON_PERFIL)) as $ruta) {
            $datos = $this->leer($ruta);

            return $this->perfilCache = $datos['profile_v2'] ?? $datos['profile'] ?? [];
        }

        return $this->perfilCache = [];
    }

    public function comentarios(): iterable
    {
        $vistos = [];

        foreach ($this->archivos(self::PATRON_COMENTARIOS) as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['comments_v2', 'comments', 'group_comments_v2', 'group_comments']) as $c) {
                $comentario = $this->primero($c['data'] ?? [], 'comment');
                $etiquetas = is_array($c['label_values'] ?? null) ? $c['label_values'] : [];
                $texto = $comentario['comment']
                    ?? $this->valorDeEtiquetas($etiquetas, ['Comentario', 'Comment', 'Texto', 'Text']);
                $ts = $comentario['timestamp'] ?? $c['timestamp'] ?? null;

                if ($ts === null) {
                    continue;
                }

                $urlAdjunto = $this->buscarEnlace($c['attachments'] ?? [])
                    ?? $this->valorDeEtiquetas($etiquetas, ['URL', 'Enlace', 'Link', 'Media', 'Adjunto'], incluirHref: true);
                $tieneAdjunto = ! empty($c['attachments']) || ! empty($comentario['media']) || $urlAdjunto !== null;

                if ($texto === null || trim((string) $texto) === '') {
                    if (! $tieneAdjunto) {
                        continue;
                    }

                    $texto = $urlAdjunto !== null
                        ? "[Comentario multimedia / enlace] {$urlAdjunto}"
                        : '[Comentario con imagen o sticker]';
                }

                $contexto = $c['title']
                    ?? $this->valorDeEtiquetas($etiquetas, ['Título', 'Titulo', 'Title', 'Grupo', 'Group', 'Nombre', 'Name']);

                $fbid = trim((string) ($c['fbid'] ?? ''));
                $llave = "{$ts}|{$texto}|".($contexto ?? '')."|{$fbid}";
                if (isset($vistos[$llave])) {
                    continue;
                }
                $vistos[$llave] = true;

                yield [
                    'texto' => $texto,
                    'contexto' => $contexto,
                    'fecha' => $this->fecha($ts),
                ];
            }
        }
    }

    public function reacciones(): iterable
    {
        $vistos = [];

        foreach ($this->archivos(self::PATRON_REACCIONES) as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['reactions_v2', 'reactions']) as $r) {
                if (! isset($r['timestamp'])) {
                    continue;
                }

                $reactionItem = $this->primero($r['data'] ?? [], 'reaction');
                $etiquetas = is_array($r['label_values'] ?? null) ? $r['label_values'] : [];

                $codigo = $reactionItem['reaction']
                    ?? $this->valorDeEtiquetas($etiquetas, ['Reacción', 'Reaccion', 'Reaction']);

                // Omitir registros incompletos cuando falta el tipo de reacción (no asignar LIKE por defecto)
                if (! is_string($codigo) || trim($codigo) === '') {
                    continue;
                }

                $codigoLimpio = trim($codigo);
                $claveReaccion = mb_strtoupper($codigoLimpio, 'UTF-8');
                $tipo = self::REACCIONES[$claveReaccion] ?? self::REACCIONES[strtoupper($codigoLimpio)] ?? $codigoLimpio;

                if ($tipo === '') {
                    continue;
                }

                $objetivo = $r['title'] ?? null;
                if (! is_string($objetivo) || trim($objetivo) === '') {
                    $objetivo = $this->valorDeEtiquetas($etiquetas, ['Título', 'Titulo', 'Title'])
                        ?? $this->valorDeEtiquetas($etiquetas, ['Nombre', 'Name', 'Autor', 'Author', 'Grupo', 'Group']);
                }

                $url = $this->valorDeEtiquetas($etiquetas, ['URL', 'Enlace', 'Link'], incluirHref: true);
                $fbid = trim((string) ($r['fbid'] ?? ''));

                $llave = "{$r['timestamp']}|{$tipo}|".($objetivo ?? '').'|'.($url ?? '')."|{$fbid}";
                if (isset($vistos[$llave])) {
                    continue;
                }
                $vistos[$llave] = true;

                yield [
                    'tipo' => $tipo,
                    'objetivo' => $objetivo,
                    'url' => $url,
                    'fecha' => $this->fecha($r['timestamp']),
                ];
            }
        }
    }

    public function compartidos(): iterable
    {
        $vistos = [];

        foreach ($this->archivos(self::PATRON_COMPARTIDOS) as $ruta) {
            foreach ($this->lista($this->leer($ruta), ['status_updates_v2', 'posts', 'group_posts_v2', 'group_posts', 'shared_memories_v2', 'shared_memories']) as $p) {
                if (! isset($p['timestamp'])) {
                    continue;
                }

                $etiquetas = is_array($p['label_values'] ?? null) ? $p['label_values'] : [];
                $enlace = $this->buscarEnlace($p['attachments'] ?? [])
                    ?? $this->valorDeEtiquetas($etiquetas, ['URL', 'Enlace', 'Link', 'URI'], incluirHref: true);
                $titulo = $p['title']
                    ?? $this->valorDeEtiquetas($etiquetas, ['Título', 'Titulo', 'Title', 'Grupo', 'Group', 'Nombre', 'Name'])
                    ?? '';
                $textoPost = $this->primero($p['data'] ?? [], 'post')['post']
                    ?? $this->valorDeEtiquetas($etiquetas, ['Publicación', 'Publicacion', 'Post', 'Descripción', 'Descripcion', 'Texto', 'Text']);

                $tieneContenido = ($textoPost !== null && trim((string) $textoPost) !== '')
                    || ! empty($p['attachments'])
                    || ! empty($p['fbid']);

                // Capturar enlaces compartidos, publicaciones con título de compartido o publicaciones con contenido/adjuntos
                if (! $enlace && ! preg_match('/compart|shared/iu', (string) $titulo) && ! $tieneContenido) {
                    continue;
                }

                $descripcion = ($textoPost !== null && trim((string) $textoPost) !== '')
                    ? trim((string) $textoPost)
                    : ($titulo !== '' ? $titulo : null);

                $fbid = trim((string) ($p['fbid'] ?? ''));
                $llave = "{$p['timestamp']}|".($enlace ?? '').'|'.($descripcion ?? '')."|{$fbid}";
                if (isset($vistos[$llave])) {
                    continue;
                }
                $vistos[$llave] = true;

                yield [
                    'descripcion' => $descripcion,
                    'enlace' => $enlace,
                    'fecha' => $this->fecha($p['timestamp']),
                ];
            }
        }
    }

    /**
     * Extrae el primer valor no vacío de una estructura label_values de Meta.
     *
     * @param  array<int, mixed>  $etiquetas
     * @param  array<int, string>  $nombresBuscados
     */
    private function valorDeEtiquetas(array $etiquetas, array $nombresBuscados, bool $incluirHref = false): ?string
    {
        $buscadosNormalizados = array_map(
            fn (string $nombre): string => mb_strtolower(trim($nombre), 'UTF-8'),
            $nombresBuscados
        );

        foreach ($etiquetas as $item) {
            if (! is_array($item)) {
                continue;
            }

            $etiqueta = mb_strtolower(trim((string) ($item['label'] ?? '')), 'UTF-8');
            if (! in_array($etiqueta, $buscadosNormalizados, true)) {
                continue;
            }

            $valor = isset($item['value']) && is_string($item['value']) ? trim($item['value']) : '';
            if ($valor !== '') {
                return $valor;
            }

            if ($incluirHref && isset($item['href']) && is_string($item['href']) && trim($item['href']) !== '') {
                return trim($item['href']);
            }
        }

        return null;
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
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer el archivo: {$ruta}");
        }

        try {
            $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Error al decodificar JSON en '.basename($ruta).': '.$e->getMessage(), 0, $e);
        }

        if (! is_array($datos)) {
            throw new RuntimeException('El archivo '.basename($ruta).' no contiene una estructura JSON válida.');
        }

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
