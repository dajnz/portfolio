import { expect, test } from '@playwright/test';

import { expectPublicPageAxeClean } from '../fixtures/audit';
import { demoProjects } from '../fixtures/content';
import { validatePublicHtml } from '../fixtures/htmlValidate';
import { pickFirstSuggestion } from '../fixtures/locators';

test.describe('projects index', () => {
    test('loads the project cards', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/projects');
        await expect(page.getByTestId('projects-page-title')).toHaveText('Projects');

        const grid = page.getByTestId('projects-page-grid');

        await expect(grid).toContainText(demoProjects.portfolio.titleEn);
        await expect(grid).toContainText(demoProjects.realtime.titleEn);
        await expect(grid).toContainText(demoProjects.inventory.titleEn);

        // The draft project must never surface on the public index.
        await expect(grid).not.toContainText('Draft Project Never Published');

        await expectPublicPageAxeClean(page, testInfo);
        await validatePublicHtml(page, testInfo);
    });

    test('filters projects by tech skills (ALL intersection)', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/projects');

        const grid = page.getByTestId('projects-page-grid');

        // Laravel: the portfolio platform and the inventory API.
        await pickFirstSuggestion(page, 'projects-page-header-tags-input', 'laravel');

        await expect(grid).toContainText(demoProjects.portfolio.titleEn);
        await expect(grid).toContainText(demoProjects.inventory.titleEn);
        await expect(grid).not.toContainText(demoProjects.realtime.titleEn);

        // Adding Redis leaves nothing carrying ALL selected skills.
        await pickFirstSuggestion(page, 'projects-page-header-tags-input', 'redis');

        await expect(page.getByTestId('projects-page-no-results')).toBeVisible();
    });

    test('sorts by date ascending and descending', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/projects');

        const grid = page.getByTestId('projects-page-grid');

        const cardLinks = () =>
            grid
                .locator('a[href^="/projects/"]')
                .evaluateAll((links) => links.map((link) => link.getAttribute('href')));

        const firstCardSlug = () => cardLinks().then((links) => links[0] ?? '');

        // Default: newest first (published_at DESC).
        await expect.poll(firstCardSlug).toContain(demoProjects.portfolio.slug);

        await page.getByTestId('projects-page-header-sort').click();
        await page.getByTestId('projects-page-header-sort-option-date:asc').click();

        await expect.poll(firstCardSlug).toContain(demoProjects.inventory.slug);

        await page.getByTestId('projects-page-header-sort').click();
        await page.getByTestId('projects-page-header-sort-option-date:desc').click();

        await expect.poll(firstCardSlug).toContain(demoProjects.portfolio.slug);
    });

    test('toggles the grid/list view', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/projects');

        const gridButton = page.getByTestId('projects-page-header-view-grid');
        const listButton = page.getByTestId('projects-page-header-view-list');

        await expect(gridButton).toHaveClass(/is-active/);

        await listButton.click();
        await expect(listButton).toHaveClass(/is-active/);

        await gridButton.click();
        await expect(gridButton).toHaveClass(/is-active/);
    });
});

test.describe('project detail', () => {
    test('renders the write-up sections, engineering details and source link', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto(`/projects/${demoProjects.portfolio.slug}`);

        await expect(page.getByTestId('project-detail-page-title')).toHaveText(
            demoProjects.portfolio.titleEn,
        );

        await expect(page.getByTestId('project-detail-page-overview')).toContainText('Architecture');
        await expect(page.getByTestId('project-detail-page-problem')).not.toBeEmpty();
        await expect(page.getByTestId('project-detail-page-solution')).not.toBeEmpty();

        await expect(page.getByTestId('project-detail-page-tags')).toContainText('Laravel');

        await expect(page.locator('[data-testid^="project-detail-page-detail-"]')).toHaveCount(2);

        await expect(page.getByTestId('project-detail-page-github')).toHaveAttribute(
            'href',
            demoProjects.portfolio.github,
        );
        await expect(page.getByTestId('project-detail-page-demo')).toHaveCount(0);

        await expectPublicPageAxeClean(page, testInfo);
        await validatePublicHtml(page, testInfo);
    });

    test('hides the source links for a project without any', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto(`/projects/${demoProjects.inventory.slug}`);

        await expect(page.getByTestId('project-detail-page-title')).toHaveText(
            demoProjects.inventory.titleEn,
        );
        await expect(page.getByTestId('project-detail-page-github')).toHaveCount(0);
        await expect(page.getByTestId('project-detail-page-demo')).toHaveCount(0);
    });
});
