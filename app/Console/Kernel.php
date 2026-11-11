<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void {
        $schedule->command('backup:make')
            ->daily()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path() . '/logs/autobackup.log');
    }
}
