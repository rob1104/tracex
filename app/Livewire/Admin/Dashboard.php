<?php

namespace App\Livewire\Admin;

use App\Models\Evidence;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    public $todayCount = 0;
    public $weekCount = 0;
    public $monthCount = 0;
    public $usersCount = 0;

    // Filters
    public $filterDateFrom = '';
    public $filterDateTo = '';

    // Charts Data
    public $evidencesByUser = [];
    public $evidencesByDate = [];
    public $evidencesByType = [];
    public $evidencesByNetwork = [];

    public function mount()
    {
        $now = now();

        $this->todayCount = Evidence::whereDate('created_at', $now->toDateString())->count();
        $this->weekCount = Evidence::whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $this->monthCount = Evidence::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $this->usersCount = User::where('is_active', true)->where('role', 'user')->count();

        // Default to current month
        $this->filterDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->filterDateTo = now()->endOfMonth()->format('Y-m-d');

        $this->loadChartData();
    }

    public function updated($property)
    {
        if (in_array($property, ['filterDateFrom', 'filterDateTo'])) {
            $this->loadChartData();
            $this->dispatch('update-charts', 
                dataDate: $this->evidencesByDate,
                dataUser: $this->evidencesByUser,
                dataType: $this->evidencesByType,
                dataNet: $this->evidencesByNetwork
            );
        }
    }

    public function loadChartData()
    {
        $queryBase = Evidence::query();
        
        if ($this->filterDateFrom) {
            $queryBase->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $queryBase->whereDate('created_at', '<=', $this->filterDateTo);
        }

        // 1. Top 5 Users by Evidences
        // We can't just use withCount because we need to filter evidences by date.
        // So we use whereHas/withCount with constraints, or a join.
        $this->evidencesByUser = User::where('role', 'user')
            ->withCount(['evidences' => function ($query) {
                if ($this->filterDateFrom) $query->whereDate('created_at', '>=', $this->filterDateFrom);
                if ($this->filterDateTo) $query->whereDate('created_at', '<=', $this->filterDateTo);
            }])
            ->orderBy('evidences_count', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($u) => ['name' => $u->name, 'count' => $u->evidences_count])
            ->toArray();

        // 2. Evidences Growth
        $countsByDate = (clone $queryBase)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('count', 'date');
        
        // Build a continuous date range for the chart if dates are valid, else just use the found dates
        $this->evidencesByDate = [];
        if ($this->filterDateFrom && $this->filterDateTo) {
            $start = \Carbon\Carbon::parse($this->filterDateFrom);
            $end = \Carbon\Carbon::parse($this->filterDateTo);
            
            // Limit to max 60 days to prevent massive charts
            if ($start->diffInDays($end) > 60) {
                $start = $end->copy()->subDays(60);
            }
            
            while ($start->lte($end)) {
                $dateStr = $start->format('Y-m-d');
                $this->evidencesByDate[] = ['date' => $dateStr, 'count' => $countsByDate->get($dateStr, 0)];
                $start->addDay();
            }
        } else {
            foreach ($countsByDate as $date => $count) {
                $this->evidencesByDate[] = ['date' => $date, 'count' => $count];
            }
        }

        // 3. Evidences by Type
        $this->evidencesByType = \App\Models\EvidenceType::withCount(['evidences' => function ($query) {
                if ($this->filterDateFrom) $query->whereDate('created_at', '>=', $this->filterDateFrom);
                if ($this->filterDateTo) $query->whereDate('created_at', '<=', $this->filterDateTo);
            }])
            ->get()
            ->map(fn($t) => ['name' => $t->name, 'count' => $t->evidences_count])
            ->toArray();

        // 4. Evidences by Social Network
        $this->evidencesByNetwork = (clone $queryBase)
            ->selectRaw('social_network as name, count(*) as count')
            ->groupBy('social_network')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.dashboard');
    }
}
