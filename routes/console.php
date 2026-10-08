<?php

use App\Models\FacebookImportLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tarea 3.4: Automated Google Drive Sync & Retention Scheduler
|--------------------------------------------------------------------------
|
| Periodically scans Google Drive for Meta export folders, downloads JSON
| data, enforces immediate cloud destruction (Zero-Retention), and
| dispatches background processing jobs.
|
*/
$frequency = config('services.google.drive.schedule_frequency', 'everyFifteenMinutes');

$syncSchedule = Schedule::command('facebook:sync-drive')
    ->withoutOverlapping()
    ->runInBackground();

if (method_exists($syncSchedule, $frequency)) {
    $syncSchedule->{$frequency}();
} else {
    $syncSchedule->cron($frequency);
}

/*
|--------------------------------------------------------------------------
| Pruning Old Audit Logs
|--------------------------------------------------------------------------
| Automatically purges import logs older than 30 days via MassPrunable.
*/
Schedule::command('model:prune', ['--model' => [FacebookImportLog::class]])
    ->daily();
