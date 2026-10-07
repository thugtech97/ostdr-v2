<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled shutdowns from Application Maintenance. Needs the scheduler running
// (php artisan schedule:work, or schedule:run every minute from Task Scheduler).
Schedule::command('ostr:scheduled-shutdown')->everyMinute()->withoutOverlapping();
