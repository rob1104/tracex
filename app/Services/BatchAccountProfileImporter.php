<?php

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class BatchAccountProfileImporter
{
    public function __construct(
        private readonly EvidenceExcelWorkbook $workbook
    ) {}

    /**
     * Generate a downloadable XLSX template for batch importing email accounts and Facebook profiles.
     */
    public function createTemplate(string $filePath): void
    {
        $rows = [
            [
                'Nombre',
                'Correo',
                'Contraseña Gmail',
                'Contraseña Facebook',
                'Link Del Perfil',
                'Colaborador',
                'Alias Correo',
                'Estatus',
            ],
            [
                'Juan Pérez',
                'ejemplo1@plantilla.tracex.local',
                'ClaveCorreo123!',
                'ClaveFB123!',
                'https://www.facebook.com/profile.php?id=1000987654321',
                'Nombre del Colaborador',
                'Juan Pérez',
                'Activo',
            ],
            [
                'María López',
                'ejemplo2@plantilla.tracex.local',
                'ClaveCorreo456!',
                'ClaveFB456!',
                'https://www.facebook.com/maria.lopez.perfil',
                'Nombre del Colaborador',
                'María López',
                'Activo',
            ],
        ];

        $this->workbook->create([
            [
                'name' => 'Plantilla_Perfiles',
                'rows' => $rows,
            ],
        ], $filePath);
    }

    /**
     * Parse an XLSX or CSV file and import all email accounts, Facebook profiles, and collaborator links.
     *
     * @param  array{
     *     default_collaborator_id?: ?int,
     *     override_collaborator?: bool,
     *     create_missing_collaborators?: bool,
     *     created_by?: ?int
     * }  $options
     * @return array{
     *     total_rows: int,
     *     emails_created: int,
     *     emails_updated: int,
     *     profiles_created: int,
     *     profiles_updated: int,
     *     collaborator_links: int,
     *     collaborators_created: int,
     *     skipped_rows: int,
     *     row_errors: list<string>
     * }
     */
    public function importFromFile(
        string $filePath,
        User $actor,
        array $options = [],
        ?string $originalExtension = null,
        ?string $originalFileName = null
    ): array {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('No se pudo leer el archivo seleccionado.');
        }

        $extension = strtolower($originalExtension ?: pathinfo($originalFileName ?: $filePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new RuntimeException('El archivo debe tener formato .xlsx o .csv.');
        }

        $sourceLabel = $originalFileName ? basename($originalFileName) : basename($filePath);

        $records = $extension === 'csv'
            ? $this->parseCsv($filePath, $sourceLabel)
            : $this->parseXlsx($filePath, $sourceLabel);

        return $this->importRecords($records, $actor, $options);
    }

    /**
     * Import parsed records into the database using bulk queries to minimize round-trips.
     *
     * @param  list<array{
     *     row_number: int,
     *     sheet: string,
     *     email: string,
     *     email_password: string,
     *     profile_name: string,
     *     raw_facebook_candidates: list<string>,
     *     facebook_password: string,
     *     collaborator: string,
     *     alias: string,
     *     status: string
     * }>  $records
     * @param  array{
     *     default_collaborator_id?: ?int,
     *     override_collaborator?: bool,
     *     create_missing_collaborators?: bool,
     *     created_by?: ?int
     * }  $options
     * @return array{
     *     total_rows: int,
     *     emails_created: int,
     *     emails_updated: int,
     *     profiles_created: int,
     *     profiles_updated: int,
     *     collaborator_links: int,
     *     collaborators_created: int,
     *     skipped_rows: int,
     *     row_errors: list<string>
     * }
     */
    public function importRecords(array $records, User $actor, array $options = []): array
    {
        @set_time_limit(300);

        $defaultCollaboratorId = isset($options['default_collaborator_id']) && (int) $options['default_collaborator_id'] > 0
            ? (int) $options['default_collaborator_id']
            : null;
        $overrideCollaborator = (bool) ($options['override_collaborator'] ?? false);
        $createMissingCollaborators = (bool) ($options['create_missing_collaborators'] ?? true);
        $ownerId = isset($options['created_by']) && (int) $options['created_by'] > 0
            ? (int) $options['created_by']
            : $actor->id;

        $stats = [
            'total_rows' => count($records),
            'emails_created' => 0,
            'emails_updated' => 0,
            'profiles_created' => 0,
            'profiles_updated' => 0,
            'collaborator_links' => 0,
            'collaborators_created' => 0,
            'skipped_rows' => 0,
            'row_errors' => [],
        ];

        if ($records === []) {
            return $stats;
        }

        // Preload candidate users once
        $userPool = User::query()
            ->whereIn('role', ['user', 'cuentas'])
            ->orderBy('id')
            ->get();

        $defaultCollaborator = $defaultCollaboratorId !== null
            ? $userPool->first(fn (User $u): bool => $u->id === $defaultCollaboratorId && (bool) $u->is_active)
            : null;

        $createdUsersTracker = [];
        $validItems = [];

        foreach ($records as $record) {
            $rowLabel = $record['sheet'] !== ''
                ? "{$record['sheet']} fila {$record['row_number']}"
                : "Fila {$record['row_number']}";

            $email = strtolower(trim($record['email']));
            if ($email === '' || str_ends_with($email, '@plantilla.tracex.local')) {
                $stats['total_rows']--;

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['skipped_rows']++;
                $stats['row_errors'][] = "{$rowLabel}: El correo '{$record['email']}' no es válido.";

                continue;
            }

            $fbIdentifier = null;
            $hadNonEmptyFbCandidate = false;
            $rawFbSample = '';

            foreach ($record['raw_facebook_candidates'] as $candidate) {
                $trimmed = trim($candidate);
                if ($trimmed === '') {
                    continue;
                }
                $hadNonEmptyFbCandidate = true;
                $rawFbSample = $trimmed;
                $normalized = FacebookProfileIdentifier::normalize($trimmed);
                if ($normalized !== null && mb_strlen($normalized, 'UTF-8') <= 191) {
                    $fbIdentifier = $normalized;
                    break;
                }
            }

            if ($hadNonEmptyFbCandidate && $fbIdentifier === null) {
                $stats['row_errors'][] = "{$rowLabel} ({$email}): El enlace de Facebook '{$rawFbSample}' no tiene formato válido; se registró el perfil sin enlace.";
            }

            $status = $this->normalizeStatus($record['status']);
            $emailStatus = $status === 'suspended' ? 'suspended' : 'active';
            $profileName = trim($record['profile_name']);
            $alias = trim($record['alias']);
            $emailPassword = trim($record['email_password']);
            $facebookPassword = trim($record['facebook_password']);

            $resolvedAlias = $alias !== '' ? $alias : ($profileName !== '' ? $profileName : null);
            $resolvedEmailPassword = $emailPassword !== ''
                ? $emailPassword
                : ($facebookPassword !== '' ? $facebookPassword : 'Temporal123!');
            $resolvedFbPassword = $facebookPassword !== ''
                ? $facebookPassword
                : ($emailPassword !== '' ? $emailPassword : null);

            $validItems[] = [
                'row_label' => $rowLabel,
                'email' => $email,
                'alias' => $alias,
                'resolved_alias' => $resolvedAlias,
                'email_password' => $emailPassword,
                'resolved_email_password' => $resolvedEmailPassword,
                'profile_name' => $profileName,
                'fb_identifier' => $fbIdentifier,
                'resolved_fb_password' => $resolvedFbPassword,
                'collaborator_raw' => trim($record['collaborator']),
                'raw_status' => $record['status'],
                'status' => $status,
                'email_status' => $emailStatus,
            ];
        }

        if ($validItems === []) {
            return $stats;
        }

        DB::transaction(function () use (
            $validItems,
            $actor,
            $ownerId,
            $defaultCollaborator,
            $overrideCollaborator,
            $createMissingCollaborators,
            &$userPool,
            &$stats,
            &$createdUsersTracker
        ): void {
            $now = now();
            $uniqueEmails = array_values(array_unique(array_column($validItems, 'email')));

            /** @var Collection<string, EmailAccount> $existingEmails */
            $existingEmails = collect();
            foreach (array_chunk($uniqueEmails, 500) as $emailChunk) {
                $chunkAccounts = EmailAccount::query()
                    ->whereIn('email', $emailChunk)
                    ->get();
                foreach ($chunkAccounts as $acc) {
                    $existingEmails->put(strtolower($acc->email), $acc);
                }
            }

            // Bulk insert new EmailAccounts
            $newEmailPayloads = [];
            $seenNewEmails = [];
            foreach ($validItems as $item) {
                $email = $item['email'];
                if ($existingEmails->has($email) || isset($seenNewEmails[$email])) {
                    continue;
                }
                $seenNewEmails[$email] = true;

                $tempEmailModel = new EmailAccount([
                    'alias' => $item['resolved_alias'],
                    'email' => $email,
                    'password' => $item['resolved_email_password'],
                    'status' => $item['email_status'],
                    'created_by' => $ownerId,
                ]);
                $attrs = $tempEmailModel->getAttributes();
                $attrs['created_at'] = $now;
                $attrs['updated_at'] = $now;
                $newEmailPayloads[] = $attrs;
            }

            if ($newEmailPayloads !== []) {
                foreach (array_chunk($newEmailPayloads, 200) as $chunk) {
                    EmailAccount::insert($chunk);
                }
                $stats['emails_created'] += count($newEmailPayloads);

                foreach (array_chunk(array_keys($seenNewEmails), 500) as $emailChunk) {
                    $createdAccounts = EmailAccount::query()->whereIn('email', $emailChunk)->get();
                    foreach ($createdAccounts as $acc) {
                        $existingEmails->put(strtolower($acc->email), $acc);
                    }
                }
            }

            // Update existing EmailAccounts only if needed
            $updatedEmailsTracker = [];
            foreach ($validItems as $item) {
                $email = $item['email'];
                if (isset($seenNewEmails[$email]) || isset($updatedEmailsTracker[$email])) {
                    continue;
                }
                $emailAccount = $existingEmails->get($email);
                if (! $emailAccount) {
                    continue;
                }

                $emailUpdates = [];
                if ($item['resolved_alias'] !== null && ($item['alias'] !== '' || blank($emailAccount->alias))) {
                    if ($emailAccount->alias !== $item['resolved_alias']) {
                        $emailUpdates['alias'] = $item['resolved_alias'];
                    }
                }
                if ($item['email_password'] !== '') {
                    $emailUpdates['password'] = $item['email_password'];
                }
                if ($item['raw_status'] !== '' && $emailAccount->status !== $item['email_status']) {
                    $emailUpdates['status'] = $item['email_status'];
                }
                if ($emailAccount->created_by === null) {
                    $emailUpdates['created_by'] = $ownerId;
                }
                if ($emailUpdates !== []) {
                    $emailAccount->update($emailUpdates);
                    $stats['emails_updated']++;
                    $updatedEmailsTracker[$email] = true;
                }
            }

            // Preload existing Profiles for these EmailAccounts and for all candidate Facebook Identifiers
            $allEmailAccountIds = $existingEmails->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $candidateFbIds = array_values(array_unique(array_filter(array_column($validItems, 'fb_identifier'))));

            /** @var Collection<int, Profile> $facebookProfilesByEmailId */
            $facebookProfilesByEmailId = collect();
            /** @var Collection<int, Collection<int, Profile>> $allProfilesByEmailId */
            $allProfilesByEmailId = collect();

            foreach (array_chunk($allEmailAccountIds, 500) as $idChunk) {
                $profilesChunk = Profile::query()
                    ->whereIn('email_account_id', $idChunk)
                    ->get();
                foreach ($profilesChunk as $prof) {
                    $eid = (int) $prof->email_account_id;
                    if (! $allProfilesByEmailId->has($eid)) {
                        $allProfilesByEmailId->put($eid, collect());
                    }
                    $allProfilesByEmailId->get($eid)->push($prof);

                    if (strtolower($prof->social_network) === 'facebook' && ! $facebookProfilesByEmailId->has($eid)) {
                        $facebookProfilesByEmailId->put($eid, $prof);
                    }
                }
            }

            /** @var array<string, array{email_account_id: int, name: string}> $fbIdOwnerMap */
            $fbIdOwnerMap = [];
            if ($candidateFbIds !== []) {
                foreach (array_chunk($candidateFbIds, 500) as $fbChunk) {
                    $fbProfiles = Profile::query()
                        ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                        ->whereIn('facebook_identifier', $fbChunk)
                        ->get(['id', 'email_account_id', 'name', 'facebook_identifier']);
                    foreach ($fbProfiles as $fbProf) {
                        if (filled($fbProf->facebook_identifier)) {
                            $fbIdOwnerMap[(string) $fbProf->facebook_identifier] = [
                                'email_account_id' => (int) $fbProf->email_account_id,
                                'name' => (string) $fbProf->name,
                            ];
                        }
                    }
                }
            }

            // Prepare new Profiles vs updates
            $newProfilePayloads = [];
            $newProfileEmailAccountIds = [];
            $resolvedCollaboratorIdsByEmailAccountId = [];
            $supervisorIdsToAssign = [];

            foreach ($validItems as $item) {
                $email = $item['email'];
                $emailAccount = $existingEmails->get($email);
                if (! $emailAccount) {
                    continue;
                }
                $emailAccountId = (int) $emailAccount->id;

                $resolvedProfileName = $item['profile_name'] !== ''
                    ? $item['profile_name']
                    : ($emailAccount->alias ?: (string) Str::before($email, '@'));

                $fbIdentifier = $item['fb_identifier'];
                if ($fbIdentifier !== null && isset($fbIdOwnerMap[$fbIdentifier])) {
                    $ownerInfo = $fbIdOwnerMap[$fbIdentifier];
                    if ($ownerInfo['email_account_id'] !== $emailAccountId) {
                        $stats['row_errors'][] = "{$item['row_label']} ({$email}): El enlace/ID '{$fbIdentifier}' ya está asignado al perfil '{$ownerInfo['name']}'; se creó/actualizó '{$resolvedProfileName}' sin ID duplicado.";
                        $fbIdentifier = null;
                    }
                }

                if ($fbIdentifier !== null && ! isset($fbIdOwnerMap[$fbIdentifier])) {
                    $fbIdOwnerMap[$fbIdentifier] = [
                        'email_account_id' => $emailAccountId,
                        'name' => $resolvedProfileName,
                    ];
                }

                $existingProfile = $facebookProfilesByEmailId->get($emailAccountId);

                if (! $existingProfile && ! isset($newProfileEmailAccountIds[$emailAccountId])) {
                    $tempProfile = new Profile([
                        'email_account_id' => $emailAccountId,
                        'name' => $resolvedProfileName,
                        'facebook_identifier' => $fbIdentifier,
                        'password' => $item['resolved_fb_password'],
                        'social_network' => 'Facebook',
                        'status' => $item['status'],
                        'created_by' => $ownerId,
                    ]);
                    $attrs = $tempProfile->getAttributes();
                    $attrs['created_at'] = $now;
                    $attrs['updated_at'] = $now;
                    $newProfilePayloads[] = $attrs;
                    $newProfileEmailAccountIds[$emailAccountId] = true;
                } elseif ($existingProfile) {
                    $profileUpdates = [];
                    if ($item['profile_name'] !== '' && $existingProfile->name !== $item['profile_name']) {
                        $profileUpdates['name'] = $item['profile_name'];
                    }
                    if ($fbIdentifier !== null && $existingProfile->facebook_identifier !== $fbIdentifier) {
                        $profileUpdates['facebook_identifier'] = $fbIdentifier;
                    }
                    if ($item['resolved_fb_password'] !== null) {
                        $profileUpdates['password'] = $item['resolved_fb_password'];
                    }
                    if ($item['raw_status'] !== '' && $existingProfile->status !== $item['status']) {
                        $profileUpdates['status'] = $item['status'];
                    }
                    if ($existingProfile->created_by === null) {
                        $profileUpdates['created_by'] = $ownerId;
                    }
                    if ($profileUpdates !== []) {
                        $existingProfile->update($profileUpdates);
                        $stats['profiles_updated']++;
                    }
                }

                // Resolve collaborator in-memory
                $collaborator = null;
                if ($overrideCollaborator && $defaultCollaborator) {
                    $collaborator = $defaultCollaborator;
                } elseif ($item['collaborator_raw'] !== '') {
                    $collaborator = $this->resolveCollaboratorFromPool(
                        $item['collaborator_raw'],
                        $actor,
                        $createMissingCollaborators,
                        $userPool,
                        $createdUsersTracker
                    );
                }

                if (! $collaborator && $defaultCollaborator) {
                    $collaborator = $defaultCollaborator;
                }

                if ($collaborator) {
                    if ($actor->isSupervisor() && $collaborator->isUser() && $collaborator->supervisor_id === null) {
                        $collaborator->supervisor_id = $actor->id;
                        $supervisorIdsToAssign[] = $collaborator->id;
                    }
                    $resolvedCollaboratorIdsByEmailAccountId[$emailAccountId][] = $collaborator->id;
                } elseif ($actor->isCuentas()) {
                    $resolvedCollaboratorIdsByEmailAccountId[$emailAccountId][] = $actor->id;
                }
            }

            if ($supervisorIdsToAssign !== []) {
                User::query()
                    ->where('role', 'user')
                    ->whereNull('supervisor_id')
                    ->whereIn('id', array_values(array_unique($supervisorIdsToAssign)))
                    ->update(['supervisor_id' => $actor->id]);
            }

            if ($newProfilePayloads !== []) {
                foreach (array_chunk($newProfilePayloads, 200) as $chunk) {
                    Profile::insert($chunk);
                }
                $stats['profiles_created'] += count($newProfilePayloads);

                foreach (array_chunk(array_keys($newProfileEmailAccountIds), 500) as $idChunk) {
                    $insertedProfiles = Profile::query()
                        ->whereIn('email_account_id', $idChunk)
                        ->get();
                    foreach ($insertedProfiles as $prof) {
                        $eid = (int) $prof->email_account_id;
                        if (! $allProfilesByEmailId->has($eid)) {
                            $allProfilesByEmailId->put($eid, collect());
                        }
                        if (! $allProfilesByEmailId->get($eid)->contains('id', $prof->id)) {
                            $allProfilesByEmailId->get($eid)->push($prof);
                        }
                    }
                }
            }

            // Bulk link profile_user pivots
            $allBatchProfileIds = $allProfilesByEmailId
                ->flatten(1)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $existingPivots = [];
            $existingUsersByProfileId = [];
            if ($allBatchProfileIds !== []) {
                foreach (array_chunk($allBatchProfileIds, 500) as $pidChunk) {
                    $pivotRows = DB::table('profile_user')
                        ->whereIn('profile_id', $pidChunk)
                        ->get(['profile_id', 'user_id']);
                    foreach ($pivotRows as $pRow) {
                        $pid = (int) $pRow->profile_id;
                        $uid = (int) $pRow->user_id;
                        $existingPivots["{$pid}:{$uid}"] = true;
                        $existingUsersByProfileId[$pid][] = $uid;
                    }
                }
            }

            $newPivotRows = [];
            foreach ($allProfilesByEmailId as $emailAccountId => $profilesForEmail) {
                $targetUserIds = $resolvedCollaboratorIdsByEmailAccountId[$emailAccountId] ?? [];
                if ($targetUserIds === []) {
                    foreach ($profilesForEmail as $prof) {
                        foreach ($existingUsersByProfileId[(int) $prof->id] ?? [] as $inheritedUid) {
                            $targetUserIds[] = $inheritedUid;
                        }
                    }
                }

                $targetUserIds = array_values(array_unique($targetUserIds));
                if ($targetUserIds === []) {
                    continue;
                }

                foreach ($profilesForEmail as $prof) {
                    $pid = (int) $prof->id;
                    foreach ($targetUserIds as $uid) {
                        $key = "{$pid}:{$uid}";
                        if (! isset($existingPivots[$key])) {
                            $existingPivots[$key] = true;
                            $newPivotRows[] = [
                                'profile_id' => $pid,
                                'user_id' => $uid,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }
            }

            if ($newPivotRows !== []) {
                foreach (array_chunk($newPivotRows, 500) as $chunk) {
                    DB::table('profile_user')->insertOrIgnore($chunk);
                }
                $stats['collaborator_links'] += count($newPivotRows);
            }
        });

        $stats['collaborators_created'] = count($createdUsersTracker);

        return $stats;
    }

    /**
     * Synchronize the link between an EmailAccount, its Facebook Profile, and its assigned Collaborator(s).
     *
     * @param  list<int>  $collaboratorIds
     * @param  array{
     *     profile_id?: ?int,
     *     profile_name?: ?string,
     *     facebook_identifier?: ?string,
     *     facebook_password?: ?string,
     *     status?: ?string,
     *     created_by?: ?int,
     *     replace_collaborators?: bool
     * }  $profileData
     */
    public function syncEmailProfileAndCollaborators(
        EmailAccount $emailAccount,
        User $actor,
        array $collaboratorIds = [],
        array $profileData = []
    ): ?Profile {
        $ownerId = $profileData['created_by'] ?? $emailAccount->created_by ?? $actor->id;
        $replaceCollaborators = (bool) ($profileData['replace_collaborators'] ?? false);

        $cleanCollaboratorIds = collect($collaboratorIds)
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($actor->isSupervisor() && $cleanCollaboratorIds !== []) {
            User::query()
                ->where('role', 'user')
                ->whereNull('supervisor_id')
                ->whereIn('id', $cleanCollaboratorIds)
                ->update(['supervisor_id' => $actor->id]);
        }

        $profile = null;
        $targetProfileId = isset($profileData['profile_id']) && (int) $profileData['profile_id'] > 0
            ? (int) $profileData['profile_id']
            : null;

        if ($targetProfileId !== null) {
            $profile = Profile::find($targetProfileId);
        }

        if (! $profile) {
            $profile = Profile::query()
                ->where('email_account_id', $emailAccount->id)
                ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                ->first();
        }

        $profileName = trim((string) ($profileData['profile_name'] ?? ''));
        $rawFbIdentifier = trim((string) ($profileData['facebook_identifier'] ?? ''));
        $normalizedFbIdentifier = $rawFbIdentifier !== ''
            ? FacebookProfileIdentifier::normalize($rawFbIdentifier)
            : null;
        $fbPassword = trim((string) ($profileData['facebook_password'] ?? ''));
        $profileStatus = in_array($profileData['status'] ?? '', ['active', 'suspended', 'restricted'], true)
            ? (string) $profileData['status']
            : ($emailAccount->status === 'suspended' ? 'suspended' : 'active');

        $shouldEnsureFacebookProfile = $profile !== null
            || $profileName !== ''
            || $normalizedFbIdentifier !== null
            || $cleanCollaboratorIds !== [];

        if ($shouldEnsureFacebookProfile) {
            $resolvedName = $profileName !== ''
                ? $profileName
                : ($profile?->name ?: ($emailAccount->alias ?: (string) Str::before($emailAccount->email, '@')));

            if (! $profile) {
                try {
                    $fallbackPassword = $emailAccount->password;
                } catch (\Throwable) {
                    $fallbackPassword = null;
                }

                $profile = Profile::create([
                    'email_account_id' => $emailAccount->id,
                    'name' => $resolvedName,
                    'facebook_identifier' => $normalizedFbIdentifier,
                    'password' => $fbPassword !== '' ? $fbPassword : $fallbackPassword,
                    'social_network' => 'Facebook',
                    'status' => $profileStatus,
                    'created_by' => $ownerId,
                ]);
            } else {
                $updates = [
                    'email_account_id' => $emailAccount->id,
                ];
                if ($profileName !== '') {
                    $updates['name'] = $profileName;
                }
                if ($rawFbIdentifier !== '' && $normalizedFbIdentifier !== null) {
                    $updates['facebook_identifier'] = $normalizedFbIdentifier;
                }
                if ($fbPassword !== '') {
                    $updates['password'] = $fbPassword;
                }
                if ($profile->created_by === null && $ownerId) {
                    $updates['created_by'] = $ownerId;
                }
                $profile->update($updates);
            }
        }

        if ($cleanCollaboratorIds === [] && ! $replaceCollaborators) {
            $cleanCollaboratorIds = $emailAccount->profiles()
                ->with('users:id')
                ->get()
                ->flatMap(fn (Profile $p) => $p->users->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($replaceCollaborators) {
            $emailAccount->profiles()->get()->each(function (Profile $p) use ($cleanCollaboratorIds): void {
                $p->users()->sync($cleanCollaboratorIds);
            });
        } elseif ($cleanCollaboratorIds !== []) {
            $emailAccount->profiles()->get()->each(function (Profile $p) use ($cleanCollaboratorIds): void {
                $p->users()->syncWithoutDetaching($cleanCollaboratorIds);
            });
        }

        return $profile?->fresh(['users', 'emailAccount']);
    }

    /**
     * Parse an XLSX file into normalized row structures.
     *
     * @return list<array{
     *     row_number: int,
     *     sheet: string,
     *     email: string,
     *     email_password: string,
     *     profile_name: string,
     *     raw_facebook_candidates: list<string>,
     *     facebook_password: string,
     *     collaborator: string,
     *     alias: string,
     *     status: string
     * }>
     */
    public function parseXlsx(string $filePath, ?string $sourceFileName = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }

        $sharedStrings = [];
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssContent !== false) {
            $sx = @simplexml_load_string($ssContent);
            if ($sx instanceof SimpleXMLElement) {
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
        }

        $rels = [];
        $relsContent = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsContent !== false) {
            $relsXml = @simplexml_load_string($relsContent);
            if ($relsXml instanceof SimpleXMLElement) {
                foreach ($relsXml->Relationship as $rel) {
                    $rels[(string) $rel['Id']] = (string) $rel['Target'];
                }
            }
        }

        $wbContent = $zip->getFromName('xl/workbook.xml');
        $sheets = [];
        if ($wbContent !== false) {
            $wbXml = @simplexml_load_string($wbContent);
            if ($wbXml instanceof SimpleXMLElement && isset($wbXml->sheets->sheet)) {
                foreach ($wbXml->sheets->sheet as $sheet) {
                    $sName = trim((string) $sheet['name']);
                    $relAttrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $rId = isset($relAttrs['id']) ? (string) $relAttrs['id'] : (string) $sheet['id'];
                    $rawTarget = $rels[$rId] ?? '';
                    if ($rawTarget === '') {
                        continue;
                    }
                    $normalizedTarget = ltrim($rawTarget, '/');
                    if (! str_starts_with($normalizedTarget, 'xl/')) {
                        $normalizedTarget = 'xl/'.$normalizedTarget;
                    }
                    $sheets[$sName] = $this->readWorksheetCells($zip, $normalizedTarget, $sharedStrings);
                }
            }
        }

        if ($sheets === []) {
            for ($i = 1; $i <= 20; $i++) {
                $candidate = "xl/worksheets/sheet{$i}.xml";
                if ($zip->locateName($candidate) !== false) {
                    $sheets["Hoja {$i}"] = $this->readWorksheetCells($zip, $candidate, $sharedStrings);
                }
            }
        }

        $zip->close();

        if (isset($sheets['Cuentas_Compiladas'])) {
            return $this->extractRowsFromSheet($sheets['Cuentas_Compiladas'], 'Cuentas_Compiladas', $sourceFileName);
        }

        $extracted = [];
        foreach ($sheets as $sheetName => $rows) {
            if (in_array(mb_strtolower($sheetName, 'UTF-8'), ['cuentas bloqueadas', 'grupos', 'resumen', 'resumen_colaboradores', 'instrucciones'], true)) {
                continue;
            }
            $sheetRecords = $this->extractRowsFromSheet($rows, $sheetName, $sourceFileName);
            foreach ($sheetRecords as $record) {
                $extracted[] = $record;
            }
        }

        return $extracted;
    }

    /**
     * Parse a CSV file into normalized row structures.
     *
     * @return list<array{
     *     row_number: int,
     *     sheet: string,
     *     email: string,
     *     email_password: string,
     *     profile_name: string,
     *     raw_facebook_candidates: list<string>,
     *     facebook_password: string,
     *     collaborator: string,
     *     alias: string,
     *     status: string
     * }>
     */
    public function parseCsv(string $filePath, ?string $sourceFileName = null): array
    {
        $fp = fopen($filePath, 'r');
        if ($fp === false) {
            return [];
        }

        $bom = fread($fp, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fp);
        }

        $rows = [];
        $rowNum = 1;
        while (($csvRow = fgetcsv($fp, 0, ',', '"', '')) !== false) {
            $cells = [];
            foreach ($csvRow as $colIndex => $value) {
                $trimmed = trim((string) $value);
                if ($trimmed !== '') {
                    $cells[$this->columnLetter($colIndex + 1)] = $trimmed;
                }
            }
            if ($cells !== []) {
                $rows[$rowNum] = $cells;
            }
            $rowNum++;
        }

        fclose($fp);

        $sheetLabel = $sourceFileName ? pathinfo($sourceFileName, PATHINFO_FILENAME) : 'CSV';

        return $this->extractRowsFromSheet($rows, $sheetLabel, $sourceFileName);
    }

    /**
     * Read cells of a worksheet indexed by 1-based row number and column letter.
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

        $sXml = @simplexml_load_string($xmlString);
        if (! $sXml instanceof SimpleXMLElement) {
            return [];
        }

        $result = [];
        $rowIndexFallback = 1;

        foreach ($sXml->sheetData->row ?? [] as $row) {
            $rNum = isset($row['r']) && (int) $row['r'] > 0 ? (int) $row['r'] : $rowIndexFallback;
            $rowIndexFallback = $rNum + 1;
            $cells = [];
            $colIndexFallback = 1;

            foreach ($row->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                $col = (string) preg_replace('/[0-9]+/', '', $ref);
                if ($col === '') {
                    $col = $this->columnLetter($colIndexFallback);
                }
                $colIndexFallback++;

                $type = (string) ($c['t'] ?? '');
                $val = isset($c->v) ? (string) $c->v : '';

                if ($type === 's' && $val !== '') {
                    $val = $sharedStrings[(int) $val] ?? $val;
                } elseif ($type === 'inlineStr') {
                    if (isset($c->is->t)) {
                        $val = (string) $c->is->t;
                    } elseif (isset($c->is->r)) {
                        $val = '';
                        foreach ($c->is->r as $r) {
                            $val .= (string) $r->t;
                        }
                    }
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
     * Extract structured rows from a worksheet's cell map using header detection or positional fallback.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return list<array{
     *     row_number: int,
     *     sheet: string,
     *     email: string,
     *     email_password: string,
     *     profile_name: string,
     *     raw_facebook_candidates: list<string>,
     *     facebook_password: string,
     *     collaborator: string,
     *     alias: string,
     *     status: string
     * }>
     */
    private function extractRowsFromSheet(array $rows, string $sheetName, ?string $sourceFileName = null): array
    {
        if ($rows === []) {
            return [];
        }

        $headerRowNumber = null;
        $columnMap = null;

        foreach ($rows as $rNum => $cells) {
            if ($rNum > 10) {
                break;
            }
            $detected = $this->detectHeaderColumns($cells);
            if ($detected !== null) {
                $headerRowNumber = $rNum;
                $columnMap = $detected;
                break;
            }
        }

        if ($columnMap === null) {
            $firstRow = reset($rows);
            $columnMap = $this->detectPositionalColumns(is_array($firstRow) ? $firstRow : []);
        }

        $fallbackCollaboratorFromSheet = $this->inferCollaboratorFromSheetOrFileName($sheetName)
            ?? ($sourceFileName ? $this->inferCollaboratorFromSheetOrFileName($sourceFileName) : null)
            ?? '';

        $extracted = [];
        foreach ($rows as $rNum => $cells) {
            if ($headerRowNumber !== null && $rNum <= $headerRowNumber) {
                continue;
            }

            $email = $this->pickFirstCell($cells, $columnMap['email']);
            $profileName = $this->pickFirstCell($cells, $columnMap['profile_name']);
            $emailPass = $this->pickFirstCell($cells, $columnMap['email_password']);
            $fbPass = $this->pickFirstCell($cells, $columnMap['facebook_password']);
            $collaborator = $this->pickFirstCell($cells, $columnMap['collaborator']);
            $alias = $this->pickFirstCell($cells, $columnMap['alias']);
            $status = $this->pickFirstCell($cells, $columnMap['status']);

            $fbCandidates = [];
            foreach ($columnMap['facebook'] as $fbCol) {
                if (isset($cells[$fbCol]) && trim($cells[$fbCol]) !== '') {
                    $fbCandidates[] = trim($cells[$fbCol]);
                }
            }

            if ($email === '' && $profileName === '' && $fbCandidates === []) {
                continue;
            }

            if ($collaborator === '' && $fallbackCollaboratorFromSheet !== '') {
                $collaborator = $fallbackCollaboratorFromSheet;
            }

            $extracted[] = [
                'row_number' => $rNum,
                'sheet' => $sheetName,
                'email' => $email,
                'email_password' => $emailPass,
                'profile_name' => $profileName,
                'raw_facebook_candidates' => $fbCandidates,
                'facebook_password' => $fbPass,
                'collaborator' => $collaborator,
                'alias' => $alias,
                'status' => $status,
            ];
        }

        return $extracted;
    }

    /**
     * Infer a collaborator name from a worksheet name or CSV filename (e.g., "Cuentas.xlsx - Ximena.csv" -> "Ximena").
     */
    private function inferCollaboratorFromSheetOrFileName(string $rawLabel): ?string
    {
        $clean = trim($rawLabel);
        if ($clean === '') {
            return null;
        }

        $clean = (string) preg_replace('/\.(xlsx|csv)$/i', '', $clean);
        $clean = (string) preg_replace('/\s*\(\d+\)\s*$/', '', $clean);

        if (str_contains($clean, ' - ')) {
            $parts = explode(' - ', $clean);
            $clean = trim((string) end($parts));
        }

        $norm = $this->normalizeHeaderLabel($clean);
        if ($norm === '' || str_starts_with($norm, 'hoja ') || str_starts_with($norm, 'sheet ')) {
            return null;
        }

        $genericLabels = [
            'plantilla perfiles',
            'nuevos perfiles',
            'hoja1',
            'sheet1',
            'csv',
            'cuentas compiladas',
            'cuentas creadas',
            'actualizado',
            'correos',
            'cuentas',
            'perfiles',
            'lote',
            'plantilla',
            'importacion',
            'export',
            'reporte',
            'usuarios',
            'sin asignar',
            'test cuentas fb',
        ];

        if (in_array($norm, $genericLabels, true) || str_contains($norm, 'plantilla') || str_contains($norm, 'lote')) {
            return null;
        }

        return Str::title($clean);
    }

    /**
     * Detect column letters from a potential header row.
     *
     * @param  array<string, string>  $cells
     * @return array{
     *     email: list<string>,
     *     email_password: list<string>,
     *     profile_name: list<string>,
     *     facebook: list<string>,
     *     facebook_password: list<string>,
     *     collaborator: list<string>,
     *     alias: list<string>,
     *     status: list<string>
     * }|null
     */
    private function detectHeaderColumns(array $cells): ?array
    {
        $map = [
            'email' => [],
            'email_password' => [],
            'profile_name' => [],
            'facebook' => [],
            'facebook_password' => [],
            'collaborator' => [],
            'alias' => [],
            'status' => [],
        ];

        foreach ($cells as $col => $rawText) {
            if (filter_var(trim($rawText), FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            $norm = $this->normalizeHeaderLabel($rawText);
            if ($norm === '') {
                continue;
            }

            $isPasswordCol = str_starts_with($norm, 'contrasena')
                || str_starts_with($norm, 'password')
                || str_starts_with($norm, 'clave')
                || str_starts_with($norm, 'pass');

            if ($isPasswordCol) {
                if (str_contains($norm, 'facebook') || str_contains($norm, ' fb')) {
                    $map['facebook_password'][] = $col;
                } else {
                    $map['email_password'][] = $col;
                }

                continue;
            }

            if (in_array($norm, ['correo', 'email', 'correo electronico', 'cuenta de correo', 'cuenta correo', 'e mail', 'mail', 'cuenta'], true)) {
                $map['email'][] = $col;
            } elseif (in_array($norm, ['nombre del perfil', 'nombre perfil', 'nombre de perfil', 'perfil', 'nombre', 'usuario facebook', 'nombre facebook', 'perfil facebook', 'nombre usuario'], true)) {
                $map['profile_name'][] = $col;
            } elseif (
                in_array($norm, [
                    'link del perfil', 'link de perfil', 'link perfil',
                    'enlace del perfil', 'enlace de perfil', 'enlace perfil',
                    'url del perfil', 'url de perfil', 'url perfil',
                    'enlace o id de facebook', 'enlace o id facebook', 'enlace de facebook', 'enlace facebook',
                    'url facebook', 'url de facebook', 'id facebook', 'id de facebook',
                    'facebook identifier tracex', 'facebook id numerico', 'facebook url', 'facebook id', 'facebook identifier',
                    'link facebook', 'link de facebook', 'enlace', 'url', 'link', 'facebook',
                    'identificador', 'identificador facebook', 'identificador de facebook',
                ], true)
                || str_contains($norm, 'link del perfil')
                || str_contains($norm, 'enlace del perfil')
            ) {
                $map['facebook'][] = $col;
            } elseif (in_array($norm, ['colaborador', 'colaborador actual', 'colaborador asignado', 'colaborador hoja general', 'asignado a', 'operador', 'responsable', 'usuario asignado'], true)) {
                $map['collaborator'][] = $col;
            } elseif (in_array($norm, ['alias', 'alias correo', 'alias de correo', 'alias cuenta'], true)) {
                $map['alias'][] = $col;
            } elseif (in_array($norm, ['estatus', 'estado', 'status', 'estatus sugerido'], true)) {
                $map['status'][] = $col;
            }
        }

        if ($map['email'] === []) {
            return null;
        }

        return $map;
    }

    /**
     * Fallback positional column mapping when a sheet has no header row.
     *
     * @param  array<string, string>  $sampleRow
     * @return array{
     *     email: list<string>,
     *     email_password: list<string>,
     *     profile_name: list<string>,
     *     facebook: list<string>,
     *     facebook_password: list<string>,
     *     collaborator: list<string>,
     *     alias: list<string>,
     *     status: list<string>
     * }
     */
    private function detectPositionalColumns(array $sampleRow): array
    {
        if (isset($sampleRow['E']) && filter_var(trim($sampleRow['E']), FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => ['E'],
                'email_password' => ['F'],
                'profile_name' => ['D'],
                'facebook' => ['H', 'J', 'I'],
                'facebook_password' => ['G'],
                'collaborator' => ['B', 'C'],
                'alias' => ['D'],
                'status' => ['L'],
            ];
        }

        if (isset($sampleRow['D']) && filter_var(trim($sampleRow['D']), FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => ['D'],
                'email_password' => ['E'],
                'profile_name' => ['C'],
                'facebook' => ['G', 'H'],
                'facebook_password' => ['F'],
                'collaborator' => ['B'],
                'alias' => ['C'],
                'status' => ['L'],
            ];
        }

        return [
            'email' => ['A'],
            'email_password' => ['B'],
            'profile_name' => ['C'],
            'facebook' => ['D'],
            'facebook_password' => ['E'],
            'collaborator' => ['F'],
            'alias' => ['G'],
            'status' => ['H'],
        ];
    }

    private function normalizeHeaderLabel(string $label): string
    {
        $lower = mb_strtolower(trim($label), 'UTF-8');
        $transliterated = strtr($lower, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $cleaned = preg_replace('/[^a-z0-9]+/', ' ', $transliterated) ?? $transliterated;

        return trim((string) preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * @param  array<string, string>  $cells
     * @param  list<string>  $columns
     */
    private function pickFirstCell(array $cells, array $columns): string
    {
        foreach ($columns as $col) {
            if (isset($cells[$col]) && trim($cells[$col]) !== '') {
                return trim($cells[$col]);
            }
        }

        return '';
    }

    private function normalizeStatus(string $rawStatus): string
    {
        $norm = $this->normalizeHeaderLabel($rawStatus);

        if (in_array($norm, ['suspended', 'suspendido', 'suspendida', 'inactivo', 'inactiva', 'bloqueado', 'bloqueada'], true)) {
            return 'suspended';
        }

        if (in_array($norm, ['restricted', 'restringido', 'restringida'], true)) {
            return 'restricted';
        }

        return 'active';
    }

    /**
     * Resolve or optionally create the Collaborator User record from the preloaded pool.
     *
     * @param  Collection<int, User>  $userPool
     * @param  array<int, bool>  $createdUsersTracker
     */
    private function resolveCollaboratorFromPool(
        string $collaboratorValue,
        User $actor,
        bool $createMissing,
        Collection &$userPool,
        array &$createdUsersTracker
    ): ?User {
        $clean = trim($collaboratorValue);
        if ($clean === '' || in_array(mb_strtolower($clean, 'UTF-8'), ['sin asignar', 'n/a', 'ninguno', '-'], true)) {
            return null;
        }

        if ($clean === 'Esbeydi' || mb_strtolower($clean, 'UTF-8') === 'esbeydi') {
            $clean = 'Esbeidy';
        }

        $normalized = mb_strtolower($clean, 'UTF-8');
        $slug = Str::slug($clean, '.');
        $fallbackEmail = "{$slug}@evidencias.local";

        $candidates = $userPool->filter(function (User $u) use ($normalized, $fallbackEmail): bool {
            $uEmail = mb_strtolower(trim((string) $u->email), 'UTF-8');
            $uName = mb_strtolower(trim((string) $u->name), 'UTF-8');

            return $uEmail === $normalized
                || $uName === $normalized
                || $uEmail === $fallbackEmail;
        });

        if ($candidates->isEmpty()) {
            $candidates = $userPool->filter(function (User $u) use ($normalized): bool {
                $uName = mb_strtolower(trim((string) $u->name), 'UTF-8');

                return str_starts_with($uName, $normalized.' ');
            });
        }

        if ($candidates->isNotEmpty()) {
            if ($actor->isSupervisor()) {
                $supervised = $candidates->first(fn (User $u): bool => (int) $u->supervisor_id === (int) $actor->id);
                if ($supervised) {
                    return $supervised;
                }
            }

            return $candidates->first();
        }

        if ($createMissing) {
            $email = filter_var($clean, FILTER_VALIDATE_EMAIL)
                ? strtolower($clean)
                : $fallbackEmail;

            $suffix = 1;
            while ($userPool->contains(fn (User $u): bool => strtolower($u->email) === $email) || User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                $email = "{$slug}{$suffix}@evidencias.local";
                $suffix++;
            }

            $displayName = filter_var($clean, FILTER_VALIDATE_EMAIL)
                ? ucfirst((string) Str::before($clean, '@'))
                : Str::title($clean);

            $user = User::create([
                'name' => $displayName,
                'email' => $email,
                'password' => Hash::make('Temporal123!'),
                'role' => 'user',
                'supervisor_id' => $actor->isSupervisor() ? $actor->id : null,
                'is_active' => true,
            ]);
            $userPool->push($user);
            $createdUsersTracker[$user->id] = true;

            return $user;
        }

        return null;
    }

    private function columnLetter(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
