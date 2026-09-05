<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * Scheduled commands (backup.md:463-467, content-management.md:854,928):
 * the Shared maintenance commands run monthly at 03:00 on the 1st.
 * `analytics:prune-events` runs monthly at 03:00 on the 1st (the 90-day
 * retention safety net, backup.md:464). The daily metrics generation
 * runs at 02:00 daily (analytics.md:182 — supersedes B22's old
 * `analytics:generate-reports` every-4-hours cadence); the manual full
 * rebuild command `analytics:generate-reports` (11.3.3) is NOT
 * scheduled — manual only. `backup:create` runs daily at 02:00
 * (backup.md:460).
 */

Schedule::command('app:cleanup-orphaned-files')->cron('0 3 1 * *');
Schedule::command('app:prune-trashed-items')->cron('0 3 1 * *');
Schedule::command('analytics:prune-events')->cron('0 3 1 * *');
Schedule::command('analytics:generate-daily-metrics')->dailyAt('02:00');
Schedule::command('backup:create')->dailyAt('02:00');
