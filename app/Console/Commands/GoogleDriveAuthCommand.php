<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GoogleDriveAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:drive-auth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar URL de autorización y obtener el GOOGLE_DRIVE_REFRESH_TOKEN leyendo credenciales desde .env';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  Asistente OAuth 2.0 de Google Drive (TraceX)');
        $this->info('====================================================');

        // 1. Leer credenciales directamente de .env / config
        $clientId = trim((string) config('services.google.drive.client_id'));
        $clientSecret = trim((string) config('services.google.drive.client_secret'));
        $redirectUri = trim((string) config('services.google.drive.redirect_uri', 'https://developers.google.com/oauthplayground'));

        if (empty($clientId) || empty($clientSecret)) {
            $this->error('❌ Faltan credenciales en tu archivo .env.');
            $this->line('Asegúrate de tener definidas en .env las siguientes variables:');
            $this->line('  GOOGLE_DRIVE_CLIENT_ID="tu_client_id"');
            $this->line('  GOOGLE_DRIVE_CLIENT_SECRET="tu_client_secret"');

            return self::FAILURE;
        }

        // 2. Construir la URL de autorización
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive',
            'access_type' => 'offline',
            'prompt' => 'consent', // Fuerza a Google a devolver refresh_token
        ]);

        $this->newLine();
        $this->comment('1. Abre este enlace en tu navegador para autorizar la aplicación:');
        $this->line($authUrl);
        $this->newLine();
        $this->info('2. Inicia sesión, autoriza el acceso y copia el código o la URL resultante de tu navegador.');
        $this->line("   (URI de retorno configurada: {$redirectUri})");
        $this->newLine();

        // 3. El usuario solo pega el código o la URL resultante
        $input = $this->ask('👉 Pega aquí el código o la URL de la barra de direcciones');

        if (empty($input)) {
            $this->error('No se ingresó ningún código. Operación cancelada.');

            return self::FAILURE;
        }

        // Extraer el código limpio si pegaron la URL completa
        $code = trim($input);
        if (str_contains($code, 'code=')) {
            $queryStr = parse_url($code, PHP_URL_QUERY) ?? '';
            parse_str($queryStr, $params);
            $code = $params['code'] ?? $code;
        }

        $this->info('Conectando con Google para obtener el token...');

        // 4. Intercambiar el código por tokens
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful()) {
            $this->error('❌ Error de Google al intercambiar el código:');
            $this->line($response->body());

            return self::FAILURE;
        }

        $data = $response->json();
        $refreshToken = $data['refresh_token'] ?? null;

        if (! $refreshToken) {
            $this->warn('⚠️ Google devolvió access_token pero no refresh_token.');
            $this->line('Causa: La cuenta ya tenía autorización previa. Ve a https://myaccount.google.com/connections, revoca el acceso a la app y vuelve a ejecutar este comando.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('🎉 ¡ÉXITO! GOOGLE_DRIVE_REFRESH_TOKEN obtenido:');
        $this->info('====================================================');
        $this->line($refreshToken);
        $this->newLine();

        // 5. Guardar automáticamente en el archivo .env
        $this->updateEnvFile([
            'GOOGLE_DRIVE_REFRESH_TOKEN' => $refreshToken,
        ]);
        $this->info('✅ GOOGLE_DRIVE_REFRESH_TOKEN guardado automáticamente en tu archivo .env.');

        return self::SUCCESS;
    }

    /**
     * Actualizar claves en el archivo .env sin perder el resto del contenido.
     *
     * @param  array<string, string>  $values
     */
    protected function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);

        foreach ($values as $key => $value) {
            $escaped = '"'.addcslashes($value, '"\\').'"';
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        File::put($envPath, $content);
    }
}
