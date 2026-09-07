import { expect, test } from '@playwright/test';

import { reportAdminAxeWarnings } from '../fixtures/audit';

/**
 * The admin dashboard (testing.md:490): the four stat cards render with
 * values (no skeleton/error state) and the quick links point at the
 * matching admin sections. Content-count assertions are avoided — other
 * admin specs publish content in parallel.
 */
test.describe('admin dashboard', () => {
    test('loads the stat cards and quick links', async ({ page }, testInfo) => {
        await page.goto('/admin');
        await expect(page.getByTestId('dashboard-page')).toBeVisible();

        const cards = page.locator('[data-testid^="dashboard-page-stats-card-"]');

        await expect(cards).toHaveCount(4);

        for (let index = 0; index < 4; index++) {
            await expect(cards.nth(index)).not.toBeEmpty();
        }

        const quickLinks = page.locator('[data-testid^="dashboard-page-quick-links-link-"]');

        await expect(quickLinks).toHaveCount(4);
        await expect(quickLinks.nth(0)).toHaveAttribute('href', '/admin/projects/create');
        await expect(quickLinks.nth(1)).toHaveAttribute('href', '/admin/blog/create');
        await expect(quickLinks.nth(2)).toHaveAttribute('href', '/admin/feedback');
        await expect(quickLinks.nth(3)).toHaveAttribute('href', '/admin/backups');

        await reportAdminAxeWarnings(page, testInfo);
    });
});
