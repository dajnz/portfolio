import { expect, test } from '@playwright/test';

import { expectPublicPageAxeClean } from '../fixtures/audit';
import { demoAbout, demoProjects, demoSkills } from '../fixtures/content';
import { validatePublicHtml } from '../fixtures/htmlValidate';

/**
 * The landing page (testing.md:477) — one test per block: about,
 * skills, featured projects and contacts. The block tests load the
 * page separately so each assertion group stays small and isolated;
 * the shared audits (axe + html-validate) run on the first load.
 */
test.describe('landing page', () => {
    test('is accessible and valid', async ({ page }, testInfo) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');
        await expect(page.getByTestId('landing-page')).toBeVisible();

        await expectPublicPageAxeClean(page, testInfo);
        await validatePublicHtml(page, testInfo);
    });

    test('renders the about block with identity, availability and CTAs', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');

        const about = page.getByTestId('landing-about-block');

        await expect(about).toContainText(demoAbout.nameEn);
        await expect(about).toContainText(demoAbout.titleEn);
        await expect(page.getByTestId('landing-about-block-availability')).toBeVisible();
        await expect(page.getByTestId('landing-about-block-cta-github')).toBeVisible();
        await expect(page.getByTestId('landing-about-block-cta-cv')).toBeVisible();
        await expect(page.getByTestId('landing-about-block-cta-projects')).toBeVisible();
    });

    test('renders every skill with a proficiency bar', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');

        const skillsBlock = page.getByTestId('landing-skills-block');

        for (const skill of demoSkills) {
            await expect(skillsBlock).toContainText(skill);
        }

        // The proficiency-bar testid is the only element carrying a
        // distinct suffix (the item roots share their prefix with the
        // nested level elements).
        await expect(page.locator('[data-testid$="-level"]')).toHaveCount(demoSkills.length);
        await expect(page.locator('[data-testid^="landing-skill-group-"]')).toHaveCount(3);
    });

    test('renders the featured projects', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');

        const featured = page.getByTestId('landing-featured-section');

        await expect(featured).toContainText(demoProjects.portfolio.titleEn);
        await expect(featured).toContainText(demoProjects.realtime.titleEn);
    });

    test('renders the contact links with their values', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/');

        const contacts = page.getByTestId('landing-contacts-block');

        await expect(contacts).toContainText('e2e-dev');
        await expect(contacts).toContainText('hello@e2e.dev');
        await expect(contacts).toContainText('@e2e_dev');
        await expect(page.locator('[data-testid^="landing-contacts-block-link-"]')).toHaveCount(3);
    });
});
