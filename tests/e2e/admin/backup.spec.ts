import { execSync } from 'node:child_process';
import { copyFileSync } from 'node:fs';

import { expect, test } from '@playwright/test';

import { expectToast } from '../fixtures/admin';
import { ADMIN_PASSWORD, loginAsAdmin } from '../fixtures/seed';

/**
 * The backup admin flows (testing.md:496, backup.md): create a backup
 * (after generating the first encryption key) and watch it complete,
 * download the archive, delete it, and finally run the disaster-
 * recovery restore from the downloaded archive — the restore truncates
 * and reseeds the whole e2e database, so this spec runs last (its own
 * project after everything else).
 */
test.describe.configure({ mode: 'serial' });

let downloadedArchivePath: string | null = null;
let encryptionKeyValue: string | null = null;

/** Opens the generate-key dialog whether or not the auto-prompt fired. */
async function openGenerateDialog(page: import('@playwright/test').Page): Promise<void> {
    // The modal root is a zero-box positioning wrapper — the scrim is
    // the visible surface (the auto-prompt can lag under load).
    const scrim = page.getByTestId('backup-list-page-generate-dialog-scrim');

    try {
        await scrim.waitFor({ state: 'visible', timeout: 15_000 });
    } catch {
        await page.getByTestId('backup-list-page-keys-generate').click();
        await expect(scrim).toBeVisible({ timeout: 10_000 });
    }
}

/** Drains the pending jobs of the e2e Redis-DB-1 queue — the compose
 *  queue worker watches DB 0, so the backup/restore jobs (plus the
 *  article-view increments queued by the earlier suites) are processed
 *  here. A no-op in CI, where QUEUE_CONNECTION=sync runs every job
 *  during the triggering request (and queue:work is unsupported). */
function drainQueue(): void {
    if (process.env.QUEUE_CONNECTION === 'sync') {
        return;
    }

    try {
        execSync('php artisan queue:work --stop-when-empty --max-time=120 --timeout=600', {
            stdio: 'pipe',
            timeout: 300_000,
        });
    } catch {
        // The job may have already been processed — the assertions below
        // poll for the completion state either way.
    }
}

test.describe('backup management', () => {
    test('creates the first encryption key and a completed backup', async ({ page }) => {
        await page.goto('/admin/backups');

        // No encryption key exists yet — the page may auto-prompt for the
        // first key, otherwise the key-list button opens the dialog.
        await openGenerateDialog(page);

        const generateDialog = page.getByTestId('backup-list-page-generate-dialog');

        await generateDialog.getByTestId('backup-list-page-generate-dialog-confirm').click();

        // The generated key is shown once for copying (the modal root is
        // a zero-box wrapper — the content children carry the surface).
        const acknowledgeButton = page.getByTestId('backup-list-page-key-modal-acknowledged');

        await expect(acknowledgeButton).toBeVisible();
        encryptionKeyValue =
            (await page.getByTestId('backup-list-page-key-modal-key-value').textContent())?.trim() ?? null;

        expect(encryptionKeyValue).not.toBeNull();
        await acknowledgeButton.click();

        // The key row is active.
        await expect(page.getByTestId('backup-list-page-keys-key-1-active')).toBeVisible();

        // Create the backup and wait for the completed status.
        await page.getByTestId('backup-list-page-stats-create').click();
        await expectToast(page, 'Backup creation started.');

        // The local e2e server pushes to Redis DB 1, but the compose
        // queue worker drains DB 0 — process the job inline (a no-op in
        // CI, where QUEUE_CONNECTION=sync already ran it during the
        // request).
        drainQueue();

        const completed = page.locator('[data-testid^="backup-list-page-table-status-"]', {
            hasText: 'Completed',
        });

        await expect(completed).toBeVisible({ timeout: 120_000 });
    });

    test('downloads the completed backup archive', async ({ page }) => {
        await page.goto('/admin/backups');

        await expect(page.getByTestId('backup-list-page-table-download-1')).toBeEnabled();

        const downloadPromise = page.waitForEvent('download');

        await page.getByTestId('backup-list-page-table-download-1').click();
        const download = await downloadPromise;

        expect(download.suggestedFilename()).toMatch(/^backup-.*\.zip$/);

        const archivePath = await download.path();

        expect(archivePath).not.toBeNull();

        // The download artifact lives in the per-test temp dir — copy it
        // somewhere stable for the restore test that follows.
        downloadedArchivePath = '/tmp/e2e-backup-archive.zip';
        copyFileSync(archivePath as string, downloadedArchivePath);
    });

    test('deletes the backup', async ({ page }) => {
        await page.goto('/admin/backups');

        await page.getByTestId('backup-list-page-table-delete-1').click();
        await page.getByTestId('backup-list-page-delete-backup-dialog-confirm').click();

        await expect(page.locator('[data-testid^="backup-list-page-table-status-"]')).toHaveCount(0);
    });

    test('restores the downloaded archive through the disaster-recovery form', async ({ page }) => {
        expect(downloadedArchivePath).not.toBeNull();
        expect(encryptionKeyValue).not.toBeNull();

        await page.goto('/admin/backups');

        // The DR form uploads the archive and resolves the key by value.
        await page.getByTestId('backup-list-page-upload-file-input').setInputFiles(downloadedArchivePath as string);
        await page.getByTestId('backup-list-page-upload-key').fill(encryptionKeyValue as string);
        await page.getByTestId('backup-list-page-upload-password').fill(ADMIN_PASSWORD);
        await page.getByTestId('backup-list-page-upload-submit').click();

        // Under QUEUE_CONNECTION=sync (CI) the restore already finished
        // inside the submit request — the progress UI may never render.
        // Under Redis (local) the job sits in the queue until drained.
        const progress = page.getByTestId('backup-list-page-upload-progress');

        try {
            await progress.waitFor({ state: 'visible', timeout: 3_000 });
            drainQueue();
        } catch {
            // The restore completed synchronously — nothing to drain.
        }

        // The restore job puts the site into maintenance and lifts it
        // again once the import finished — the whole round trip takes
        // about a second, so the completion effects are asserted rather
        // than the transient 503 window.
        const request = page.request;

        await expect
            .poll(async () => (await request.get('/blog')).status(), { timeout: 180_000 })
            .toBe(200);

        // The restored database serves the seeded content again and the
        // DR import left its backup record as completed.
        await page.goto('/blog/understanding-laravel-queues');
        await expect(page.getByTestId('blog-article-page-content-title')).toHaveText(
            'Understanding Laravel Queues',
        );

        // The restore flushed the sessions — re-authenticate for the
        // final admin check.
        await loginAsAdmin(page);
        await page.goto('/admin/backups');
        await expect(page.getByTestId('backup-list-page')).toBeVisible();
        await expect(
            page.locator('[data-testid^="backup-list-page-table-status-"]', { hasText: 'Completed' }),
        ).toBeVisible();
        await expect(page.locator('[data-testid^="backup-list-page-table-download-"]').first()).toBeEnabled();
    });
});
