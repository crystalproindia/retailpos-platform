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
Schedule::command('retailpos:lead-followup-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('retailpos:onboarding-reminders')->hourly()->withoutOverlapping();
Schedule::command('retailpos:support-ticket-reminders')->hourly()->withoutOverlapping();
Schedule::command('retailpos:crm-refresh-lead-scores --stale')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('notifications:prune-domain-events')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('operations:health-check')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('operations:capture-queue-snapshot')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('operations:prune-health-checks')->dailyAt('03:00')->withoutOverlapping();
