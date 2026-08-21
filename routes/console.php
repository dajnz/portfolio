<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled commands (backup.md:463-467, content-management.md:854,928):
 * the Shared maintenance commands run monthly at 03:00 on the 1st.
 * `analytics:prune-events` runs monthly at 03:00 on the 1st (the 90-day
 * retention safety net, backup.md:464). The daily metrics generation
 * schedule (`0 2 * * *`) lands with the daily aggregation (step 11.3.2 —
 * it supersedes the old `analytics:generate-reports` every-4-hours
 * cadence, B22/B290). `backup:create` lands with the Backup feature
 * (step 12.3, B22).
 */

Schedule::command('app:cleanup-orphaned-files')->cron('0 3 1 * *');
Schedule::command('app:prune-trashed-items')->cron('0 3 1 * *');
Schedule::command('analytics:prune-events')->cron('0 3 1 * *');
