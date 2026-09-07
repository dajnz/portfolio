import { expect, test } from '@playwright/test';

import { expectToast, setCheckbox } from '../fixtures/admin';
import { demoArticles } from '../fixtures/content';

/**
 * The admin settings (testing.md:495): a feature-flag toggle takes
 * effect on the public blog article page and an articles-per-page
 * change takes effect on the public blog index. Every change is
 * reverted, since this project runs between the public project and the
 * backup project — both expect the seeded defaults.
 */
test.describe('admin settings', () => {
    test('toggles the social reactions feature flag', async ({ page }) => {
        await page.goto('/admin/settings');
        await page.getByTestId('settings-page-tab-flags').click();

        const toggle = page.getByTestId('settings-page-flag-social_reactions-toggle');

        // Default: the flag is off and the article page has no widget.
        await expect(toggle).not.toBeChecked();

        await page.goto(`/blog/${demoArticles.queues.slug}`);
        await expect(page.locator('[data-testid$="-reactions"]')).toHaveCount(0);

        // Enable the flag — the article page renders the reactions.
        await page.goto('/admin/settings');
        await page.getByTestId('settings-page-tab-flags').click();
        await setCheckbox(page, 'settings-page-flag-social_reactions-toggle', true);
        await expect(page.getByTestId('settings-page-flag-social_reactions-toggle')).toBeChecked();

        await page.goto(`/blog/${demoArticles.queues.slug}`);
        await expect(page.getByTestId('blog-article-page-content-reactions')).toBeVisible();

        // Revert.
        await page.goto('/admin/settings');
        await page.getByTestId('settings-page-tab-flags').click();
        await setCheckbox(page, 'settings-page-flag-social_reactions-toggle', false);
        await expect(page.getByTestId('settings-page-flag-social_reactions-toggle')).not.toBeChecked();

        await page.goto(`/blog/${demoArticles.queues.slug}`);
        await expect(page.locator('[data-testid$="-reactions"]')).toHaveCount(0);
    });

    test('changes the articles-per-page setting and sees it on the public blog', async ({ page }) => {
        await page.goto('/admin/settings');
        await page.getByTestId('settings-page-articles-per-page').fill('2');
        await page.getByTestId('settings-page-save').click();
        await expectToast(page, 'Settings saved successfully.');

        // The blog index now pages: the newest two articles on the first
        // page, the third behind the Load More button.
        await page.goto('/blog');
        const grid = page.getByTestId('blog-page-grid');

        await expect(grid).toContainText(demoArticles.queues.titleEn);
        await expect(grid).toContainText(demoArticles.vue.titleEn);
        await expect(grid).not.toContainText(demoArticles.postgres.titleEn);

        await page.getByTestId('blog-page-load-more-button').click();
        await expect(grid).toContainText(demoArticles.postgres.titleEn);

        // Restore the default and verify the full first page returns.
        await page.goto('/admin/settings');
        await page.getByTestId('settings-page-articles-per-page').fill('10');
        await page.getByTestId('settings-page-save').click();
        await expectToast(page, 'Settings saved successfully.');

        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-grid')).toContainText(demoArticles.postgres.titleEn);
    });
});
