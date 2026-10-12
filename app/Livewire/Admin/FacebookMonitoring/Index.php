<?php

namespace App\Livewire\Admin\FacebookMonitoring;

use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\FacebookImportLog;
use App\Models\Profile;
use App\Services\FacebookProfileIdentifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $monitorName = '';

    public string $monitorFacebookUrl = '';

    public string $monitorEmailAccountId = '';

    public string $activityTab = 'recent';

    public string $activityProfileId = '';

    public string $chartPeriod = '7';

    public string $chartGrouping = 'day';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function registerMonitoredFacebookProfile(): void
    {
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
        }
    }

    public function showProfileActivity(int $profileId): void
    {
        $this->activityProfileId = (string) $profileId;
        $this->activityTab = 'recent';
    }

    public function showAllActivity(): void
    {
        $this->activityProfileId = '';
        $this->activityTab = 'recent';
    }

    private function restrictToManageableProfiles($query)
    {
        if (auth()->user()->isCuentas()) {
            $query->where(function ($profileQuery) {
                $profileQuery->where('created_by', auth()->id())
                    ->orWhereHas('users', fn ($userQuery) => $userQuery->where('users.id', auth()->id()))
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($creatorQuery) => $creatorQuery->where('role', 'admin'));
            });
        }

        return $query;
    }

    private function facebookProfilesQuery()
    {
        $query = $this->restrictToManageableProfiles(Profile::query()
            ->with('emailAccount')
            ->whereRaw('LOWER(social_network) = ?', ['facebook']));

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

        if (auth()->user()->isCuentas()) {
            $query->where(function ($importsQuery) {
                $importsQuery->whereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery))
                    ->orWhere(function ($unassignedQuery) {
                        $unassignedQuery->whereNull('profile_id')->where('status', 'pending_assignment');
                    });
            });
        }

        if ($this->activityProfileId !== '') {
            $query->where('profile_id', $this->activityProfileId);
        }

        return $query;
    }

    private function activityQuery()
    {
        $query = Evidence::query()
            ->where('origin', 'google_drive')
            ->whereRaw('LOWER(social_network) = ?', ['facebook']);

        if (auth()->user()->isCuentas()) {
            $query->whereHas('profile', fn ($profileQuery) => $this->restrictToManageableProfiles($profileQuery));
        }

        if ($this->activityProfileId !== '') {
            $query->where('profile_id', $this->activityProfileId);
        }

        return $query;
    }

    public function render()
    {
        $profilesQuery = $this->facebookProfilesQuery()
            ->withCount([
                'facebookImportLogs as facebook_exports_count',
                'facebookCommentsThroughImports as facebook_comments_count',
                'facebookReactionsThroughImports as facebook_reactions_count',
                'evidences as facebook_shares_count' => fn ($query) => $query
                    ->where('origin', 'google_drive')
                    ->whereRaw('LOWER(social_network) = ?', ['facebook'])
                    ->whereHas('evidenceType', fn ($typeQuery) => $typeQuery->where('name', 'Compartido')),
            ])
            ->withMax('facebookImportLogs as latest_facebook_export_at', 'downloaded_at');

        $profileCountQuery = $this->restrictToManageableProfiles(
            Profile::query()->whereRaw('LOWER(social_network) = ?', ['facebook'])
        );

        $importsQuery = $this->importsQuery();
        $processingCount = (clone $importsQuery)->whereIn('status', ['downloaded', 'processing'])->count();
        $pendingAssignmentCount = (clone $importsQuery)->where('status', 'pending_assignment')->count();

        $emailsQuery = EmailAccount::query()->where('status', 'active');
        if (auth()->user()->isCuentas()) {
            $emailsQuery->where(function ($accountQuery) {
                $accountQuery->where('created_by', auth()->id())
                    ->orWhereNull('created_by')
                    ->orWhereHas('creator', fn ($creatorQuery) => $creatorQuery->where('role', 'admin'));
            });
        }

        $driveConfigured = filled(config('services.google.drive.client_id'))
            && filled(config('services.google.drive.client_secret'))
            && filled(config('services.google.drive.refresh_token'));

        $driver = config('database.connections.'.config('database.default').'.driver', 'database');
        $databaseName = $driver === 'mysql' ? 'MariaDB / MySQL' : ucfirst((string) $driver);

        $activity = collect();
        $imports = collect();
        if ($this->activityTab === 'recent') {
            $activity = $this->activityQuery()
                ->with(['profile', 'evidenceType'])
                ->orderByRaw('COALESCE(external_published_at, created_at) DESC')
                ->limit(8)
                ->get();
        } else {
            $logQuery = $this->importsQuery()->with(['profile', 'facebookUsers']);
            if ($this->activityTab === 'pending') {
                $logQuery->whereIn('status', ['downloaded', 'processing']);
            } elseif ($this->activityTab === 'unassigned') {
                $logQuery->where('status', 'pending_assignment');
            }
            $imports = $logQuery->latest('downloaded_at')->limit(8)->get();
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
            'profiles' => $profilesQuery->latest()->paginate(6),
            'emails' => $emailsQuery->orderBy('email')->get(),
            'totalProfiles' => $profileCountQuery->count(),
            'totalImports' => (clone $importsQuery)->count(),
            'processingCount' => $processingCount,
            'pendingAssignmentCount' => $pendingAssignmentCount,
            'driveConfigured' => $driveConfigured,
            'databaseName' => $databaseName,
            'activity' => $activity,
            'imports' => $imports,
            'chart' => $chart,
            'chartMax' => $chartMax,
            'chartHasActivity' => $chartHasActivity,
            'chartRangeLabel' => 'Últimos '.$periodDays.' días',
            'chartGroupingLabel' => $this->chartGrouping === 'day' ? 'por día' : 'por semana',
        ])->layout('layouts.app');
    }
}
