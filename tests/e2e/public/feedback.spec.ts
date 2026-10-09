import { expect, test } from '@playwright/test';

test.describe('feedback form', () => {
    test('submits a valid message and shows the success state', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/#contacts');

        const form = page.getByTestId('landing-feedback-form');

        await form.getByTestId('landing-feedback-form-name').fill('E2E Visitor');
        await form.getByTestId('landing-feedback-form-email').fill('e2e-visitor@example.com');
        await form.getByTestId('landing-feedback-form-message').fill('Hello from the e2e suite.');

        await form.getByTestId('landing-feedback-form-submit').click();

        await expect(page.getByTestId('landing-feedback-form-success')).toBeVisible();
    });

    test('rejects an empty submission with validation errors', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/#contacts');

        const form = page.getByTestId('landing-feedback-form');

        await form.getByTestId('landing-feedback-form-submit').click();

        // The per-field errors render inside `role="alert"` paragraphs.
        const errors = form.locator('[role="alert"]');

        await expect(errors.first()).toBeVisible();
        await expect(page.getByTestId('landing-feedback-form-success')).not.toBeVisible();
    });

    test('silently rejects a honeypot-filled submission', async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('cookies_accepted', '1');
        });
        await page.goto('/#contacts');

        const form = page.getByTestId('landing-feedback-form');

        await form.getByTestId('landing-feedback-form-name').fill('E2E Bot');
        await form.getByTestId('landing-feedback-form-email').fill('e2e-bot@example.com');
        await form.getByTestId('landing-feedback-form-message').fill('I am not a robot.');
        await form.getByTestId('landing-feedback-form-honeypot').fill('spam');

        await form.getByTestId('landing-feedback-form-submit').click();

        // Neither a success message nor validation errors appear.
        await expect(page.getByTestId('landing-feedback-form-success')).not.toBeVisible();
        await expect(form.locator('[role="alert"]')).toHaveCount(0);
    });
});
