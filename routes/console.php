<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * `analytics:prune-events` is the 90-day retention safety net; the
 * manual full rebuild command `analytics:generate-reports` is
 * intentionally not scheduled.
 */

Schedule::command('app:cleanup-orphaned-files')->cron('0 3 1 * *');
Schedule::command('app:prune-trashed-items')->cron('0 3 1 * *');
Schedule::command('analytics:prune-events')->cron('0 3 1 * *');
Schedule::command('analytics:generate-daily-metrics')->dailyAt('02:00');
Schedule::command('backup:create')->dailyAt('02:00');
