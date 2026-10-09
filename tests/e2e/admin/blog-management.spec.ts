import { expect, test } from '@playwright/test';

import { expectToast, rowIdByText, rowLocator, setCheckbox, typeMarkdown, unique } from '../fixtures/admin';
import { demoArticles } from '../fixtures/content';

/**
 * Every created row is soft-deleted at the end, so the public project
 * (which runs after this file's project) still sees the seeded baseline.
 *
 * The file is serial: the tests share the blog table and assert row
 * counts of the seeded baseline.
 */
test.describe.configure({ mode: 'serial' });

test.describe('blog management', () => {
    test('lists the seeded articles and filters them', async ({ page }) => {
        await page.goto('/admin/blog');

        // All live rows: three published + the draft.
        await expect(page.getByTestId('blog-list-page-table-row-1')).toBeVisible();
        await expect(page.getByTestId('blog-list-page-table-row-4')).toBeVisible();

        await page.getByTestId('blog-list-page-published-filter').click();
        await page.getByTestId('blog-list-page-published-filter-option-published').click();

        await expect(page.locator('[data-testid^="blog-list-page-table-row-"]')).toHaveCount(3);
        await expect(rowLocator(page, 'blog-list-page', 1)).toContainText(demoArticles.queues.titleEn);
        await expect(rowLocator(page, 'blog-list-page', 4)).not.toBeVisible();

        await page.getByTestId('blog-list-page-published-filter').click();
        await page.getByTestId('blog-list-page-published-filter-option-unpublished').click();

        await expect(page.locator('[data-testid^="blog-list-page-table-row-"]')).toHaveCount(1);
        await expect(rowLocator(page, 'blog-list-page', 4)).toContainText('Draft article never published');

        await page.getByTestId('blog-list-page-published-filter').click();
        await page.getByTestId('blog-list-page-published-filter-option-all').click();

        // Year filter: 2025 keeps only the Postgres article.
        await page.getByTestId('blog-list-page-year-filter').click();
        await page.locator('[data-testid="blog-list-page-year-filter-option-2025"]').click();

        await expect(page.locator('[data-testid^="blog-list-page-table-row-"]')).toHaveCount(1);
        await expect(rowLocator(page, 'blog-list-page', 3)).toContainText(demoArticles.postgres.titleEn);

        // Reset the year filter before switching to the trashed view.
        await page.getByTestId('blog-list-page-year-filter').click();
        await page.locator('[data-testid="blog-list-page-year-filter-option-"]').click();

        // The trashed view shows the seeded trashed row with the
        // restore/force-delete actions.
        await page.getByTestId('blog-list-page-trashed').click();

        await expect(page.locator('[data-testid^="blog-list-page-table-row-"]')).toHaveCount(1);
        await expect(page.getByTestId('blog-list-page-restore-5')).toBeVisible();
        await expect(page.getByTestId('blog-list-page-force-delete-5')).toBeVisible();

        await page.getByTestId('blog-list-page-restore-5').click();
        await expect(rowLocator(page, 'blog-list-page', 5)).toBeVisible();
    });

    test('creates an article, publishes it and sees it on the public blog', async ({ page }) => {
        const token = unique('e2e-blog');
        const titleEn = `The ${token} Guide`;
        const titleRu = `Гайд ${token}`;

        await createArticle(page, token, titleEn, titleRu);

        const id = await openCreatedArticle(page, token);
        expect(id).not.toBeNull();

        // Publish from the edit form and verify the public page shows the
        // EN (and, with the locale cookie, the RU) title.
        await setCheckbox(page, 'blog-form-page-published', true);
        await page.getByTestId('blog-form-page-save-btn').click();
        await expectToast(page, 'Saved successfully.');

        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-grid')).toContainText(titleEn);

        const origin = new URL(page.url()).origin;

        await page.context().addCookies([{ name: 'locale', value: 'ru', url: origin }]);
        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-grid')).toContainText(titleRu);
        await page.context().clearCookies({ name: 'locale' });

        await softDeleteArticle(page, id as number, titleEn);
    });

    test('edits both locales of an article and updates the public page', async ({ page }) => {
        const token = unique('e2e-blog-edit');
        const titleEn = `The ${token} Guide`;
        const titleRu = `Гайд ${token}`;

        await createArticle(page, token, titleEn, titleRu);
        const id = await openCreatedArticle(page, token);

        await page.goto(`/admin/blog/${id}/edit`);

        const editedEn = `${titleEn} Revised`;
        const editedRu = `${titleRu} правки`;

        await page.getByTestId('blog-form-page-title-en').fill(editedEn);
        await page.getByTestId('blog-form-page-locale-tabs-tab-ru').click();
        await page.getByTestId('blog-form-page-title-ru').fill(editedRu);
        // Publish alongside the title edits so the public page reflects them.
        await setCheckbox(page, 'blog-form-page-published', true);
        await page.getByTestId('blog-form-page-save-btn').click();

        await expectToast(page, 'Saved successfully.');

        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-grid')).toContainText(editedEn);

        await softDeleteArticle(page, id as number, editedEn);
    });

    test('soft-deletes an article so it disappears from the public blog', async ({ page }) => {
        const token = unique('e2e-blog-trash');
        const titleEn = `The ${token} Guide`;

        await createArticle(page, token, titleEn, `Гайд ${token}`);
        const id = await openCreatedArticle(page, token);

        await setCheckbox(page, 'blog-form-page-published', true);
        await page.getByTestId('blog-form-page-save-btn').click();
        await expectToast(page, 'Saved successfully.');

        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-grid')).toContainText(titleEn);

        await softDeleteArticle(page, id as number, titleEn);

        await page.goto('/blog');
        await expect(page.getByTestId('blog-page-grid')).not.toContainText(titleEn);
    });

    test('bulk publishes and unpublishes the selected articles', async ({ page }) => {
        const token = unique('e2e-bulk');
        const titles: string[] = [];

        for (const suffix of ['One', 'Two']) {
            const titleEn = `Bulk ${token} ${suffix}`;

            titles.push(titleEn);
            await createArticle(page, token, titleEn, `Пакет ${token} ${suffix}`);
        }

        await page.goto('/admin/blog');

        const rowIds: number[] = [];

        for (const title of titles) {
            const id = await rowIdByText(page, 'blog-list-page', title);

            expect(id).not.toBeNull();
            rowIds.push(id as number);
            await page.getByTestId(`blog-list-page-table-row-${id}`).click();
        }

        const bulkBar = page.getByTestId('blog-list-page-bulk-bar');

        await expect(bulkBar).toBeVisible();
        await bulkBar.getByTestId('blog-list-page-bulk-bar-action-publish').click();

        for (const id of rowIds) {
            await expect(page.getByTestId(`blog-list-page-publish-toggle-${id}`)).toContainText('Unpublish');
        }

        // Unpublish the SAME two rows — the bulk actions apply to the
        // whole current selection, so the rows are re-picked by id after
        // the refetch (never the select-all checkbox, which would also
        // grab the seeded rows).
        await reselectRows(page, rowIds);
        await page.getByTestId('blog-list-page-bulk-bar-action-unpublish').click();

        for (const id of rowIds) {
            await expect(page.getByTestId(`blog-list-page-publish-toggle-${id}`)).toContainText('Publish');
        }

        bulkRowIds.splice(0, bulkRowIds.length, ...rowIds);
    });

    test('bulk deletes the selected articles', async ({ page }) => {
        expect(bulkRowIds.length).toBe(2);

        await page.goto('/admin/blog');

        await reselectRows(page, bulkRowIds);
        await page.getByTestId('blog-list-page-bulk-bar-action-delete').click();
        await page.getByTestId('blog-list-page-delete-dialog-confirm').click();

        for (const id of bulkRowIds) {
            await expect(page.locator(`[data-testid="blog-list-page-table-row-${id}"]`)).toHaveCount(0);
        }
    });
});

