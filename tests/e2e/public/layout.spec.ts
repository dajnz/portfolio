import { expect, test } from '@playwright/test';

import { expectPublicPageAxeClean } from '../fixtures/audit';
import { validatePublicHtml } from '../fixtures/htmlValidate';
import { expectStackedAbove } from '../fixtures/stacking';

/**
 * All state here is per-browser-context (cookies/localStorage), so the
 * file is safe to run in parallel with the content specs.
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

    // A first visit has no `theme` cookie yet: the server prop is "light"
    // while the blocking boot script applies the system preference — the
    // client must trust the document state, or theme-dependent UI (the
    // header logo, the toggle's label) renders the wrong variant until a
    // reload.
    test.describe('dark system preference', () => {
        test.use({ colorScheme: 'dark' });

        test('renders the dark logo on a first visit without a theme cookie', async ({ page }) => {
            await page.goto('/');

            await expect(page.locator('html')).toHaveClass(/dark/);
            await expect(page.getByTestId('public-header-logo-image')).toHaveAttribute(
                'src',
                '/images/logo-full-dark.svg',
            );
            await expect(page.getByTestId('theme-switch-button')).toHaveAttribute(
                'aria-label',
                'Switch to light mode',
            );
        });
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

        // The self-contained Blade error layout carries the same skip
        // link as the Inertia layouts.
        await expect(page.getByTestId('error-page-skip-link')).toHaveAttribute(
            'href',
            '#main-content',
        );
        await expect(page.locator('#main-content')).toBeVisible();

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

/**
 * The mobile menu panel starts at the header's bottom edge, exactly where
 * the sticky filter toolbars of the index pages pin themselves. Both used
 * to share the `sticky` z-layer, so the (later-in-DOM) toolbar painted over
 * the open menu and swallowed its clicks. The panel now sits on the header
 * layer; these tests pin that the menu stays the topmost element across
 * the whole menu/toolbar overlap.
 */
test.describe('public mobile menu layering', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    const indexPages = [
        { path: '/projects', toolbarTestId: 'projects-page-header' },
        { path: '/blog', toolbarTestId: 'blog-page-header' },
    ];

    for (const { path, toolbarTestId } of indexPages) {
        test(`keeps the mobile menu above the filter toolbar on ${path}`, async ({ page }) => {
            await page.addInitScript(() => {
                localStorage.setItem('cookies_accepted', '1');
            });
            await page.goto(path);

            await page.getByTestId('public-header-hamburger').click();

            const panel = page.getByTestId('public-header-mobile-menu');
            await expect(panel).toBeVisible();

            await expectStackedAbove(panel, page.getByTestId(toolbarTestId));
        });
    }
});
