import { expect, test } from '@playwright/test';

import { expectPublicPageAxeClean } from '../fixtures/audit';
import { demoArticles } from '../fixtures/content';
import { validatePublicHtml } from '../fixtures/htmlValidate';
import { pickFirstSuggestion } from '../fixtures/locators';

test.describe('blog index', () => {
    test('loads the article cards and opens an article detail page', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-title')).toHaveText('Blog');

        const grid = page.getByTestId('blog-page-grid');

        await expect(grid).toContainText(demoArticles.queues.titleEn);
        await expect(grid).toContainText(demoArticles.vue.titleEn);
        await expect(grid).toContainText(demoArticles.postgres.titleEn);

        // The draft article must never surface on the public index.
        await expect(grid).not.toContainText('Draft article never published');

        await expectPublicPageAxeClean(page, testInfo);
        await validatePublicHtml(page, testInfo);
    });

    test('navigates to an article detail with TOC, enhanced content and related posts', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');

        // The card testid now sits on the container — the whole-card
        // click surface is the stretched overlay link inside it.
        const card = page.locator(`[data-testid^="blog-page-card-"]`).filter({
            has: page.locator(`a[href="/blog/${demoArticles.queues.slug}"]`),
        });

        await expect(card).toBeVisible();
        await card.locator(`a[href="/blog/${demoArticles.queues.slug}"]`).click();

        await expect(page).toHaveURL(new RegExp(`/blog/${demoArticles.queues.slug}$`));

        const article = page.getByTestId('blog-article-page-content');

        await expect(article.getByTestId('blog-article-page-content-title')).toHaveText(
            demoArticles.queues.titleEn,
        );
        await expect(article.getByTestId('blog-article-page-content-body')).toContainText(
            demoArticles.queues.keyword,
        );

        await expect(page.getByTestId('blog-article-page-toc')).toContainText('Why queues matter');

        // The code block and the callout mount only after the hydration
        // enhancement pass (the code block root and its copy button both
        // carry the code testid prefix — assert the copy control instead).
        await expect(article.locator('[data-testid$="-copy"]')).toHaveCount(1);
        await expect(page.locator('[data-testid^="blog-article-page-content-callout-"]')).toHaveCount(1);

        // The two seeded related cards render under the article (their
        // root anchors carry the related-card testid prefix — count the
        // section's article links instead of the nested card nodes).
        const related = page.getByTestId('blog-article-page-related');

        await expect(related.locator('a[href^="/blog/"]')).toHaveCount(2);
        await expect(related).toContainText(demoArticles.vue.titleEn);

        await expectPublicPageAxeClean(page, testInfo);
        await validatePublicHtml(page, testInfo);
    });

    test('finds an article by keyword through the search autocomplete', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');

        const searchInput = page.getByTestId('blog-page-header-search-input');

        await searchInput.fill(demoArticles.queues.keyword);

        const results = page.locator('[data-testid^="blog-page-header-search-result-"]');

        await expect(results.first()).toContainText(demoArticles.queues.titleEn);

        await searchInput.press('Enter');

        await expect(page).toHaveURL(new RegExp(`/blog/${demoArticles.queues.slug}$`));
        await expect(page.getByTestId('blog-article-page-content-title')).toHaveText(
            demoArticles.queues.titleEn,
        );
    });

    test('filters articles by tags and skills (ALL intersection) and shows the year filter', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');

        const grid = page.getByTestId('blog-page-grid');
        const tagsInput = page.getByTestId('blog-page-header-tags-input');

        // Select the article TAG `php` (the typed prefix also matches the
        // equally-named PHP skill — the second suggestion is the tag).
        await tagsInput.click();
        await tagsInput.fill('php');
        await page.locator('[data-testid^="blog-page-header-tags-suggestion-"]').nth(1).click();

        await expect(grid).toContainText(demoArticles.queues.titleEn);
        await expect(grid).toContainText(demoArticles.postgres.titleEn);
        await expect(grid).not.toContainText(demoArticles.vue.titleEn);

        // Narrow with the Redis skill — only the queues article carries it.
        await pickFirstSuggestion(page, 'blog-page-header-tags-input', 'redis');

        await expect(grid).toContainText(demoArticles.queues.titleEn);
        await expect(grid).not.toContainText(demoArticles.postgres.titleEn);

        // Adding the Vue.js skill leaves no article with ALL three — the
        // empty state takes over.
        await pickFirstSuggestion(page, 'blog-page-header-tags-input', 'vue');

        await expect(page.getByTestId('blog-page-no-results')).toBeVisible();
    });

    test('filters articles by year', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');

        const grid = page.getByTestId('blog-page-grid');
        const yearTrigger = page.getByTestId('blog-page-header-year');

        await yearTrigger.click();
        await page.getByTestId('blog-page-header-year-option-2025').click();

        await expect(grid).toContainText(demoArticles.postgres.titleEn);
        await expect(grid).not.toContainText(demoArticles.queues.titleEn);

        // Back to all years via the "All" option (its testid carries the
        // empty-string suffix — the '' sentinel value).
        await yearTrigger.click();
        await page.locator('[data-testid="blog-page-header-year-option-"]').click();

        await expect(grid).toContainText(demoArticles.queues.titleEn);
    });

    test('toggles the grid/list view and persists it', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/blog');

        const gridButton = page.getByTestId('blog-page-header-view-grid');
        const listButton = page.getByTestId('blog-page-header-view-list');

        await expect(gridButton).toHaveClass(/is-active/);

        await listButton.click();
        await expect(listButton).toHaveClass(/is-active/);
        await expect(gridButton).not.toHaveClass(/is-active/);

        // The view-mode cookie persists across reloads.
        await page.reload();
        await expect(page.getByTestId('blog-page-header-view-list')).toHaveClass(/is-active/);

        await gridButton.click();
        await expect(gridButton).toHaveClass(/is-active/);
    });
});
