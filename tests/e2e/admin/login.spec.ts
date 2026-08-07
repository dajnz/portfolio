import { execSync } from 'node:child_process';

import { expect, test } from '@playwright/test';

import { ADMIN_LOGIN, ADMIN_PASSWORD } from '../fixtures/seed';

/**
 * The admin login lifecycle (authentication.md:15-29, 264-283). Tests are
 * serial: they share the e2e admin row and the `throttle:login` limiter
 * (5 attempts/min/IP), so a parallel burst would trip the rate limit.
 *
 * The credential tests run in FRESH browser contexts (no storage state):
 * a successful login regenerates the session, which DESTROYS the shared
 * `.auth/admin.json` session data — subsequent storage-state tests (logout,
 * TOTP redirect) would otherwise start as guests. The storage state stays
 * reserved for tests that never log in.
 *
 * The session-version test mutates the shared admin row (bump + restore),
 * so it also runs isolated and restores the version in a finally block.
 */
test.describe.configure({ mode: 'serial' });

test.describe('admin login', () => {
    test('shows an inline error for invalid credentials', async ({ browser }) => {
        // `storageState: null` overrides the project's storage state — the
        // credential tests must start unauthenticated (see the describe
        // comment above).
        const context = await browser.newContext({ storageState: undefined });
        const page = await context.newPage();

        try {
            await page.goto('/admin/login');

            await page.getByTestId('login-page-login-input').fill(ADMIN_LOGIN);
            await page.getByTestId('login-page-password-input').fill('wrong-password');
            await page.getByTestId('login-page-submit-btn').click();

            await expect(page.getByTestId('login-page-error')).toBeVisible();
        } finally {
            await context.close();
        }
    });

    test('logs in with valid credentials and redirects to the dashboard', async ({ browser }) => {
        // `storageState: null` overrides the project's storage state — the
        // credential tests must start unauthenticated (see the describe
        // comment above).
        const context = await browser.newContext({ storageState: undefined });
        const page = await context.newPage();

        try {
            await page.goto('/admin/login');

            await page.getByTestId('login-page-login-input').fill(ADMIN_LOGIN);
            await page.getByTestId('login-page-password-input').fill(ADMIN_PASSWORD);
            await page.getByTestId('login-page-submit-btn').click();

            // `/admin` (the dashboard) is a 4.x route — the redirect itself is
            // what the auth flow promises (authentication.md:20,279).
            await page.waitForURL('**/admin');
        } finally {
            await context.close();
        }
    });

    test('logs out from the admin header', async ({ page }) => {
        // The shared storage state is already authenticated (auth.setup.ts).
        await page.goto('/admin/profile');

        await page.getByTestId('admin-header-logout').click();

        await page.waitForURL('**/admin/login');
        await expect(page.getByTestId('login-page-form')).toBeVisible();
    });

    test('redirects the TOTP page to login when no TOTP verification is pending', async ({ page }) => {
        await page.goto('/admin/login/totp');

        // `totp.pending` middleware (authentication.md:266-271).
        await page.waitForURL('**/admin/login');
        await expect(page.getByTestId('login-page-form')).toBeVisible();
    });

    test('forces logout of other sessions after a session-version bump', async ({ browser }) => {
        // Fresh context: the mutation must not touch the shared storage
        // state used by the other tests (authentication.md:283,309).
        // `storageState: null` overrides the project's storage state — the
        // credential tests must start unauthenticated (see the describe
        // comment above).
        const context = await browser.newContext({ storageState: undefined });
        const page = await context.newPage();

        try {
            await page.goto('/admin/login');

            await page.getByTestId('login-page-login-input').fill(ADMIN_LOGIN);
            await page.getByTestId('login-page-password-input').fill(ADMIN_PASSWORD);
            await page.getByTestId('login-page-submit-btn').click();
            await page.waitForURL('**/admin');

            execSync(
                "php artisan tinker --execute=\"\\App\\Features\\Auth\\Infra\\Models\\AdminModel::query()->where('login', 'e2e-admin')->increment('session_version')\"",
            );

            await page.goto('/admin/profile');

            // `auth.session_version` mismatch → forced logout + flash.
            await page.waitForURL('**/admin/login');
            await expect(page.getByTestId('login-page-form')).toBeVisible();
        } finally {
            // Restore the shared admin row for subsequent test runs.
            execSync(
                "php artisan tinker --execute=\"\\App\\Features\\Auth\\Infra\\Models\\AdminModel::query()->where('login', 'e2e-admin')->update(['session_version' => 0])\"",
            );
            await context.close();
        }
    });
});
