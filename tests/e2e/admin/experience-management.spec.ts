import { execSync } from 'node:child_process';

import { expect, test } from '@playwright/test';

import { rowIdByText, setCheckbox, unique } from '../fixtures/admin';

/**
 * The experience admin CRUD (testing.md:493). The tests are serial and
 * share the created item: the first creates the timeline entry, the
 * second verifies the public timeline and cleans up (the item is
 * deleted and the seeded current role's `ended_at` — auto-closed by the
 * timeline-continuity rules when a newer item starts — is restored via
 * tinker, so the public project that follows sees the baseline).
 */
test.describe.configure({ mode: 'serial' });

let createdPosition = '';
let createdItemId: number | null = null;
let seededCurrentId: number | null = null;

test.describe('experience management', () => {
    test('creates a timeline item with achievements and tags', async ({ page }) => {
        const token = unique('e2e-exp');
        const positionEn = `Platform Engineer ${token}`;
        const positionRu = `Инженер платформы ${token}`;

        createdPosition = positionEn;

        await page.goto('/admin/experience/create');

        await page.getByTestId('experience-form-page-position-en').fill(positionEn);
        await page.getByTestId('experience-form-page-company-name-en').fill('E2E Labs');
        await page.getByTestId('experience-form-page-country-en').fill('Germany');
        await page.getByTestId('experience-form-page-short-description-en').fill(
            `Building the internal platform for ${token} with reliable delivery.`,
        );

        // The ongoing item starts today (the continuity rules close the
        // seeded current role when a newer ongoing item appears).
        await pickDay(page, 'experience-form-page-started-at', 1);

        // One achievement per locale.
        await page.getByTestId('experience-form-page-achievements-add').click();
        await page.getByTestId('experience-form-page-achievements-item-0-description-en').fill(
            `Shipped the delivery pipeline for ${token}.`,
        );

        await page.getByTestId('experience-form-page-locale-tabs-tab-ru').click();
        await page.getByTestId('experience-form-page-position-ru').fill(positionRu);
        await page.getByTestId('experience-form-page-company-name-ru').fill('E2E Labs');
        await page.getByTestId('experience-form-page-country-ru').fill('Германия');
        await page.getByTestId('experience-form-page-short-description-ru').fill(
            `Разработка внутренней платформы для ${token} с надёжной доставкой.`,
        );
        await page.getByTestId('experience-form-page-achievements-item-0-description-ru').fill(
            `Запустил конвейер доставки для ${token}.`,
        );

        // A tech tag and the publish switch (the sr-only switch input).
        await page.getByTestId('experience-form-page-tech-tags-input').fill('laravel');
        await page
            .locator('[data-testid^="experience-form-page-tech-tags-suggestion-"]')
            .first()
            .click();

        await setCheckbox(page, 'experience-form-page-published', true);

        await page.getByTestId('experience-form-page-save-btn').click();
        await expect(page.locator('[data-testid^="toast-"]').first()).toContainText(
            'Saved successfully.',
        );
    });

    test('shows the item on the public timeline and cleans it up', async ({ page }) => {
        expect(createdPosition).not.toBe('');

        // The new ongoing entry tops the public timeline.
        await page.goto('/experience');
        const timeline = page.getByTestId('experience-page-timeline');

        await expect(timeline).toContainText(createdPosition);
        await expect(timeline.locator(':scope > li').first()).toContainText(createdPosition);

        // Cleanup: delete the item and restore the seeded role's end.
        await page.goto('/admin/experience');
        createdItemId = await rowIdByText(page, 'experience-list-page', createdPosition, 'delete');

        expect(createdItemId).not.toBeNull();

        await page.getByTestId(`experience-list-page-delete-${createdItemId}`).click();
        await page.getByTestId('experience-list-page-delete-dialog-confirm').click();
        await expect(
            page.locator(`[data-testid="experience-list-page-delete-${createdItemId}"]`),
        ).toHaveCount(0);

        seededCurrentId = await rowIdByText(
            page,
            'experience-list-page',
            'Senior Backend Engineer',
            'delete',
        );

        expect(seededCurrentId).not.toBeNull();

        execSync(
            `php artisan tinker --execute="\\App\\Features\\Experience\\Infra\\Models\\TimelineItemModel::query()->where('id', ${seededCurrentId})->update(['ended_at' => null])"`,
        );
    });
});

/** Picks the given day of the currently displayed month in the picker. */
async function pickDay(
    page: import('@playwright/test').Page,
    fieldTestId: string,
    day: number,
): Promise<void> {
    await page.getByTestId(`${fieldTestId}-field`).click();

    const now = new Date();
    const dd = String(day).padStart(2, '0');
    const mm = String(now.getMonth() + 1).padStart(2, '0');

    await page.getByTestId(`${fieldTestId}-day-${dd}-${mm}-${now.getFullYear()}`).click();
}
