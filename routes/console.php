<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::useCache('file');
Schedule::command('notifications:retry-failed-deliveries')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('notifications:dispatch-followup-due')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('notifications:dispatch-followup-overdue')->hourly()->withoutOverlapping();
Schedule::command('notifications:prune-domain-events')->dailyAt('02:30')->withoutOverlapping();
