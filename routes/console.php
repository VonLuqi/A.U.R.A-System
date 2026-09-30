<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Etapa C §2.4 — HostGator cron should call `php artisan schedule:run` every minute.
Schedule::command('statements:purge-files')->dailyAt('03:15');

// Etapa H §4.4 — due-date reminders (credit cards + loans). Cron entry unchanged.
Schedule::command('aura:check-due-dates')->dailyAt('08:00');
