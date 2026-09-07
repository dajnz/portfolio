import { expect, test } from '@playwright/test';

import { expectPublicPageAxeClean } from '../fixtures/audit';
import { validatePublicHtml } from '../fixtures/htmlValidate';

/**
 * Cross-page layout behaviour (testing.md:482): language switch, theme
 * switch, the custom 404 page and the skip link. All state here is
 * per-browser-context (cookies/localStorage), so the file is safe to
 * run in parallel with the content specs.
 */
test.describe('public layout', () => {
    test('toggles the dark theme class on the document root', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');

        await page.getByTestId('theme-switch-button').click();

        await expect(page.locator('html')).toHaveClass(/dark/);

        await page.getByTestId('theme-switch-button').click();

        await expect(page.locator('html')).not.toHaveClass(/dark/);
    });

    test('switches the interface language to Russian and back', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-title')).toHaveText('Blog');

        await page.getByTestId('language-selector-button').click();
        await page.getByTestId('language-selector-option-ru').click();

        // The nav label and the page title come from the RU catalog, the
        // seeded article title is the RU translation of the demo row.
        await expect(page.getByTestId('public-header-nav-/blog')).toHaveText('Блог');
        await expect(page.getByTestId('blog-page-title')).toHaveText('Блог');
        await expect(page.getByTestId('blog-page-grid')).toContainText('Очереди Laravel: разбор');

        await page.getByTestId('language-selector-button').click();
        await page.getByTestId('language-selector-option-en').click();

        await expect(page.getByTestId('public-header-nav-/blog')).toHaveText('Blog');
    });

    test('renders the custom 404 page with a home link', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/nonexistent-route');

        await expect(page.getByTestId('error-page')).toBeVisible();
        await expect(page.getByTestId('error-page-code')).toHaveText('404');

        await expectPublicPageAxeClean(page, testInfo);

        // The home link resolves to the site root (the APP_URL origin —
        // absolute in dev/CI) and navigates back to the landing page.
        await page.getByTestId('error-page-home').click();
        await expect(page.getByTestId('landing-page')).toBeVisible();

        await validatePublicHtml(page, testInfo);
    });

    test('offers a skip link that targets the main content', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');

        const skipLink = page.getByTestId('public-layout-skip-link');

        await expect(skipLink).toHaveAttribute('href', '#main-content');
        await expect(page.getByTestId('public-layout-main')).toBeVisible();
    });
});
