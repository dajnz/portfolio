import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

/**
 * Seeds the e2e database state before all projects. Runs once per
 * Playwright invocation; `E2ETestSeeder` is idempotent
 * (TRUNCATE ... RESTART IDENTITY CASCADE) and never runs in production.
 *
 * `make test-e2e` points this process at the DEDICATED `portfolio_e2e`
 * database + Redis DB 1 via env overrides (DB_DATABASE/REDIS_DB — they
 * propagate to the artisan calls through process.env), so the dev
 * database is never touched. The settings/feature-flag caches (Redis,
 * 1-min TTL) are cleared so the reseeded rows are served immediately.
 */
export default function globalSetup(): void {
    mkdirSync('.auth', { recursive: true });

    execSync('php artisan db:seed --class=E2ETestSeeder', { stdio: 'inherit' });
    execSync('php artisan cache:clear', { stdio: 'inherit' });
}
