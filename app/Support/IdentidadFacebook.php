<?php

namespace App\Support;

/**
 * Convierte enlaces e identificadores de Facebook en huellas HMAC.
 * Nunca se guarda el dato en claro para buscar: solo su huella.
 */
class IdentidadFacebook
{
    /**
     * Normaliza el enlace a un perfil para que el que captura el usuario y el que viene
     * en el export den lo mismo:
     *   https://m.facebook.com/Sofia.P/?mibextid=xyz  → u:sofia.p
     *   facebook.com/profile.php?id=100012345678      → id:100012345678
     * Regresa null si no es un enlace de perfil reconocible.
     */
    public static function normalizarEnlace(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = trim($url);
        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        $partes = parse_url($url);
        $host = strtolower($partes['host'] ?? '');

        if (! preg_match('/(^|\.)(facebook\.com|fb\.com)$/', $host)) {
            return null;
        }

        $ruta = trim($partes['path'] ?? '', '/');
        parse_str($partes['query'] ?? '', $query);

        if (strtolower($ruta) === 'profile.php' && ! empty($query['id']) && ctype_digit((string) $query['id'])) {
            return 'id:' . $query['id'];
        }

        // Nombre de usuario: primer segmento, sin rutas reservadas de Facebook
        $usuario = strtolower(explode('/', $ruta)[0] ?? '');
        $reservadas = ['', 'share', 'groups', 'pages', 'events', 'watch', 'photo', 'photo.php', 'story.php', 'permalink.php', 'people', 'profile.php'];

        if (in_array($usuario, $reservadas, true) || ! preg_match('/^[a-z0-9.]{3,}$/', $usuario)) {
            return null;
        }

        return 'u:' . $usuario;
    }

    public static function huella(?string $valor): ?string
    {
        return $valor ? hash_hmac('sha256', $valor, config('services.facebook.hmac_key')) : null;
    }
}
