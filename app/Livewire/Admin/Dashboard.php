<?php

namespace App\Livewire\Admin;

use App\Models\EmailAccount;
use App\Models\Evidence;
use App\Models\EvidenceType;
use App\Models\Profile;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public $todayCount = 0;

    public $weekCount = 0;

    public $monthCount = 0;

    public $usersCount = 0;

    // Cuentas Summary
    public $accountsToday = 0;

    public $accountsWeek = 0;

    public $accountsMonth = 0;

    public $profilesToday = 0;

    public $profilesWeek = 0;

    public $profilesMonth = 0;

    public $cuentasUsersCount = 0;

    // Filters
    public $filterDateFrom = '';

    public $filterDateTo = '';

    // Charts Data (Evidences)
    public $evidencesByUser = [];

    public $evidencesByDate = [];

    public $evidencesByType = [];

    public $evidencesByNetwork = [];

    // Charts Data (Cuentas)
    public $accountsByDate = [];

    public $accountsByGestor = [];

    public $topAccountCreators = [];

    public function mount()
    {
        // Default to current month only on first load
        $this->filterDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->filterDateTo = now()->endOfMonth()->format('Y-m-d');

        $this->refreshDashboard(false);
    }

    public function refreshDashboard($dispatch = true)
    {
        $now = now();

        $this->todayCount = Evidence::whereDate('created_at', $now->toDateString())->count();
        $this->weekCount = Evidence::whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $this->monthCount = Evidence::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $this->usersCount = User::where('is_active', true)->where('role', 'user')->count();

        $this->accountsToday = EmailAccount::whereDate('created_at', $now->toDateString())->count();
        $this->accountsWeek = EmailAccount::whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $this->accountsMonth = EmailAccount::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

        $this->profilesToday = Profile::whereDate('created_at', $now->toDateString())->count();
        $this->profilesWeek = Profile::whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $this->profilesMonth = Profile::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();

        $this->cuentasUsersCount = User::where('is_active', true)->where('role', 'cuentas')->count();

        $this->loadChartData();

        if ($dispatch) {
            $this->dispatch('update-charts',
                dataDate: $this->evidencesByDate,
                dataUser: $this->evidencesByUser,
                dataType: $this->evidencesByType,
                dataNet: $this->evidencesByNetwork,
                dataAccountDate: $this->accountsByDate,
                dataAccountGestor: $this->accountsByGestor,
                dataTopCreators: $this->topAccountCreators
            );
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['filterDateFrom', 'filterDateTo'])) {
            $this->loadChartData();
            $this->dispatch('update-charts',
                dataDate: $this->evidencesByDate,
                dataUser: $this->evidencesByUser,
                dataType: $this->evidencesByType,
                dataNet: $this->evidencesByNetwork,
                dataAccountDate: $this->accountsByDate,
                dataAccountGestor: $this->accountsByGestor,
                dataTopCreators: $this->topAccountCreators
            );
        }
    }

    public function loadChartData()
    {
        $queryBase = Evidence::query();
        $accountsBase = EmailAccount::query();

        if ($this->filterDateFrom) {
            $queryBase->whereDate('created_at', '>=', $this->filterDateFrom);
            $accountsBase->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $queryBase->whereDate('created_at', '<=', $this->filterDateTo);
            $accountsBase->whereDate('created_at', '<=', $this->filterDateTo);
        }

        /* --- EVIDENCES --- */
        $this->evidencesByUser = User::where('role', 'user')
            ->withCount(['evidences' => function ($query) {
                if ($this->filterDateFrom) {
                    $query->whereDate('created_at', '>=', $this->filterDateFrom);
                }
                if ($this->filterDateTo) {
                    $query->whereDate('created_at', '<=', $this->filterDateTo);
                }
            }])
            ->orderBy('evidences_count', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($u) => ['name' => $u->name, 'count' => $u->evidences_count])
            ->toArray();

        $countsByDate = (clone $queryBase)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('count', 'date');

        $this->evidencesByDate = $this->fillDateGaps($countsByDate);

        $this->evidencesByType = EvidenceType::withCount(['evidences' => function ($query) {
            if ($this->filterDateFrom) {
                $query->whereDate('created_at', '>=', $this->filterDateFrom);
            }
            if ($this->filterDateTo) {
                $query->whereDate('created_at', '<=', $this->filterDateTo);
            }
        }])
            ->get()
            ->map(fn ($t) => ['name' => $t->name, 'count' => $t->evidences_count])
            ->toArray();

        $this->evidencesByNetwork = (clone $queryBase)
            ->selectRaw('social_network as name, count(*) as count')
            ->groupBy('social_network')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        /* --- ACCOUNTS --- */
        $accountCountsByDate = (clone $accountsBase)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('count', 'date');

        $this->accountsByDate = $this->fillDateGaps($accountCountsByDate);

        $this->accountsByGestor = (clone $accountsBase)
            ->selectRaw('created_by, count(*) as count')
            ->groupBy('created_by')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($a) {
                $user = User::find($a->created_by);

                return ['name' => $user ? $user->name : 'N/A', 'count' => $a->count];
            })
            ->toArray();

        $this->topAccountCreators = User::whereIn('role', ['cuentas', 'admin'])
            ->withCount(['emailAccounts' => function ($query) {
                if ($this->filterDateFrom) {
                    $query->whereDate('created_at', '>=', $this->filterDateFrom);
                }
                if ($this->filterDateTo) {
                    $query->whereDate('created_at', '<=', $this->filterDateTo);
                }
            }])
            ->orderBy('email_accounts_count', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($u) => ['name' => $u->name, 'count' => $u->email_accounts_count])
            ->toArray();
    }

    private function fillDateGaps($countsByDate)
    {
        $filled = [];
        if ($this->filterDateFrom && $this->filterDateTo) {
            $start = Carbon::parse($this->filterDateFrom);
            $end = Carbon::parse($this->filterDateTo);

            if ($start->diffInDays($end) > 60) {
                $start = $end->copy()->subDays(60);
            }

            while ($start->lte($end)) {
                $dateStr = $start->format('Y-m-d');
                $filled[] = ['date' => $dateStr, 'count' => $countsByDate->get($dateStr, 0)];
                $start->addDay();
            }
        } else {
            foreach ($countsByDate as $date => $count) {
                $filled[] = ['date' => $date, 'count' => $count];
            }
        }

        return $filled;
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
