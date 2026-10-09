<?php

namespace App\Console\Commands;

use App\Exceptions\GoogleDesconectado;
use App\Jobs\ProcesarExportJob;
use App\Models\Importacion;
use App\Services\DriveService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportarDesdeDrive extends Command
{
    protected $signature = 'drive:importar';
    protected $description = 'Busca carpetas meta-* en Drive, las descarga y encola su procesamiento sin borrar los originales';

    public function handle(): int
    {
        try {
            $drive = new DriveService();
            $carpetas = $drive->carpetasDeMeta();
        } catch (GoogleDesconectado $e) {
            $this->error("Google Drive no autorizado: {$e->getMessage()}");
            $this->line('Vuelve a dar el permiso con: php artisan google:token');

            return self::FAILURE;
        }

        if (! $carpetas) {
            $this->line('Sin exportaciones nuevas.');

            return self::SUCCESS;
        }

        foreach ($carpetas as $carpeta) {
            if (Importacion::where('drive_folder_id', $carpeta->getId())->exists()) {
                continue; // ya se tomó en una ejecución anterior
            }

            $importacion = Importacion::create([
                'drive_folder_id' => $carpeta->getId(),
                'nombre_carpeta'  => $carpeta->getName(),
                'recibido_en'     => Carbon::parse($carpeta->getCreatedTime()),
                'estado'          => 'descargando',
            ]);

            try {
                $archivos = $drive->descargarCarpeta($carpeta->getId(), $importacion->directorioLocal());

                // Descarga verificada → borrado permanente en Drive
                // Original de Drive conservado por seguridad.

               $importacion->update(['estado' => 'en_cola']);
                ProcesarExportJob::dispatch($importacion);

                $this->info("{$carpeta->getName()}: {$archivos} archivos descargados.");

    } catch (Throwable $e) {
        $importacion->update([
        'estado' => 'error',
        'error' => mb_substr($e->getMessage(), 0, 5000),
        ]);

        report($e);

        $this->error(
        "{$carpeta->getName()}: {$e->getMessage()}"
    );
}

        }

        return self::SUCCESS;
    }
}
