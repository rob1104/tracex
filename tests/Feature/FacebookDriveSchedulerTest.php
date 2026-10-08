<?php

namespace Tests\Feature;

use App\Models\FacebookImportLog;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Tests\TestCase;

class FacebookDriveSchedulerTest extends TestCase
{
    public function test_facebook_sync_drive_is_registered_in_schedule(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $syncEvent = $events->first(function (Event $event) {
            return str_contains($event->command, 'facebook:sync-drive');
        });

        $this->assertNotNull($syncEvent, 'facebook:sync-drive command should be scheduled.');
        $this->assertTrue($syncEvent->withoutOverlapping, 'facebook:sync-drive must prevent overlapping runs.');
        $this->assertTrue($syncEvent->runInBackground, 'facebook:sync-drive must run in background.');
        $this->assertSame('*/15 * * * *', $syncEvent->expression);
    }

    public function test_model_prune_for_facebook_import_logs_is_scheduled_daily(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $pruneEvent = $events->first(function (Event $event) {
            return str_contains($event->command, 'model:prune')
                && str_contains($event->command, FacebookImportLog::class);
        });

        $this->assertNotNull($pruneEvent, 'model:prune for FacebookImportLog should be scheduled.');
        $this->assertSame('0 0 * * *', $pruneEvent->expression);
    }

    public function test_custom_cron_expression_is_supported(): void
    {
        config(['services.google.drive.schedule_frequency' => '0 */2 * * *']);

        $newSchedule = new Schedule;
        $this->app->instance(Schedule::class, $newSchedule);
        ScheduleFacade::swap($newSchedule);

        require base_path('routes/console.php');

        $events = collect($newSchedule->events());
        $syncEvent = $events->first(function (Event $event) {
            return str_contains($event->command, 'facebook:sync-drive');
        });

        $this->assertNotNull($syncEvent);
        $this->assertSame('0 */2 * * *', $syncEvent->expression);
    }
}
