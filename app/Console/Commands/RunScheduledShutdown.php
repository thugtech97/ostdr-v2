<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\MaintenanceMode;
use Illuminate\Console\Command;

/**
 * Takes the app down when a scheduled shutdown comes due. Legacy did this from the
 * browser of whoever had a page open at that minute; here the scheduler does it.
 */
class RunScheduledShutdown extends Command
{
    protected $signature = 'ostr:scheduled-shutdown';

    protected $description = 'Put the application into maintenance mode when a scheduled shutdown is due';

    public function handle(MaintenanceMode $maintenance): int
    {
        if ($maintenance->isDown()) {
            return self::SUCCESS;
        }

        // Only a schedule that fell due in the last few minutes, so an admin who starts the
        // app again afterwards is not shut out by the same schedule.
        $due = Application::query()->live()
            ->whereBetween('scheduled_date', [today()->subDay()->toDateString(), today()->toDateString()])
            ->get()
            ->first(fn (Application $schedule) => $schedule->scheduledAt()->between(now()->subMinutes(5), now()));

        if (! $due) {
            return self::SUCCESS;
        }

        $maintenance->down();
        $this->info("Application is down for: {$due->reason}");

        return self::SUCCESS;
    }
}
