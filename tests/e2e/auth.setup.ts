import { test as setup } from '@playwright/test';

import { loginAsAdmin } from './fixtures/seed';

/**
 * Authenticates once with the E2E-seeded credentials and persists the
 * session to `.auth/admin.json` (testing.md:550) — admin tests start
 * already logged in and never repeat the login step.
 */
setup('authenticate as admin', async ({ page }) => {
    await loginAsAdmin(page);
    await page.context().storageState({ path: '.auth/admin.json' });
});
