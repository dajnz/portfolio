import { expect, test } from '@playwright/test';

import { expectPublicPageAxeClean } from '../fixtures/audit';
import { demoExperience } from '../fixtures/content';
import { validatePublicHtml } from '../fixtures/htmlValidate';

/**
 * Modal visibility is asserted on the backdrop (base-modal-scrim): the
 * modal root element is an empty positioning wrapper with a zero-sized
 * box — its fixed-position children carry the visual surface.
 */
test.describe('experience page', () => {
    test('timeline order, current marker and the detail modal', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/experience');
        await expect(page.getByTestId('experience-page-title')).toHaveText('Work Experience');

        const timeline = page.getByTestId('experience-page-timeline');
        const items = timeline.locator(':scope > li');

        await expect(items).toHaveCount(3);

        // Newest first: the current role tops the timeline and its period
        // carries the "Present" marker; the oldest entry comes last.
        await expect(items.first()).toContainText(demoExperience.current.companyEn);
        await expect(items.first()).toContainText('Present');
        await expect(items.last()).toContainText(demoExperience.oldest.companyEn);

        await items.first().locator('[data-testid$="-details"]').click();

        const scrim = page.getByTestId('base-modal-scrim');
        const modal = page.getByTestId('experience-detail-modal');

        await expect(scrim).toBeVisible();
        await expect(modal.getByTestId('experience-detail-modal-company')).toContainText(
            demoExperience.current.companyEn,
        );
        await expect(modal).toContainText(demoExperience.current.achievementEn);

        await modal.getByTestId('base-modal-close').click();
        await expect(scrim).not.toBeVisible();

        await expectPublicPageAxeClean(page, testInfo);
        await validatePublicHtml(page, testInfo);
    });

    test('the +N chip opens the popover with the remaining tech tags', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/experience');

        // Only the current role carries more tags than the card shows.
        const overflow = page.locator('[data-testid$="-tags-overflow"]');

        await expect(overflow).toHaveCount(1);
        await expect(overflow).toContainText('+3');
        await overflow.click();

        const scrim = page.getByTestId('base-modal-scrim');
        const popover = page.getByTestId('tag-popup-modal');

        await expect(scrim).toBeVisible();
        await expect(popover).toContainText('PostgreSQL');
        await expect(popover).toContainText('Redis');

        await popover.getByTestId('base-modal-close').click();
        await expect(scrim).not.toBeVisible();
    });
});
