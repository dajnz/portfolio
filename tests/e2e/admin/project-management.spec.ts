import { expect, test } from '@playwright/test';

import { expectToast, rowIdByText, setCheckbox, typeMarkdown, unique } from '../fixtures/admin';

/**
 * The project admin CRUD (testing.md:492). The tests are serial and
 * share the created project: the first creates a published, featured
 * project with a screenshot and tech tags, the second pulls its
 * featured order to the front and verifies the landing page, the last
 * soft-deletes it so the public project sees the seeded baseline.
 */
test.describe.configure({ mode: 'serial' });

const token = unique('e2e-proj');
const titleEn = `${token} Platform`;
const titleRu = `Платформа ${token}`;
const slug = `${token}-platform`;

let projectId: number | null = null;

test.describe('project management', () => {
    test('creates a featured project with a screenshot and shows it publicly', async ({ page }) => {
        await page.goto('/admin/projects/create');

        await fillBilingualContent(page);
        await uploadScreenshot(page);
        await attachTechTags(page);
        await setFeaturedWithOrder(page, '3');
        await saveProjectForm(page);

        // The public projects index shows the new card.
        await page.goto('/projects');
        await expect(page.getByTestId('projects-page-grid')).toContainText(titleEn);

        // The landing featured section lists the seeds first, the new
        // project last (featured_sort_order 3).
        await page.goto('/');
        const featuredSlugs = await landingFeaturedSlugs(page);

        expect(featuredSlugs).toEqual(['portfolio-platform', 'realtime-analytics-dashboard', slug]);
    });

    test('moves the featured order to the front of the landing list', async ({ page }) => {
        await page.goto('/admin/projects');
        projectId = await rowIdByText(page, 'projects-list-page', token);

        expect(projectId).not.toBeNull();

        await page.goto(`/admin/projects/${projectId}/edit`);
        await page.getByTestId('project-form-page-featured-order').fill('0');
        await page.getByTestId('project-form-page-save-btn').click();
        await expectToast(page, 'Saved successfully.');

        await page.goto('/');
        await expect
            .poll(async () => landingFeaturedSlugs(page))
            .toEqual([slug, 'portfolio-platform', 'realtime-analytics-dashboard']);
    });

    test('soft-deletes the created project', async ({ page }) => {
        expect(projectId).not.toBeNull();

        await page.goto('/admin/projects');
        await page.getByTestId(`projects-list-page-delete-${projectId}`).click();
        await page.getByTestId('projects-list-page-delete-dialog-confirm').click();
        await expect(page.locator(`[data-testid="projects-list-page-table-row-${projectId}"]`)).toHaveCount(0);
    });
});

/** The featured card slugs in their landing-page order. */
async function landingFeaturedSlugs(page: import('@playwright/test').Page): Promise<string[]> {
    const featured = page.getByTestId('landing-featured-section');

    await expect(featured).toBeVisible();

    return featured
        .locator('a[href^="/projects/"]')
        .evaluateAll((links) => links.map((link) => link.getAttribute('href') ?? '')
            .map((href) => href.replace('/projects/', ''))
        );
}

/** Fills the bilingual title, brief and the architecture write-up. */
async function fillBilingualContent(page: import('@playwright/test').Page): Promise<void> {
    await page.getByTestId('project-form-page-title-en').fill(titleEn);
    await page.getByTestId('project-form-page-brief-en').fill(
        `The ${token} platform with a brief description of moderate length.`,
    );
    await typeMarkdown(
        page,
        'project-form-page-full-description-en',
        `## Architecture\n\nThe ${token} platform architecture section with more than fifty characters of text.`,
    );

    await page.getByTestId('project-form-page-locale-tabs-tab-ru').click();
    await page.getByTestId('project-form-page-title-ru').fill(titleRu);
    await page.getByTestId('project-form-page-brief-ru').fill(
        `Платформа ${token} с кратким описанием достаточной длины.`,
    );
    await typeMarkdown(
        page,
        'project-form-page-full-description-ru',
        `## Архитектура\n\nРаздел архитектуры платформы ${token} с текстом достаточной длины для сохранения.`,
    );
}

/** Uploads one screenshot through the media section's file input. */
async function uploadScreenshot(page: import('@playwright/test').Page): Promise<void> {
    await page.getByTestId('project-form-page-add-screenshot').click();
    await page.getByTestId('project-form-page-screenshot-0-dropzone-input').setInputFiles({
        name: 'screenshot.png',
        mimeType: 'image/png',
        buffer: PNG_1X1,
    });

    // The upload pipeline replaces the file with a processed .webp.
    await expect(page.getByTestId('project-form-page-screenshot-0-dropzone')).toContainText('.webp');
}

/** Picks the first two tech-tag suggestions (Laravel + Vue.js). */
async function attachTechTags(page: import('@playwright/test').Page): Promise<void> {
    for (const tag of ['laravel', 'vue']) {
        await page.getByTestId('project-form-page-tech-tags-input').click();
        await page.getByTestId('project-form-page-tech-tags-input').fill(tag);
        await page.locator('[data-testid^="project-form-page-tech-tags-suggestion-"]').first().click();
    }
}

/** Marks the project as featured with the given order and publishes it. */
async function setFeaturedWithOrder(page: import('@playwright/test').Page, order: string): Promise<void> {
    await setCheckbox(page, 'project-form-page-featured', true);
    await page.getByTestId('project-form-page-featured-order').fill(order);
    await setCheckbox(page, 'project-form-page-published', true);
}

/** Saves the form and waits for the success toast. */
async function saveProjectForm(page: import('@playwright/test').Page): Promise<void> {
    await page.getByTestId('project-form-page-save-btn').click();
    await expectToast(page, 'Saved successfully.');
}

/** A 1×1 transparent PNG. */
const PNG_1X1 = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
);
