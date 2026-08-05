<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled commands (backup.md:465-467, content-management.md:854,928):
 * the Shared maintenance commands run monthly at 03:00 on the 1st. The
 * analytics (`analytics:generate-reports`, `analytics:prune-events`) and
 * backup (`backup:create`) schedules land with their features (steps
 * 11.1 / 12.3 per B22).
 */

Schedule::command('app:cleanup-orphaned-files')->cron('0 3 1 * *');
Schedule::command('app:prune-trashed-items')->cron('0 3 1 * *');
