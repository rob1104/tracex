<?php

namespace App\Livewire\Cuentas;

use App\Models\EmailAccount;
use App\Models\Profile;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $userId = auth()->id();
        $now = Carbon::now();

        // Cuentas de correo
        $emailsQuery = EmailAccount::where('created_by', $userId);
        $emailsToday = (clone $emailsQuery)->whereDate('created_at', $now->toDateString())->count();
        $emailsWeek = (clone $emailsQuery)->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $emailsMonth = (clone $emailsQuery)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $emailsTotal = (clone $emailsQuery)->count();

        // Perfiles
        $profilesQuery = Profile::where('created_by', $userId);
        $profilesToday = (clone $profilesQuery)->whereDate('created_at', $now->toDateString())->count();
        $profilesWeek = (clone $profilesQuery)->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->count();
        $profilesMonth = (clone $profilesQuery)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $profilesTotal = (clone $profilesQuery)->count();

        return view('livewire.cuentas.dashboard', compact(
            'emailsToday', 'emailsWeek', 'emailsMonth',
            'profilesToday', 'profilesWeek', 'profilesMonth', 'emailsTotal', 'profilesTotal'
        ));
    }
}

