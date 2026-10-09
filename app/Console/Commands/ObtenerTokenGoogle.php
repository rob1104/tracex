<?php

namespace App\Console\Commands;

use App\Services\DriveService;
use Illuminate\Console\Command;

/**
 * Se corre una vez al instalar (y cada vez que caduque el permiso) para autorizar
 * a la app a leer y borrar las exportaciones en el Google Drive configurado.
 * Requiere un cliente OAuth de tipo "App de escritorio".
 */
class ObtenerTokenGoogle extends Command
{
    protected $signature = 'google:token';
    protected $description = 'Autoriza el Google Drive configurado y muestra el refresh token para el .env';

    public function handle(): int
    {
        $client = DriveService::cliente();

        $this->line('1. Abre esta URL e inicia sesión con el correo al que Facebook transfiere las exportaciones:');
        $this->newLine();
        $this->info($client->createAuthUrl());
        $this->newLine();
        $this->line('2. Acepta los permisos. El navegador irá a http://localhost/?code=... (puede marcar error, es normal).');
        $this->line('   Copia el valor de "code" de la barra de direcciones, hasta antes de &scope.');

        $codigo = urldecode(trim((string) $this->ask('Pega aquí el code')));
        $token = $client->fetchAccessTokenWithAuthCode($codigo);

        if (isset($token['error'])) {
            $this->error("Google respondió: {$token['error']} " . ($token['error_description'] ?? ''));

            return self::FAILURE;
        }

        if (empty($token['refresh_token'])) {
            $this->error('No llegó el refresh token. Quita el acceso de la app en myaccount.google.com/permissions y repite.');

            return self::FAILURE;
        }

        $correo = (new DriveService($token['refresh_token']))->correo();

        $this->newLine();
        $this->info("Autorizado: {$correo}");
        $this->line('Agrega (o reemplaza) estas líneas en tu .env:');
        $this->newLine();
        $this->info('Token de Google obtenido correctamente.');
        $this->line('Correo autorizado: ' . $correo);
        $this->line('Agrega GOOGLE_REFRESH_TOKEN al .env manualmente; no se mostrará aquí.');
        $this->newLine();
        $this->line('Después corre: php artisan config:clear');

        return self::SUCCESS;
    }
}
