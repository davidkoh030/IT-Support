<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires `php artisan schedule:work` (or a real cron entry calling
// `schedule:run` every minute) to actually fire - see OPERATIONS.md.
Schedule::command('sla:scan')->everyFiveMinutes()->withoutOverlapping();
