import { defineConfig } from '@playwright/test';

// baseURL is env-driven: `make test-e2e` starts a dedicated e2e server
// (`php artisan serve` on 127.0.0.1:8001 against the `portfolio_e2e`
// database — the dev DB/nginx stack stay untouched), CI points at its own
// `php artisan serve` (port 8000, devops.md:1095-1102, testing.md:576).
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8001';

export default defineConfig({
    // The config lives at the repo root (Playwright resolves it from the
    // cwd — a config nested under tests/e2e would be silently ignored by
    // `npx playwright test`, breaking CI and the plan's run command).
    testDir: './tests/e2e',
    fullyParallel: true,
    retries: 1,
    // The e2e app runs on a single-threaded `php artisan serve` — a wide
    // worker fan-out queues every asset/inertia request behind it. Three
    // workers keep the parallel files moving without starving the
    // server; the generous timeouts absorb the remaining queueing.
    workers: 1,
    timeout: 120_000,
    expect: {
        timeout: 30_000,
    },
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL,
        locale: 'en',
        actionTimeout: 20_000,
        // The dev container (Alpine/musl) cannot run Playwright's glibc
        // chromium builds — the dev image installs system chromium and
        // points at it via PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
        // (devops/docker/dev/Dockerfile). CI keeps the downloaded browser.
        launchOptions: {
            executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined,
        },
        // Screenshot on failure (testing.md:554).
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts/,
        },
        // The admin project owns every mutation: each spec cleans its
        // created rows up, and the public project only starts once the
        // admin project finished — the read-only public assertions then
        // see the seeded baseline (per-file serial blog-management and
        // project-management specs keep their row-count checks stable).
        {
            name: 'admin',
            dependencies: ['setup'],
            testMatch: /admin\//,
            testIgnore: /admin\/(settings|backup|login)\.spec\.ts/,
            retries: 0,
            use: {
                storageState: '.auth/admin.json',
            },
        },
        // login.spec runs in its own project AFTER the mutation specs:
        // its fresh-context credential logins regenerate the Laravel
        // session and thereby invalidate the shared `.auth/admin.json`
        // storage state server-side (B88) — any parallel admin worker
        // would be redirected to the login page mid-test.
        {
            name: 'admin-auth',
            dependencies: ['admin'],
            testMatch: /admin\/login\.spec\.ts/,
            // retries: 0 — the suite does exactly 4 login POSTs and the
            // `login` throttle allows 5/min (authentication.md:21-27);
            // a retry would become the 5th POST and trip a 429.
            retries: 0,
            use: {
                storageState: '.auth/admin.json',
            },
        },
        {
            name: 'public',
            dependencies: ['admin-auth'],
            testMatch: /public\//,
        },
        // The login specs regenerate/logout the shared session, so the
        // storage state is re-established before the stateful settings
        // and backup projects run.
        {
            name: 'reauth',
            dependencies: ['admin-auth', 'public'],
            testMatch: /auth\.setup\.ts/,
        },
        // settings + backup run in their own projects AFTER the parallel
        // admin/public files finish: settings changes (feature flags,
        // per-page counts) and the backup DR restore (which truncates and
        // reseeds the whole e2e database mid-flight) are global state —
        // running them beside the other files would race their assertions.
        {
            name: 'admin-settings',
            dependencies: ['reauth'],
            testMatch: /admin\/settings\.spec\.ts/,
            retries: 0,
            use: {
                storageState: '.auth/admin.json',
            },
        },
        {
            name: 'admin-backup',
            dependencies: ['admin-settings'],
            testMatch: /admin\/backup\.spec\.ts/,
            retries: 0,
            use: {
                storageState: '.auth/admin.json',
            },
        },
    ],
});
