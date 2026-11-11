<?php

namespace App\Livewire\Admin\FacebookMonitoring;

use App\Jobs\ProcessFacebookExportJob;
use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\EvidenceTextImport;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Models\User;
use App\Services\FacebookImportProfileNameResolver;
use App\Services\FacebookProfileIdentifier;
use App\Services\LocalFacebookExportZip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $localZip;

    public string $search = '';

    public string $collaboratorId = '';

    public string $monitorName = '';

    public string $monitorFacebookUrl = '';

    public string $monitorEmailAccountId = '';

    public string $activityTab = 'recent';

    public string $activityProfileId = '';

    public string $chartPeriod = '7';

    public string $chartGrouping = 'day';

    public ?int $assigningIdentifierProfileId = null;

    public string $assigningFacebookIdentifier = '';

    /** @var array<int, string> */
    public array $profileFacebookIdentifiers = [];

    public string $profileActivityFilter = 'all';

    public function openAssignIdentifier(int $profileId): void
    {
        $profile = $this->restrictToManageableProfiles(
            Profile::query()->whereRaw('LOWER(social_network) = ?', ['facebook'])
        )->find($profileId);
        abort_if($profile === null, 403);

        $this->resetValidation(['assigningFacebookIdentifier', "profileFacebookIdentifiers.{$profileId}"]);
        $this->assigningIdentifierProfileId = $profile->id;
        $this->assigningFacebookIdentifier = (string) ($this->profileFacebookIdentifiers[$profile->id] ?? '');
    }

    public function cancelAssignIdentifier(): void
    {
        if ($this->assigningIdentifierProfileId !== null) {
            unset($this->profileFacebookIdentifiers[$this->assigningIdentifierProfileId]);
        }

        $this->reset(['assigningIdentifierProfileId', 'assigningFacebookIdentifier']);
        $this->resetValidation(['assigningFacebookIdentifier', 'profileFacebookIdentifiers']);
    }

    public function assignFacebookIdentifier(?int $profileId = null): void
    {
        $targetProfileId = $profileId ?? $this->assigningIdentifierProfileId;
        abort_if($targetProfileId === null, 422);

        $profile = $this->restrictToManageableProfiles(
            Profile::query()->whereRaw('LOWER(social_network) = ?', ['facebook'])
        )->find($targetProfileId);
        abort_if($profile === null, 403);

        $errorKey = $profileId !== null && array_key_exists($profileId, $this->profileFacebookIdentifiers)
            ? "profileFacebookIdentifiers.{$profileId}"
            : 'assigningFacebookIdentifier';

        $this->resetValidation([$errorKey, 'assigningFacebookIdentifier', "profileFacebookIdentifiers.{$profile->id}"]);

        if (filled($profile->facebook_identifier)) {
            $this->addError($errorKey, 'Este perfil ya tiene un ID de Facebook asignado.');

            return;
        }

        $rawValue = trim((string) ($this->profileFacebookIdentifiers[$profile->id] ?? $this->assigningFacebookIdentifier));
        if ($rawValue === '') {
            $this->addError($errorKey, 'Ingresa el ID o enlace del perfil de Facebook.');

            return;
        }

        $identifier = FacebookProfileIdentifier::normalize($rawValue);
        if ($identifier === null) {
            $this->addError($errorKey, 'Ingresa un ID numérico o un enlace válido de facebook.com.');

            return;
        }

        if (mb_strlen($identifier, 'UTF-8') > 191) {
            $this->addError($errorKey, 'El identificador del perfil es demasiado largo.');

            return;
        }

        $alreadyUsed = Profile::query()
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->where('facebook_identifier', $identifier)
            ->whereKeyNot($profile->id)
            ->exists();

        if ($alreadyUsed) {
            $this->addError($errorKey, 'Este ID o enlace de Facebook ya está registrado en otro perfil.');

            return;
        }

        $profile->update([
            'facebook_identifier' => $identifier,
        ]);

        unset($this->profileFacebookIdentifiers[$profile->id]);
        if ($this->assigningIdentifierProfileId === $profile->id) {
            $this->reset(['assigningIdentifierProfileId', 'assigningFacebookIdentifier']);
        }

        session()->flash('status', "ID de Facebook asignado correctamente a {$profile->name}.");
    }

    public function setProfileActivityFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'with_activity', 'without_activity', 'missing_id'], true)) {
            $this->profileActivityFilter = $filter;
            $this->resetPage();
        }
    }

    public function toggleProfileStatus(int $profileId): void
    {
        $profile = $this->restrictToManageableProfiles(
            Profile::query()->whereRaw('LOWER(social_network) = ?', ['facebook'])
        )->find($profileId);
        abort_if($profile === null, 403);

        $profile->status = $profile->status === 'active' ? 'suspended' : 'active';
        $profile->save();

        $statusLabel = $profile->status === 'active' ? 'activado' : 'desactivado';
        session()->flash('status', "El perfil {$profile->name} fue {$statusLabel} correctamente.");
    }

    public function importLocalZip(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);
        $this->resetValidation('localZip');

        $this->validate([
            'localZip' => 'required|file|mimes:zip|max:102400',
        ], [
            'localZip.required' => 'Selecciona un archivo ZIP de Facebook.',
            'localZip.mimes' => 'El archivo debe tener formato ZIP.',
            'localZip.max' => 'El ZIP no puede superar los 100 MB.',
        ]);

        $archiveHash = hash_file('sha256', $this->localZip->getRealPath());
        if ($archiveHash === false) {
            $this->addError('localZip', 'No se pudo leer el ZIP seleccionado.');

            return;
        }
        $duplicate = FacebookImportLog::query()
            ->where('drive_file_id', 'like', 'local:%')
            ->whereIn('status', ['downloaded', 'processing', 'pending_assignment', 'completed'])
            ->get(['metadata'])
            ->contains(fn (FacebookImportLog $importLog): bool => is_array($importLog->metadata)
                && ($importLog->metadata['archive_sha256'] ?? null) === $archiveHash);

        if ($duplicate) {
            $this->addError('localZip', 'Este mismo archivo ZIP ya se había cargado.');

            return;
        }

        $importId = (string) Str::uuid();
        $localDirectory = storage_path('app/temp_exports/local-'.$importId);
        File::ensureDirectoryExists($localDirectory);

        try {
            $extracted = app(LocalFacebookExportZip::class)->extract(
                $this->localZip->getRealPath(),
                $localDirectory
            );
        } catch (Throwable $exception) {
            File::deleteDirectory($localDirectory);
            $this->addError('localZip', $exception->getMessage());

            return;
        }

        $fileName = mb_substr(basename($this->localZip->getClientOriginalName()), 0, 255);
        $importLog = FacebookImportLog::create([
            'drive_file_id' => 'local:'.$importId,
            'file_name' => $fileName,
            'file_size_bytes' => $this->localZip->getSize(),
            'status' => 'downloaded',
            'downloaded_at' => now(),
            'metadata' => [
                'source' => 'local_upload',
                'uploaded_by' => auth()->id(),
                'archive_sha256' => $archiveHash,
                'files_count' => $extracted['files_count'],
                'extracted_bytes' => $extracted['bytes'],
            ],
        ]);

        try {
            ProcessFacebookExportJob::dispatch($localDirectory, $importLog->id, 'local_upload');
        } catch (Throwable $exception) {
            File::deleteDirectory($localDirectory);
            $importLog->update([
                'status' => 'failed',
                'error_message' => 'No se pudo iniciar el procesamiento: '.$exception->getMessage(),
            ]);
            report($exception);
            $this->addError('localZip', 'No se pudo iniciar el procesamiento del ZIP. Inténtalo de nuevo.');

            return;
        }

        $this->reset('localZip');
        $this->resetPage();
        $this->resetPage('activityPage');
        $this->resetPage('importsPage');
        session()->flash('status', 'ZIP recibido. TraceX lo está procesando y asociará el perfil con su colaborador si encuentra una coincidencia.');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->resetPage('activityPage');
        $this->resetPage('importsPage');
    }

    public function updatedCollaboratorId(): void
    {
        $this->resetPage();
        $this->resetPage('activityPage');
        $this->resetPage('importsPage');
    }

    public function registerMonitoredFacebookProfile(): void
    {
        abort_unless(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas(), 403);

        $this->validate([
            'monitorName' => 'required|string|max:255',
            'monitorFacebookUrl' => 'required|string|max:512',
            'monitorEmailAccountId' => 'required|integer|exists:email_accounts,id',
        ], [
            'monitorName.required' => 'Escribe el nombre o alias del perfil de Facebook.',
            'monitorFacebookUrl.required' => 'Agrega el enlace del perfil de Facebook.',
            'monitorEmailAccountId.required' => 'Selecciona la cuenta de correo asociada.',
        ]);

        $identifier = FacebookProfileIdentifier::normalize($this->monitorFacebookUrl);
        if ($identifier === null) {
            $this->addError('monitorFacebookUrl', 'El enlace debe pertenecer a facebook.com y apuntar a un perfil.');

            return;
        }

        if (mb_strlen($identifier, 'UTF-8') > 191) {
            $this->addError('monitorFacebookUrl', 'El enlace del perfil es demasiado largo.');

            return;
        }

        $emailAccount = EmailAccount::query()
            ->where('status', 'active')
            ->when(auth()->user()->isCuentas(), function ($query) {
                $query->where(function ($accountQuery) {
                    $accountQuery->where('created_by', auth()->id())
                        ->orWhereNull('created_by')
                        ->orWhereHas('creator', fn ($creatorQuery) => $creatorQuery->where('role', 'admin'));
                });
            })
            ->findOrFail($this->monitorEmailAccountId);

        if (Profile::query()
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ->where('facebook_identifier', $identifier)
            ->exists()) {
            $this->addError('monitorFacebookUrl', 'Este usuario de Facebook ya está registrado.');

            return;
        }

        Profile::create([
            'email_account_id' => $emailAccount->id,
            'name' => trim($this->monitorName),
            'facebook_identifier' => $identifier,
            'social_network' => 'Facebook',
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        $this->reset(['monitorName', 'monitorFacebookUrl', 'monitorEmailAccountId']);
        $this->resetPage();
        session()->flash('status', 'Usuario de Facebook registrado. TraceX buscará coincidencias en las próximas exportaciones.');
    }

    public function setActivityTab(string $tab): void
    {
        if (in_array($tab, ['recent', 'imports', 'pending', 'unassigned'], true)) {
            $this->activityTab = $tab;
            $this->resetPage('activityPage');
            $this->resetPage('importsPage');
        }
    }

    public function showProfileActivity(int $profileId): void
    {
        $this->activityProfileId = (string) $profileId;
        $this->activityTab = 'recent';
        $this->resetPage('activityPage');
    }

    public function showAllActivity(): void
    {
        $this->activityProfileId = '';
        $this->activityTab = 'recent';
        $this->resetPage('activityPage');
    }

    private function restrictToManageableProfiles($query)
    {
        if (auth()->user()->isUser()) {
            $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()));
        } elseif (auth()->user()->isCuentas()) {
            $query->where(function ($profileQuery) {
                $profileQuery->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()))
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($creatorQuery) => $creatorQuery->where('role', 'admin'));
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds();
            if ($monitoredIds !== null) {
                $query->where(function ($profileQuery) use ($monitoredIds) {
                    $profileQuery->where('created_by', auth()->id())
                        ->orWhereIn('created_by', $monitoredIds)
                        ->orWhereHas('users', fn ($userQuery) => $userQuery->whereIn('users.id', $monitoredIds));
                });
            }
        }

        return $query;
    }

    private function filterProfilesByCollaborator($query)
    {
        if (auth()->user()->canMonitorCollaborators() && $this->collaboratorId !== '') {
            $collaboratorId = (int) $this->collaboratorId;
            $query->where(function ($profileQuery) use ($collaboratorId) {
                $profileQuery->where('created_by', $collaboratorId)
                    ->orWhereHas('users', fn ($userQuery) => $userQuery->where('users.id', $collaboratorId));
            });
        }

        return $query;
    }

    private function facebookProfilesQuery()
    {
        $query = $this->filterProfilesByCollaborator($this->restrictToManageableProfiles(Profile::query()
            ->with(['emailAccount.creator', 'users'])
            ->whereRaw('LOWER(social_network) = ?', ['facebook'])));

        if ($this->search !== '') {
            $query->where(function ($profileQuery) {
                $profileQuery->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('facebook_identifier', 'like', '%'.$this->search.'%');
            });
        }

        return $query;
    }

    private function importsQuery()
    {
        $query = FacebookImportLog::query();

        if (auth()->user()->isUser()) {
            $query->where(function ($importsQuery) {
                $importsQuery->where('user_id', auth()->id())
                    ->orWhereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
            });
        } elseif (auth()->user()->isCuentas()) {
            $query->where(function ($importsQuery) {
                $importsQuery->whereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery))
                    ->orWhere(function ($unassignedQuery) {
                        $unassignedQuery->whereNull('profile_id')->where('status', 'pending_assignment');
                    });
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds();
            if ($monitoredIds !== null) {
                $query->where(function ($importsQuery) use ($monitoredIds) {
                    $importsQuery->whereIn('user_id', $monitoredIds)
                        ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('created_by', auth()->id())->orWhereIn('created_by', $monitoredIds))
                        ->orWhereHas('profile.users', fn ($userQuery) => $userQuery->whereIn('users.id', $monitoredIds));
                });
            }
        }

        if (auth()->user()->canMonitorCollaborators() && $this->collaboratorId !== '') {
            $collaboratorId = (int) $this->collaboratorId;
            $query->where(function ($importsQuery) use ($collaboratorId) {
                $importsQuery->where('user_id', $collaboratorId)
                    ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('created_by', $collaboratorId))
                    ->orWhereHas('profile.users', fn ($userQuery) => $userQuery->where('users.id', $collaboratorId));
            });
        }

        if ($this->activityProfileId !== '') {
            $query->where('profile_id', $this->activityProfileId);
        }

        return $query;
    }

    private function textImportsQuery(): Builder
    {
        $query = EvidenceTextImport::query();

        if (auth()->user()->isUser()) {
            $query->where(function ($importsQuery) {
                $importsQuery->where('user_id', auth()->id())
                    ->orWhereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
            });
        } elseif (auth()->user()->isCuentas()) {
            $query->whereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds();
            if ($monitoredIds !== null) {
                $query->where(function ($importsQuery) use ($monitoredIds) {
                    $importsQuery->whereIn('user_id', $monitoredIds)
                        ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('created_by', auth()->id())->orWhereIn('created_by', $monitoredIds))
                        ->orWhereHas('profile.users', fn ($userQuery) => $userQuery->whereIn('users.id', $monitoredIds));
                });
            }
        }

        if (auth()->user()->canMonitorCollaborators() && $this->collaboratorId !== '') {
            $collaboratorId = (int) $this->collaboratorId;
            $query->where(function ($importsQuery) use ($collaboratorId) {
                $importsQuery->where('user_id', $collaboratorId)
                    ->orWhereHas('profile', fn ($profileQuery) => $profileQuery->where('created_by', $collaboratorId))
                    ->orWhereHas('profile.users', fn ($userQuery) => $userQuery->where('users.id', $collaboratorId));
            });
        }

        if ($this->activityProfileId !== '') {
            $query->where('profile_id', $this->activityProfileId);
        }

        return $query;
    }

    private function combinedImportsQuery(): QueryBuilder
    {
        $exportImports = $this->importsQuery()
            ->selectRaw("id as source_id, 'export' as source_type, file_name as display_name, profile_id, user_id, status, COALESCE(downloaded_at, created_at) as received_at, error_message")
            ->toBase();
        $textImports = $this->textImportsQuery()
            ->selectRaw("id as source_id, 'text' as source_type, original_file_name as display_name, profile_id, user_id, status, created_at as received_at, error_message")
            ->toBase();

        return DB::query()
            ->fromSub($exportImports->unionAll($textImports), 'combined_imports')
            ->orderByDesc('received_at')
            ->orderBy('source_type')
            ->orderByDesc('source_id');
    }

    private function activityQuery()
    {
        $query = Evidence::query()
            ->whereIn('origin', ['google_drive', 'local_upload', 'facebook_txt'])
            ->whereRaw('LOWER(social_network) = ?', ['facebook']);

        if (auth()->user()->isUser()) {
            $query->where(function ($activityQuery) {
                $activityQuery->where('user_id', auth()->id())
                    ->orWhereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
            });
        } elseif (auth()->user()->isCuentas()) {
            $query->whereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds();
            if ($monitoredIds !== null) {
                $query->where(function ($activityQuery) use ($monitoredIds) {
                    $activityQuery->whereIn('user_id', $monitoredIds)
                        ->orWhereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
                });
            }
        }

        if (auth()->user()->canMonitorCollaborators() && $this->collaboratorId !== '') {
            $query->where('user_id', (int) $this->collaboratorId);
        }

        if ($this->activityProfileId !== '') {
            $query->where('profile_id', $this->activityProfileId);
        }

        return $query;
    }

    private function withProfileActivityMetrics(Builder $query): Builder
    {
        return $query
            ->withCount([
                'facebookImportLogs as facebook_exports_count',
                'facebookCommentsThroughImports as facebook_comments_count',
                'facebookReactionsThroughImports as facebook_reactions_count',
                'evidences as facebook_txt_comments_count' => fn ($query) => $query
                    ->where('origin', 'facebook_txt')
                    ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                    ->whereHas('evidenceType', fn ($typeQuery) => $typeQuery->where('name', 'like', '%Coment%')),
                'evidences as facebook_txt_reactions_count' => fn ($query) => $query
                    ->where('origin', 'facebook_txt')
                    ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                    ->whereHas('evidenceType', fn ($typeQuery) => $typeQuery
                        ->where('name', 'like', '%Reacci%')
                        ->orWhere('name', 'like', '%Like%')),
                'evidences as facebook_shares_count' => fn ($query) => $query
                    ->whereIn('origin', ['google_drive', 'local_upload', 'facebook_txt'])
                    ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                    ->whereHas('evidenceType', fn ($typeQuery) => $typeQuery->where('name', 'Compartido')),
                'evidences as facebook_evidences_count' => fn ($query) => $query
                    ->whereIn('origin', ['google_drive', 'local_upload', 'facebook_txt'])
                    ->whereRaw('LOWER(social_network) = ?', ['facebook']),
            ])
            ->withMax('facebookImportLogs as latest_facebook_export_at', 'downloaded_at')
            ->withMax([
                'evidences as latest_facebook_evidence_at' => fn ($query) => $query
                    ->whereIn('origin', ['google_drive', 'local_upload', 'facebook_txt'])
                    ->whereRaw('LOWER(social_network) = ?', ['facebook']),
            ], 'external_published_at');
    }

    private function profileTotalActivityCount(Profile $profile): int
    {
        $commentsCount = (int) ($profile->facebook_comments_count ?? 0) + (int) ($profile->facebook_txt_comments_count ?? 0);
        $reactionsCount = (int) ($profile->facebook_reactions_count ?? 0) + (int) ($profile->facebook_txt_reactions_count ?? 0);
        $sharesCount = (int) ($profile->facebook_shares_count ?? 0);
        $evidencesCount = (int) ($profile->facebook_evidences_count ?? 0);

        return max($commentsCount + $reactionsCount + $sharesCount, $evidencesCount);
    }

    /**
     * @param  Collection<int, EvidenceTextImport>  $textImports
     * @return array<int, array{comments: int, reactions: int, shares: int}>
     */
    private function resolveTextImportActivityCounts(Collection $textImports): array
    {
        if ($textImports->isEmpty()) {
            return [];
        }

        $profileIds = $textImports
            ->filter(fn (EvidenceTextImport $import): bool => $import->status !== 'cancelled' && filled($import->profile_id))
            ->pluck('profile_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $countsByProfile = [];
        if ($profileIds->isNotEmpty()) {
            $rows = Evidence::query()
                ->join('evidence_types', 'evidence_types.id', '=', 'evidence.evidence_type_id')
                ->where('evidence.origin', 'facebook_txt')
                ->whereRaw('LOWER(evidence.social_network) = ?', ['facebook'])
                ->whereIn('evidence.profile_id', $profileIds)
                ->selectRaw('evidence.profile_id, evidence_types.name as type_name, COUNT(*) as total')
                ->groupBy('evidence.profile_id', 'evidence_types.name')
                ->get();

            foreach ($rows as $row) {
                $profileId = (int) $row->profile_id;
                $countsByProfile[$profileId] ??= ['comments' => 0, 'reactions' => 0, 'shares' => 0];
                $typeName = mb_strtolower((string) $row->type_name);

                if (str_contains($typeName, 'compart')) {
                    $countsByProfile[$profileId]['shares'] += (int) $row->total;
                } elseif (str_contains($typeName, 'reacci') || str_contains($typeName, 'like')) {
                    $countsByProfile[$profileId]['reactions'] += (int) $row->total;
                } elseif (str_contains($typeName, 'coment')) {
                    $countsByProfile[$profileId]['comments'] += (int) $row->total;
                }
            }
        }

        $resolved = [];
        foreach ($textImports as $import) {
            $counts = ['comments' => 0, 'reactions' => 0, 'shares' => 0];

            if ($import->status === 'cancelled' || ((int) $import->imported_count === 0 && (int) $import->recognized_count === 0)) {
                $resolved[$import->id] = $counts;

                continue;
            }

            if (filled($import->parsed_path) && Storage::disk('local')->exists($import->parsed_path)) {
                $path = Storage::disk('local')->path($import->parsed_path);
                if (is_file($path) && is_readable($path) && ($handle = fopen($path, 'rb')) !== false) {
                    try {
                        while (($line = fgets($handle)) !== false) {
                            $record = json_decode($line, true);
                            if (! is_array($record) || ($record['kind'] ?? null) !== 'evidence') {
                                continue;
                            }
                            $typeKey = (string) ($record['type_key'] ?? '');
                            if ($typeKey === 'comment') {
                                $counts['comments']++;
                            } elseif ($typeKey === 'reaction') {
                                $counts['reactions']++;
                            } elseif ($typeKey === 'share') {
                                $counts['shares']++;
                            }
                        }
                    } finally {
                        fclose($handle);
                    }
                }
            }

            if ($counts['comments'] + $counts['reactions'] + $counts['shares'] === 0 && filled($import->profile_id)) {
                $profileId = (int) $import->profile_id;
                if (isset($countsByProfile[$profileId])) {
                    $counts = $countsByProfile[$profileId];
                }
            }

            if ($counts['comments'] + $counts['reactions'] + $counts['shares'] === 0 && is_array($import->preview_records)) {
                foreach ($import->preview_records as $record) {
                    $label = mb_strtolower((string) ($record['type_label'] ?? $record['type_key'] ?? ''));
                    if (str_contains($label, 'compart') || $label === 'share') {
                        $counts['shares']++;
                    } elseif (str_contains($label, 'reacci') || str_contains($label, 'like') || $label === 'reaction') {
                        $counts['reactions']++;
                    } elseif (str_contains($label, 'coment') || $label === 'comment') {
                        $counts['comments']++;
                    }
                }
            }

            $resolved[$import->id] = $counts;
        }

        return $resolved;
    }

    public function render()
    {
        $allTrackedProfiles = $this->withProfileActivityMetrics(
            $this->filterProfilesByCollaborator($this->restrictToManageableProfiles(
                Profile::query()
                    ->with(['emailAccount.creator', 'users'])
                    ->whereRaw('LOWER(social_network) = ?', ['facebook'])
            ))
        )
            ->orderBy('name')
            ->get()
            ->each(function (Profile $profile): void {
                $profile->setAttribute('total_activity_count', $this->profileTotalActivityCount($profile));
            });

        $profilesWithActivity = $allTrackedProfiles
            ->filter(fn (Profile $profile): bool => (int) $profile->total_activity_count > 0)
            ->sortByDesc('total_activity_count')
            ->values();

        $profilesWithoutActivity = $allTrackedProfiles
            ->filter(fn (Profile $profile): bool => (int) $profile->total_activity_count === 0)
            ->sortBy([
                fn (Profile $profile): int => blank($profile->facebook_identifier) ? 0 : 1,
                fn (Profile $profile): string => mb_strtolower((string) $profile->name),
            ])
            ->values();

        $profilesMissingIdCount = $allTrackedProfiles
            ->filter(fn (Profile $profile): bool => blank($profile->facebook_identifier))
            ->count();

        $totalProfilesCount = $allTrackedProfiles->count();
        $profilesWithActivityCount = $profilesWithActivity->count();
        $profilesWithoutActivityCount = $profilesWithoutActivity->count();
        $activityCoveragePercent = $totalProfilesCount > 0
            ? (int) round(($profilesWithActivityCount / $totalProfilesCount) * 100)
            : 0;

        $profilesQuery = $this->withProfileActivityMetrics($this->facebookProfilesQuery());

        if ($this->profileActivityFilter === 'with_activity') {
            $profilesQuery->whereIn('profiles.id', $profilesWithActivity->pluck('id')->all() ?: [0]);
        } elseif ($this->profileActivityFilter === 'without_activity') {
            $profilesQuery->whereIn('profiles.id', $profilesWithoutActivity->pluck('id')->all() ?: [0]);
        } elseif ($this->profileActivityFilter === 'missing_id') {
            $profilesQuery->where(function ($query) {
                $query->whereNull('facebook_identifier')->orWhere('facebook_identifier', '');
            });
        }

        $importsQuery = $this->importsQuery();
        $textImportsQuery = $this->textImportsQuery();
        $processingCount = (clone $importsQuery)->whereIn('status', ['downloaded', 'processing'])->count()
            + (clone $textImportsQuery)->whereIn('status', ['queued_preview', 'analyzing', 'queued', 'processing'])->count();
        $pendingAssignmentCount = (clone $importsQuery)->where('status', 'pending_assignment')->count();
        $totalImports = (clone $importsQuery)->count() + (clone $textImportsQuery)->count();

        $emailsQuery = EmailAccount::query()->where('status', 'active');
        if (auth()->user()->isUser()) {
            $emailsQuery->whereHas('profiles.users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()));
        } elseif (auth()->user()->isCuentas()) {
            $emailsQuery->where(function ($accountQuery) {
                $accountQuery->where('created_by', auth()->id())
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($creatorQuery) => $creatorQuery->where('role', 'admin'));
            });
        } elseif (auth()->user()->isSupervisor()) {
            $monitoredIds = auth()->user()->monitoredCollaboratorIds() ?? [0];
            $emailsQuery->where(function ($accountQuery) use ($monitoredIds) {
                $accountQuery->where('created_by', auth()->id())
                    ->orWhereHas('profiles.users', fn ($userQuery) => $userQuery->whereIn('users.id', $monitoredIds));
            });
        }

        $driveConfigured = filled(config('services.google.drive.client_id'))
            && filled(config('services.google.drive.client_secret'))
            && filled(config('services.google.drive.refresh_token'));

        $driver = config('database.connections.'.config('database.default').'.driver', 'database');
        $databaseName = $driver === 'mysql' ? 'MariaDB / MySQL' : ucfirst((string) $driver);

        $activity = collect();
        $imports = collect();
        $combinedImports = collect();
        $exportImportRecords = collect();
        $textImportRecords = collect();
        $textImportActivityCounts = [];
        $detectedImportProfileNames = [];
        if ($this->activityTab === 'recent') {
            $activity = $this->activityQuery()
                ->with(['profile', 'evidenceType'])
                ->orderByRaw('COALESCE(external_published_at, created_at) DESC')
                ->paginate(50, pageName: 'activityPage');
        } elseif ($this->activityTab === 'imports') {
            $combinedImports = $this->combinedImportsQuery()->paginate(50, pageName: 'importsPage');
            $rows = $combinedImports->getCollection();
            $exportIds = $rows->where('source_type', 'export')->pluck('source_id');
            $textImportIds = $rows->where('source_type', 'text')->pluck('source_id');

            $exportImportRecords = FacebookImportLog::query()
                ->with(['profile', 'user', 'facebookUsers'])
                ->whereIn('id', $exportIds)
                ->get()
                ->keyBy('id');
            $textImportRecords = EvidenceTextImport::query()
                ->with(['profile', 'user'])
                ->whereIn('id', $textImportIds)
                ->get()
                ->keyBy('id');
            $textImportActivityCounts = $this->resolveTextImportActivityCounts($textImportRecords->values());
            $detectedImportProfileNames = app(FacebookImportProfileNameResolver::class)
                ->resolveMany($exportImportRecords->values());
        } else {
            $logQuery = $this->importsQuery()->with(['profile', 'facebookUsers']);
            if ($this->activityTab === 'pending') {
                $logQuery->whereIn('status', ['downloaded', 'processing']);
            } elseif ($this->activityTab === 'unassigned') {
                $logQuery->where('status', 'pending_assignment');
            }
            $imports = $logQuery->latest('downloaded_at')->paginate(50, pageName: 'importsPage');
            $detectedImportProfileNames = app(FacebookImportProfileNameResolver::class)
                ->resolveMany($imports->getCollection());
        }

        if (! in_array($this->chartPeriod, ['7', '14', '30'], true)) {
            $this->chartPeriod = '7';
        }
        if (! in_array($this->chartGrouping, ['day', 'week'], true)) {
            $this->chartGrouping = 'day';
        }

        $periodDays = (int) $this->chartPeriod;
        $chartStart = now()->startOfDay()->subDays($periodDays - 1);
        $chartEnd = now();
        $dateExpression = 'DATE(COALESCE(evidence.external_published_at, evidence.created_at))';
        $chartRows = $this->activityQuery()
            ->join('evidence_types', 'evidence_types.id', '=', 'evidence.evidence_type_id')
            ->whereBetween(DB::raw('COALESCE(evidence.external_published_at, evidence.created_at)'), [$chartStart, $chartEnd])
            ->selectRaw($dateExpression.' as activity_date, evidence_types.name as activity_type, COUNT(*) as total')
            ->groupByRaw($dateExpression.', evidence_types.name')
            ->get();

        $chartBuckets = [];
        $cursor = $chartStart->copy();
        $today = now()->startOfDay();
        while ($cursor->lessThanOrEqualTo($today)) {
            $bucketStart = $this->chartGrouping === 'week' ? $cursor->copy()->startOfWeek() : $cursor->copy();
            $bucketKey = $bucketStart->toDateString();

            if (! isset($chartBuckets[$bucketKey])) {
                if ($this->chartGrouping === 'week') {
                    $visibleStart = $bucketStart->greaterThan($chartStart) ? $bucketStart : $chartStart;
                    $weekEnd = $bucketStart->copy()->endOfWeek();
                    $visibleEnd = $weekEnd->lessThan($today) ? $weekEnd : $today;
                    $label = $visibleStart->format('d').'–'.$visibleEnd->format('d M');
                } else {
                    $label = $periodDays > 7 ? $cursor->format('d') : $cursor->translatedFormat('d M');
                }

                $chartBuckets[$bucketKey] = [
                    'key' => $bucketKey,
                    'label' => $label,
                    'comments' => 0,
                    'reactions' => 0,
                    'shares' => 0,
                ];
            }

            $cursor->addDay();
        }

        foreach ($chartRows as $row) {
            $activityDate = Carbon::parse($row->activity_date);
            $bucketKey = ($this->chartGrouping === 'week' ? $activityDate->copy()->startOfWeek() : $activityDate)->toDateString();
            if (! isset($chartBuckets[$bucketKey])) {
                continue;
            }

            $typeName = mb_strtolower((string) $row->activity_type);
            if (str_contains($typeName, 'compart')) {
                $chartBuckets[$bucketKey]['shares'] += (int) $row->total;
            } elseif (str_contains($typeName, 'reacci') || str_contains($typeName, 'like')) {
                $chartBuckets[$bucketKey]['reactions'] += (int) $row->total;
            } elseif (str_contains($typeName, 'coment')) {
                $chartBuckets[$bucketKey]['comments'] += (int) $row->total;
            }
        }

        $chart = collect(array_values($chartBuckets));

        $chartMax = max(1, (int) $chart->flatMap(fn ($day) => [$day['comments'], $day['reactions'], $day['shares']])->max());
        $chartHasActivity = $chart->sum(fn ($day) => $day['comments'] + $day['reactions'] + $day['shares']) > 0;

        return view('livewire.admin.facebook-monitoring.index', [
            'profiles' => $profilesQuery
                ->orderByRaw('CASE WHEN latest_facebook_export_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('latest_facebook_export_at')
                ->orderByDesc('profiles.created_at')
                ->orderByDesc('profiles.id')
                ->paginate(6),
            'collaborators' => auth()->user()->canMonitorCollaborators()
                ? User::query()->where('role', 'user')->where('is_active', true)->forAdminHierarchy(auth()->user())->orderBy('name')->get()
                : collect(),
            'emails' => $emailsQuery->orderBy('email')->get(),
            'totalProfiles' => $totalProfilesCount,
            'profilesWithActivityCount' => $profilesWithActivityCount,
            'profilesWithoutActivityCount' => $profilesWithoutActivityCount,
            'profilesMissingIdCount' => $profilesMissingIdCount,
            'activityCoveragePercent' => $activityCoveragePercent,
            'profilesWithActivity' => $profilesWithActivity,
            'profilesWithoutActivity' => $profilesWithoutActivity,
            'totalImports' => $totalImports,
            'processingCount' => $processingCount,
            'pendingAssignmentCount' => $pendingAssignmentCount,
            'driveConfigured' => $driveConfigured,
            'databaseName' => $databaseName,
            'activity' => $activity,
            'imports' => $imports,
            'combinedImports' => $combinedImports,
            'exportImportRecords' => $exportImportRecords,
            'textImportRecords' => $textImportRecords,
            'textImportActivityCounts' => $textImportActivityCounts,
            'detectedImportProfileNames' => $detectedImportProfileNames,
            'chart' => $chart,
            'chartMax' => $chartMax,
            'chartHasActivity' => $chartHasActivity,
            'chartRangeLabel' => 'Últimos '.$periodDays.' días',
            'chartGroupingLabel' => $this->chartGrouping === 'day' ? 'por día' : 'por semana',
        ])->layout('layouts.app');
    }
}
