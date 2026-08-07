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
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL,
        locale: 'en',
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
        {
            name: 'public',
            dependencies: ['setup'],
            testMatch: /public\//,
        },
        {
            name: 'admin',
            dependencies: ['setup'],
            testMatch: /admin\//,
            // retries: 0 — the login suite does exactly 4 login POSTs and
            // the `login` throttle allows 5/min (authentication.md:21-27);
            // a retry would become the 5th POST and trip a 429. The suite
            // is deterministic since the storage-state/session fixes.
            retries: 0,
            use: {
                storageState: '.auth/admin.json',
            },
        },
    ],
});
