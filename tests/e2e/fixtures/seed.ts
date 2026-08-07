import type { Page } from '@playwright/test';

/**
 * Shared e2e seed helpers (testing.md:550-552) — the known admin
 * credentials mirror `database/seeders/E2ETestSeeder.php` and the
 * `loginAsAdmin` flow used by `auth.setup.ts` and the login spec.
 */
export const ADMIN_LOGIN = 'e2e-admin';

export const ADMIN_PASSWORD = 'E2e-Admin-Password-123';

/** Logs in via the login page and waits for the dashboard redirect. */
export async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login');
    await page.getByTestId('login-page-login-input').fill(ADMIN_LOGIN);
    await page.getByTestId('login-page-password-input').fill(ADMIN_PASSWORD);
    await page.getByTestId('login-page-submit-btn').click();
    await page.waitForURL('**/admin');
}
