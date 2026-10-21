<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use App\Services\FacebookProfileIdentifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class FacebookImportIdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:import-ids
                            {file : Ruta al archivo .xlsx o .csv con las cuentas e IDs de Facebook}
                            {--dry-run : Simula la importación sin guardar cambios en la base de datos}
                            {--create-missing : Crea usuarios colaboradores, cuentas de correo y perfiles que aún no existan en la BD}
                            {--allow-duplicate-ids : Permite importar filas cuyo Facebook ID aparece repetido en distintos correos del archivo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrae e importa los Facebook IDs vinculados a colaboradores desde un archivo .xlsx o .csv hacia TraceX.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = (string) $this->argument('file');

        if (! is_file($filePath) || ! is_readable($filePath)) {
            $this->error("No se encontró o no se puede leer el archivo: {$filePath}");

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $createMissing = (bool) $this->option('create-missing');
        $allowDuplicateIds = (bool) $this->option('allow-duplicate-ids');

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            $this->error('El archivo debe tener extensión .xlsx o .csv.');

            return self::FAILURE;
        }

        $records = $extension === 'xlsx'
            ? $this->parseXlsx($filePath)
            : $this->parseCsv($filePath);

        if ($records === []) {
            $this->warn('No se encontraron registros válidos con Facebook ID y colaborador identificado.');

            return self::SUCCESS;
        }

        $fbIdCounts = [];
        foreach ($records as $record) {
            $fbIdCounts[$record['facebook_identifier']] = ($fbIdCounts[$record['facebook_identifier']] ?? 0) + 1;
        }

        $stats = [
            'total_extracted' => count($records),
            'profiles_updated' => 0,
            'profiles_created' => 0,
            'emails_created' => 0,
            'users_matched' => 0,
            'users_created' => 0,
            'pivots_linked' => 0,
            'skipped_duplicate_id' => 0,
            'skipped_missing_profile' => 0,
            'skipped_missing_user' => 0,
        ];

        $matchedUsersTracker = [];
        $createdUsersTracker = [];

        $runner = function () use (
            $records,
            $fbIdCounts,
            $allowDuplicateIds,
            $createMissing,
            &$stats,
            &$matchedUsersTracker,
            &$createdUsersTracker
        ): void {
            foreach ($records as $record) {
                $fbIdentifier = $record['facebook_identifier'];

                if (! $allowDuplicateIds && ($fbIdCounts[$fbIdentifier] ?? 1) > 1) {
                    $stats['skipped_duplicate_id']++;

                    continue;
                }

                $user = $this->resolveCollaboratorUser($record['collaborator'], $createMissing, $createdUsersTracker);

                if ($user) {
                    $matchedUsersTracker[$user->id] = true;
                } else {
                    $stats['skipped_missing_user']++;
                }

                $emailAccount = EmailAccount::query()
                    ->whereRaw('LOWER(email) = ?', [strtolower($record['email'])])
                    ->first();

                if (! $emailAccount && $createMissing) {
                    $emailAccount = EmailAccount::create([
                        'alias' => $record['name'] ?: null,
                        'email' => strtolower($record['email']),
                        'password' => $record['email_password'] ?: 'Temporal123!',
                        'status' => $record['status'] === 'suspended' ? 'suspended' : 'active',
                    ]);
                    $stats['emails_created']++;
                }

                $profile = null;

                if ($emailAccount) {
                    $profile = Profile::query()
                        ->where('email_account_id', $emailAccount->id)
                        ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                        ->first();
                }

                if (! $profile) {
                    $profile = Profile::query()
                        ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                        ->where('facebook_identifier', $fbIdentifier)
                        ->first();
                }

                if ($profile) {
                    $dirty = false;
                    if ($profile->facebook_identifier !== $fbIdentifier) {
                        $profile->facebook_identifier = $fbIdentifier;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $profile->save();
                        $stats['profiles_updated']++;
                    }
                } elseif ($createMissing && $emailAccount) {
                    $profile = Profile::create([
                        'email_account_id' => $emailAccount->id,
                        'name' => $record['name'] ?: strtolower($record['email']),
                        'facebook_identifier' => $fbIdentifier,
                        'password' => $record['facebook_password'] ?: $record['email_password'],
                        'social_network' => 'Facebook',
                        'status' => in_array($record['status'], ['active', 'suspended', 'restricted'], true)
                            ? $record['status']
                            : 'active',
                    ]);
                    $stats['profiles_created']++;
                } else {
                    $stats['skipped_missing_profile']++;

                    continue;
                }

                if ($user) {
                    $changes = $profile->users()->syncWithoutDetaching([$user->id]);
                    if (! empty($changes['attached'])) {
                        $stats['pivots_linked']++;
                    }
                }
            }
        };

        if ($isDryRun) {
            DB::beginTransaction();
            try {
                $runner();
            } finally {
                DB::rollBack();
            }
            $this->warn('Modo --dry-run activo: ningún cambio fue persistido en la base de datos.');
        } else {
            DB::transaction($runner);
            $this->info('Importación completada exitosamente.');
        }

        $stats['users_created'] = count($createdUsersTracker);
        $stats['users_matched'] = count($matchedUsersTracker);

        $this->table(
            ['Métrica', 'Total'],
            [
                ['Cuentas con FB ID + Colaborador leídas', $stats['total_extracted']],
                ['Perfiles existentes actualizados con FB ID', $stats['profiles_updated']],
                ['Perfiles nuevos creados', $stats['profiles_created']],
                ['Cuentas de correo nuevas creadas', $stats['emails_created']],
                ['Colaboradores identificados en BD', $stats['users_matched']],
                ['Colaboradores nuevos creados', $stats['users_created']],
                ['Nuevos vínculos Perfil ↔ Colaborador (profile_user)', $stats['pivots_linked']],
                ['Omitidos por FB ID duplicado en archivo', $stats['skipped_duplicate_id']],
                ['Omitidos por no existir Perfil en BD (sin --create-missing)', $stats['skipped_missing_profile']],
                ['Filas cuyo Colaborador aún no existe en BD (sin --create-missing)', $stats['skipped_missing_user']],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Find or optionally create the collaborator User record.
     *
     * @param  array<int, bool>  $createdUsersTracker
     */
    private function resolveCollaboratorUser(string $collaboratorName, bool $createMissing, array &$createdUsersTracker): ?User
    {
        $cleanName = trim($collaboratorName);
        if ($cleanName === '') {
            return null;
        }

        $normalized = mb_strtolower($cleanName, 'UTF-8');
        $slug = Str::slug($cleanName, '.');

        $user = User::query()
            ->where('role', 'user')
            ->where(function ($query) use ($normalized, $slug) {
                $query->whereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(email) = ?', ["{$slug}@evidencias.local"]);
            })
            ->orderBy('id')
            ->first();

        if (! $user) {
            $user = User::query()
                ->where('role', 'user')
                ->whereRaw('LOWER(name) LIKE ?', [$normalized.' %'])
                ->orderBy('id')
                ->first();
        }

        if (! $user && $createMissing) {
            $email = "{$slug}@evidencias.local";
            $suffix = 1;
            while (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                $email = "{$slug}{$suffix}@evidencias.local";
                $suffix++;
            }

            $user = User::create([
                'name' => ucfirst($cleanName),
                'email' => $email,
                'password' => Hash::make('Temporal123!'),
                'role' => 'user',
                'is_active' => true,
            ]);
            $createdUsersTracker[$user->id] = true;
        }

        return $user;
    }

    /**
     * Parse either a compiled single-sheet XLSX or the multi-sheet raw Cuentas.xlsx workbook.
     *
     * @return list<array{collaborator: string, name: string, email: string, email_password: string, facebook_password: string, facebook_identifier: string, status: string}>
     */
    private function parseXlsx(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sharedStrings = [];
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssContent !== false) {
            $sx = new SimpleXMLElement($ssContent);
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $rels = [];
        $relsXml = new SimpleXMLElement((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        foreach ($relsXml->Relationship as $rel) {
            $rels[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $wbXml = new SimpleXMLElement((string) $zip->getFromName('xl/workbook.xml'));
        $sheets = [];
        foreach ($wbXml->sheets->sheet as $sheet) {
            $sName = trim((string) $sheet['name']);
            $rId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = 'xl/'.ltrim($rels[$rId], '/');
            $sheets[$sName] = $this->readWorksheetCells($zip, $target, $sharedStrings);
        }

        $zip->close();

        if (isset($sheets['Cuentas_Compiladas'])) {
            return $this->extractFromCompiledRows($sheets['Cuentas_Compiladas']);
        }

        return $this->extractFromMultiSheetWorkbook($sheets);
    }

    /**
     * Read cells of a worksheet indexed by row number and column letter.
     *
     * @param  list<string>  $sharedStrings
     * @return array<int, array<string, string>>
     */
    private function readWorksheetCells(ZipArchive $zip, string $target, array $sharedStrings): array
    {
        $xmlString = $zip->getFromName($target);
        if ($xmlString === false) {
            return [];
        }

        $sXml = new SimpleXMLElement($xmlString);
        $result = [];

        foreach ($sXml->sheetData->row ?? [] as $row) {
            $rNum = (int) $row['r'];
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = (string) preg_replace('/[0-9]+/', '', $ref);
                $type = (string) $c['t'];
                $val = isset($c->v) ? (string) $c->v : '';
                if ($type === 's' && $val !== '') {
                    $val = $sharedStrings[(int) $val] ?? $val;
                } elseif ($type === 'inlineStr') {
                    $val = (string) ($c->is->t ?? '');
                }
                if (trim($val) !== '') {
                    $cells[$col] = trim($val);
                }
            }
            if ($cells !== []) {
                $result[$rNum] = $cells;
            }
        }

        return $result;
    }

    /**
     * Extract records from the compiled Cuentas_Compiladas sheet.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return list<array{collaborator: string, name: string, email: string, email_password: string, facebook_password: string, facebook_identifier: string, status: string}>
     */
    private function extractFromCompiledRows(array $rows): array
    {
        $compiled = [];
        foreach ($rows as $rNum => $cells) {
            if ($rNum === 1) {
                continue;
            }

            $collaborator = trim($cells['B'] ?? $cells['C'] ?? '');
            $name = trim($cells['D'] ?? '');
            $email = strtolower(trim($cells['E'] ?? ''));
            $emailPass = trim($cells['F'] ?? '');
            $fbPass = trim($cells['G'] ?? '');
            $rawFb = trim($cells['H'] ?? $cells['J'] ?? '');
            $status = strtolower(trim($cells['L'] ?? 'active'));

            $fbNorm = FacebookProfileIdentifier::normalize($rawFb);
            if (! $fbNorm || $collaborator === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $compiled[] = [
                'collaborator' => $collaborator,
                'name' => $name,
                'email' => $email,
                'email_password' => $emailPass,
                'facebook_password' => $fbPass,
                'facebook_identifier' => $fbNorm,
                'status' => in_array($status, ['active', 'suspended', 'restricted'], true) ? $status : 'active',
            ];
        }

        return $compiled;
    }

    /**
     * Extract and consolidate records from the raw multi-sheet Cuentas.xlsx workbook.
     *
     * @param  array<string, array<int, array<string, string>>>  $sheets
     * @return list<array{collaborator: string, name: string, email: string, email_password: string, facebook_password: string, facebook_identifier: string, status: string}>
     */
    private function extractFromMultiSheetWorkbook(array $sheets): array
    {
        $operatorSheets = ['NETO', 'NATO', 'AMAIRANY', 'DEINNA', 'ANAID', 'KATYA', 'ALBERTO', 'ELEAZAR', 'RICARDO', 'ALEJANDRO'];
        $blockedEmails = [];
        $records = [];

        if (isset($sheets['cuentas bloqueadas'])) {
            foreach ($sheets['cuentas bloqueadas'] as $cells) {
                if (isset($cells['D']) && str_contains($cells['D'], '@')) {
                    $blockedEmails[strtolower(trim($cells['D']))] = true;
                }
            }
        }

        foreach ($sheets as $sName => $rows) {
            if (in_array($sName, ['cuentas bloqueadas', 'GRUPOS', 'resumen', 'Resumen_Colaboradores'], true)) {
                continue;
            }

            foreach ($rows as $rNum => $cells) {
                if ($rNum <= 2 && $sName !== 'ALBERTO' && $sName !== 'CORREOS') {
                    continue;
                }
                if (($sName === 'ALBERTO' || $sName === 'CORREOS') && $rNum <= 1) {
                    continue;
                }
                if (in_array($sName, ['CUENTAS CREADAS', 'KATYA', 'ACTUALIZADO', 'Hoja 18', 'RICARDO'], true) && $rNum <= 4) {
                    continue;
                }

                $email = null;
                $fbUrl = null;
                $name = null;
                $emailPass = null;
                $fbPass = null;
                $collabGeneral = null;
                $collabOperator = null;
                $obs = null;

                if ($sName === 'CUENTAS CREADAS' || $sName === 'ACTUALIZADO') {
                    $collabGeneral = isset($cells['B']) ? ucfirst(strtolower(trim($cells['B']))) : null;
                    $name = $cells['D'] ?? null;
                    $email = $cells['E'] ?? null;
                    $emailPass = $cells['F'] ?? null;
                    $fbPass = $cells['G'] ?? null;
                    $fbUrl = $cells['H'] ?? null;
                    $obs = $cells['L'] ?? null;
                } elseif ($sName === 'KATYA') {
                    $collabOperator = 'Katya';
                    $name = $cells['D'] ?? null;
                    $email = $cells['E'] ?? null;
                    $emailPass = $cells['F'] ?? null;
                    $fbPass = $cells['G'] ?? null;
                    $fbUrl = $cells['I'] ?? null;
                    $obs = $cells['L'] ?? null;
                } elseif ($sName === 'RICARDO') {
                    $collabOperator = 'Ricardo';
                    $name = $cells['C'] ?? null;
                    $email = $cells['D'] ?? null;
                    $fbPass = $cells['E'] ?? null;
                    $emailPass = $cells['F'] ?? null;
                    $obs = $cells['G'] ?? null;
                } elseif ($sName === 'ALEJANDRO') {
                    $collabOperator = 'Alejandro';
                    $name = $cells['D'] ?? null;
                    $email = $cells['E'] ?? null;
                    $emailPass = $cells['F'] ?? null;
                    $fbPass = $cells['G'] ?? null;
                    $obs = $cells['H'] ?? null;
                } elseif ($sName === 'ALBERTO') {
                    $collabOperator = 'Alberto';
                    $name = $cells['C'] ?? null;
                    $email = $cells['D'] ?? null;
                    $emailPass = $cells['E'] ?? null;
                    $fbPass = $cells['F'] ?? null;
                    $fbUrl = $cells['H'] ?? null;
                    $obs = trim(($cells['I'] ?? '').' '.($cells['N'] ?? ''));
                } elseif ($sName === 'ANAID') {
                    $collabOperator = 'Anaid';
                    $name = $cells['C'] ?? null;
                    $email = $cells['D'] ?? null;
                    $emailPass = $cells['E'] ?? null;
                    $fbPass = $cells['F'] ?? null;
                    $fbUrl = $cells['G'] ?? null;
                    $obs = $cells['K'] ?? null;
                } elseif ($sName === 'DEINNA' || $sName === 'ELEAZAR') {
                    $collabOperator = ucfirst(strtolower($sName));
                    $name = $cells['C'] ?? null;
                    $email = $cells['D'] ?? null;
                    $emailPass = $cells['E'] ?? null;
                    $fbPass = $cells['F'] ?? null;
                    $fbUrl = $cells['G'] ?? null;
                    $obs = $cells['B'] ?? null;
                } elseif (in_array($sName, ['NETO', 'NATO', 'AMAIRANY'], true)) {
                    $collabOperator = ucfirst(strtolower($sName));
                    $name = $cells['C'] ?? null;
                    $email = $cells['D'] ?? null;
                    $emailPass = $cells['E'] ?? null;
                    $fbPass = $cells['F'] ?? null;
                    $fbUrl = $cells['H'] ?? null;
                    $obs = $cells['L'] ?? null;
                }

                if ($email) {
                    $email = strtolower(trim($email));
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email = null;
                    }
                }

                $fbNorm = null;
                if ($fbUrl && stripos($fbUrl, 'facebook.com') !== false && ! str_contains($fbUrl, '/share/')) {
                    $fbNorm = FacebookProfileIdentifier::normalize($fbUrl);
                }

                if (! $email) {
                    continue;
                }

                if ($collabGeneral === 'Esbeydi') {
                    $collabGeneral = 'Esbeidy';
                }

                if (! isset($records[$email])) {
                    $records[$email] = [
                        'email' => $email,
                        'name' => null,
                        'email_pass' => null,
                        'fb_pass' => null,
                        'fb_id_by_sheet' => [],
                        'collab_operator' => [],
                        'collab_general' => null,
                        'obs' => [],
                    ];
                }

                if ($name && ! $records[$email]['name']) {
                    $records[$email]['name'] = $name;
                }
                if ($emailPass && ! $records[$email]['email_pass']) {
                    $records[$email]['email_pass'] = $emailPass;
                }
                if ($fbPass && ! $records[$email]['fb_pass']) {
                    $records[$email]['fb_pass'] = $fbPass;
                }
                if ($fbNorm) {
                    $records[$email]['fb_id_by_sheet'][$sName] = $fbNorm;
                }
                if ($collabOperator) {
                    $records[$email]['collab_operator'][$sName] = $collabOperator;
                }
                if ($collabGeneral && ! $records[$email]['collab_general']) {
                    $records[$email]['collab_general'] = $collabGeneral;
                }
                if ($obs) {
                    $records[$email]['obs'][$sName] = $obs;
                }
            }
        }

        $fbIdEmailCount = [];
        foreach ($records as $r) {
            foreach (array_unique(array_values($r['fb_id_by_sheet'])) as $fid) {
                $fbIdEmailCount[$fid] = ($fbIdEmailCount[$fid] ?? 0) + 1;
            }
        }

        $compiled = [];
        foreach ($records as $r) {
            if ($r['fb_id_by_sheet'] === []) {
                continue;
            }

            $activeCollab = $r['collab_operator'] !== []
                ? reset($r['collab_operator'])
                : $r['collab_general'];

            if (! $activeCollab) {
                continue;
            }

            $candidates = [];
            foreach ($r['fb_id_by_sheet'] as $sh => $fid) {
                $num = str_replace('id:', '', $fid);
                $lenScore = strlen($num) >= 14 ? 100 : strlen($num);
                $uniqueScore = ($fbIdEmailCount[$fid] ?? 1) === 1 ? 50 : 0;
                $sheetScore = in_array($sh, $operatorSheets, true) ? 30 : ($sh === 'ACTUALIZADO' ? 20 : 10);
                $candidates[$sh] = $lenScore + $uniqueScore + $sheetScore;
            }
            arsort($candidates);
            $chosenSheet = (string) array_key_first($candidates);
            $chosenFbId = $r['fb_id_by_sheet'][$chosenSheet];

            $isBlocked = isset($blockedEmails[$r['email']]);
            $obsText = implode(' | ', array_unique(array_filter($r['obs'])));
            $status = 'active';
            if ($isBlocked || stripos($obsText, 'bloquead') !== false || stripos($obsText, 'reconocimiento facial') !== false) {
                $status = 'suspended';
            } elseif (stripos($obsText, 'pide codigo') !== false || stripos($obsText, 'verificación') !== false) {
                $status = 'restricted';
            }

            $compiled[] = [
                'collaborator' => $activeCollab,
                'name' => $r['name'] ?? '',
                'email' => $r['email'],
                'email_password' => $r['email_pass'] ?? '',
                'facebook_password' => $r['fb_pass'] ?? '',
                'facebook_identifier' => $chosenFbId,
                'status' => $status,
            ];
        }

        return $compiled;
    }

    /**
     * Parse a compiled CSV file.
     *
     * @return list<array{collaborator: string, name: string, email: string, email_password: string, facebook_password: string, facebook_identifier: string, status: string}>
     */
    private function parseCsv(string $filePath): array
    {
        $fp = fopen($filePath, 'r');
        if ($fp === false) {
            return [];
        }

        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $headers = fgetcsv($fp, 0, ',', '"', '');
        if (! is_array($headers)) {
            fclose($fp);

            return [];
        }

        $compiled = [];
        while (($row = fgetcsv($fp, 0, ',', '"', '')) !== false) {
            $collaborator = trim($row[1] ?? $row[2] ?? '');
            $name = trim($row[3] ?? '');
            $email = strtolower(trim($row[4] ?? ''));
            $emailPass = trim($row[5] ?? '');
            $fbPass = trim($row[6] ?? '');
            $rawFb = trim($row[7] ?? $row[9] ?? '');
            $status = strtolower(trim($row[11] ?? 'active'));

            $fbNorm = FacebookProfileIdentifier::normalize($rawFb);
            if (! $fbNorm || $collaborator === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $compiled[] = [
                'collaborator' => $collaborator,
                'name' => $name,
                'email' => $email,
                'email_password' => $emailPass,
                'facebook_password' => $fbPass,
                'facebook_identifier' => $fbNorm,
                'status' => in_array($status, ['active', 'suspended', 'restricted'], true) ? $status : 'active',
            ];
        }

        fclose($fp);

        return $compiled;
    }
}
