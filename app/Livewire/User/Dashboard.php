<?php

namespace App\Livewire\User;

use Livewire\Component;

class Dashboard extends Component
{
    public $todayCount = 0;

    public $weekCount = 0;

    public $monthCount = 0;

    public function mount()
    {
        $user = auth()->user();
        $now = now();

        $this->todayCount = $user->evidences()->whereDate('created_at', $now->toDateString())->count();
        $this->weekCount = $user->evidences()->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $this->monthCount = $user->evidences()->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
    }

    public function render()
    {
        return view('livewire.user.dashboard');
    }
}
