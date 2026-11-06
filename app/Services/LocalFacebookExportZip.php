<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class LocalFacebookExportZip
{
    /**
     * Extract JSON entries without trusting paths stored inside the archive.
     *
     * @return array{files_count: int, bytes: int}
     */
    public function extract(string $archivePath, string $destination): array
    {
        $zip = new ZipArchive;
        $opened = $zip->open($archivePath);

        if ($opened !== true) {
            throw new RuntimeException('No se pudo abrir el archivo ZIP. Verifica que no esté dañado.');
        }

        $fileCount = 0;
        $totalBytes = 0;
        $maxFiles = 10000;
        $maxExtractedBytes = 512 * 1024 * 1024;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                $stat = $zip->statIndex($index);

                if (! is_string($entryName) || ! is_array($stat) || str_ends_with($entryName, '/')) {
                    continue;
                }

                if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'json') {
                    continue;
                }

                $normalizedName = str_replace('\\', '/', $entryName);
                $segments = explode('/', $normalizedName);
                if (str_starts_with($normalizedName, '/')
                    || preg_match('/^[a-zA-Z]:/', $normalizedName)
                    || in_array('..', $segments, true)
                    || in_array('.', $segments, true)
                    || str_contains($normalizedName, "\0")) {
                    throw new RuntimeException('El ZIP contiene una ruta de archivo no válida.');
                }

                $entryBytes = (int) ($stat['size'] ?? 0);
                $totalBytes += $entryBytes;
                $fileCount++;

                if ($fileCount > $maxFiles || $totalBytes > $maxExtractedBytes) {
                    throw new RuntimeException('El ZIP excede el límite permitido de archivos o tamaño extraído.');
                }

                $targetPath = $destination.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
                $targetDirectory = dirname($targetPath);
                if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
                    throw new RuntimeException('No se pudo preparar la carpeta local para procesar el ZIP.');
                }

                $input = $zip->getStream($entryName);
                $output = fopen($targetPath, 'wb');

                if ($input === false || $output === false) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    if (is_resource($output)) {
                        fclose($output);
                    }

                    throw new RuntimeException('No se pudo extraer un archivo JSON del ZIP.');
                }

                try {
                    if (stream_copy_to_stream($input, $output) === false) {
                        throw new RuntimeException('Falló la extracción de un archivo JSON del ZIP.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
            }
        } finally {
            $zip->close();
        }

        if ($fileCount === 0) {
            throw new RuntimeException('El ZIP no contiene archivos JSON de una exportación de Facebook.');
        }

        return ['files_count' => $fileCount, 'bytes' => $totalBytes];
    }
}