// The rows created by the bulk-publish test — consumed by the serial
// bulk-delete test that follows (the article rows survive soft deletes
// only within this file's scope).
const bulkRowIds: number[] = [];

async function reselectRows(
    page: import('@playwright/test').Page,
    rowIds: number[],
): Promise<void> {
    // Selection is cleared by the refetch after each bulk action — pick
    // the rows again (the select-all checkbox would include the seeded
    // rows, which the bulk delete would trash).
    for (const id of rowIds) {
        const checkbox = page.getByTestId(`blog-list-page-table-row-${id}`);

        await expect(checkbox).toBeVisible();
        await checkbox.click();
    }

    await expect(page.getByTestId('blog-list-page-bulk-bar')).toBeVisible();
}

async function createArticle(
    page: import('@playwright/test').Page,
    token: string,
    titleEn: string,
    titleRu: string,
): Promise<void> {
    await page.goto('/admin/blog/create');

    const editorEn = page.getByTestId('blog-form-page-editor-en-content');

    await expect(editorEn).toBeVisible();
    await page.getByTestId('blog-form-page-title-en').fill(titleEn);
    await page.getByTestId('blog-form-page-short-description-en')
        .fill(`Short description of ${token} with enough words.`);
    await typeMarkdown(
        page,
        'blog-form-page-editor-en',
        `## Intro\nThe body of ${token} with more than fifty characters of markdown content.`
    );

    await page.getByTestId('blog-form-page-locale-tabs-tab-ru').click();
    await page.getByTestId('blog-form-page-title-ru').fill(titleRu);
    await page.getByTestId('blog-form-page-short-description-ru')
        .fill(`Краткое описание ${token} с достаточной длиной текста.`);
    await typeMarkdown(
        page,
        'blog-form-page-editor-ru',
        `## Введение\nТело статьи ${token} длиной более пятидесяти знаков markdown.`
    );

    await page.getByTestId('blog-form-page-save-btn').click();
    await expectToast(page, 'Saved successfully.');
}

/** Opens the article via the q-filtered list and returns its DB id. */
async function openCreatedArticle(
    page: import('@playwright/test').Page,
    token: string,
): Promise<number | null> {
    await page.goto('/admin/blog');

    const id = await rowIdByText(page, 'blog-list-page', token);

    expect(id).not.toBeNull();

    await rowLocator(page, 'blog-list-page', id as number).click();
    await page.waitForURL(new RegExp(`/admin/blog/${id}/edit$`));

    return id;
}

async function softDeleteArticle(
    page: import('@playwright/test').Page,
    id: number,
    expectedTitle: string,
): Promise<void> {
    await page.goto('/admin/blog');

    const foundId = await rowIdByText(page, 'blog-list-page', expectedTitle);

    expect(foundId).not.toBeNull();

    const row = rowLocator(page, 'blog-list-page', id);

    await expect(row).toBeVisible();
    await page.getByTestId(`blog-list-page-delete-${id}`).click();
    await page.getByTestId('blog-list-page-delete-dialog-confirm').click();

    await expect(page.locator(`[data-testid="blog-list-page-table-row-${id}"]`)).toHaveCount(0);
}
