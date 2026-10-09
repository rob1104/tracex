<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TraceX: monitoreo de usuarios mediante exportaciones oficiales de Facebook.
use App\Models\Importacion;
use Illuminate\Support\Facades\Schedule;

Schedule::command('drive:importar')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function () {
    Importacion::whereIn('estado', ['sin_dueno', 'rechazado', 'error'])
        ->where('created_at', '<', now()->subDays(7))
        ->get()
        ->each->delete();
})->daily()->name('limpiar-cargas-sin-asignar');
