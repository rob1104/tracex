<?php

namespace App\Services;

use App\Exceptions\GoogleDesconectado;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Acceso al Google Drive del proyecto, al que se transfieren las exportaciones de Facebook.
 *
 * Facebook ("Transferir a Google Drive") crea en la raíz de Mi unidad
 * una carpeta por exportación: meta-2026-Oct-07-12-45-04
 */
class DriveService
{
    private const CARPETA = 'application/vnd.google-apps.folder';

    private Drive $drive;

    public function __construct(?string $refreshToken = null)
    {
        $refreshToken ??= config('services.google.refresh_token');

        if (! $refreshToken) {
            throw new GoogleDesconectado('Falta GOOGLE_REFRESH_TOKEN en el .env (corre php artisan google:token).');
        }

        $client = self::cliente();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new GoogleDesconectado($token['error_description'] ?? $token['error']);
        }

        $this->drive = new Drive($client);
    }

    /** Cliente OAuth base (también lo usa google:token para obtener el permiso). */
    public static function cliente(): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri('http://localhost'); // cliente OAuth tipo "App de escritorio"
        $client->addScope(Drive::DRIVE); // necesario para leer y borrar carpetas que creó Facebook
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    public function correo(): ?string
    {
        return $this->drive->about->get(['fields' => 'user(emailAddress)'])->getUser()?->getEmailAddress();
    }

    /** Carpetas de exportación de Facebook en la raíz de Mi unidad. */
    public function carpetasDeMeta(): array
{
    $carpetas = $this->listar(
        "mimeType = '" . self::CARPETA . "' and trashed = false"
    );

    return array_values(array_filter(
        $carpetas,
        fn ($c) => preg_match('/^meta-\d{4}-/', $c->getName())
    ));
}

    /**
     * Descarga una carpeta completa (con subcarpetas) a $destino.
     * Verifica cada archivo contra el md5 que reporta Drive. Regresa cuántos archivos bajó.
     */
    public function descargarCarpeta(string $carpetaId, string $destino): int
    {
        File::ensureDirectoryExists($destino);
        $total = 0;

        foreach ($this->listar("'{$carpetaId}' in parents and trashed = false") as $item) {
            $ruta = $destino . DIRECTORY_SEPARATOR . $this->nombreSeguro($item->getName());

            if ($item->getMimeType() === self::CARPETA) {
                $total += $this->descargarCarpeta($item->getId(), $ruta);
                continue;
            }

            // Documentos nativos de Google (Docs, Sheets) no son parte del export: se ignoran
            if (str_starts_with($item->getMimeType(), 'application/vnd.google-apps.')) {
                continue;
            }

            $respuesta = $this->drive->files->get($item->getId(), ['alt' => 'media']);
            $cuerpo = $respuesta->getBody();
            $salida = fopen($ruta, 'wb');
            while (! $cuerpo->eof()) {
                fwrite($salida, $cuerpo->read(1024 * 1024));
            }
            fclose($salida);

            if ($item->getMd5Checksum() && md5_file($ruta) !== $item->getMd5Checksum()) {
                throw new RuntimeException("La descarga de {$item->getName()} llegó incompleta.");
            }

            $total++;
        }

        return $total;
    }

    /** Borrado permanente (no pasa por la papelera), incluido todo el contenido de la carpeta. */
    public function borrar(string $id): void
    {
        $this->drive->files->delete($id);
    }

    private function listar(string $consulta): array
    {
        $items = [];
        $token = null;

        do {
            $resultado = $this->drive->files->listFiles([
            'q' => $consulta,
            'fields' => 'nextPageToken, files(id, name, mimeType, md5Checksum, createdTime)',
            'pageSize' => 200,
            'pageToken' => $token,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
                    ]);
            array_push($items, ...$resultado->getFiles());
            $token = $resultado->getNextPageToken();
        } while ($token);

        return $items;
    }

    private function nombreSeguro(string $nombre): string
    {
        return preg_replace('/[^\w.\-]+/u', '_', basename($nombre));
    }
}
