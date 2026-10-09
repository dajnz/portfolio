import { expect, type Page } from '@playwright/test';

/**
 * Shared admin e2e helpers. Content created by the admin specs gets a
 * `unique()` token so parallel specs and re-runs never collide, and the
 * specs delete their rows before finishing — the read-only public specs
 * then see the pristine seeded baseline (the config runs the public
 * project only after the admin project finished).
 */

export function unique(label: string): string {
    return `${label}-${Date.now().toString(36)}`;
}

/**
 * The numeric id of the list row whose visible text contains `needle`.
 * Row-level testids carry the DB id (`{page}-table-row-{id}`), which is
 * unknown until the row exists — the id is read from the DOM.
 */
export async function rowIdByText(
    page: Page,
    pageTestId: string,
    needle: string,
    marker = 'table-row',
): Promise<number | null> {
    const prefix = `${pageTestId}-${marker}-`;

    const firstRow = page.locator(`[data-testid^="${prefix}"]`).first();

    await firstRow.waitFor({ state: 'visible', timeout: 15_000 });

    return page.evaluate(
        ([attrPrefix, text]) => {
            const checkboxes = Array.from(document.querySelectorAll<HTMLElement>(`[data-testid^="${attrPrefix}"]`));

            for (const checkbox of checkboxes) {
                const row = checkbox.closest('tr');

                if (row !== null && (row.textContent ?? '').includes(text)) {
                    const id = checkbox.getAttribute('data-testid')?.slice(attrPrefix.length);

                    if (id !== undefined && id !== '') {
                        return Number(id);
                    }
                }
            }

            return null;
        },
        [prefix, needle] as const,
    );
}

export function rowLocator(page: Page, pageTestId: string, id: number) {
    return page.locator(`tr:has([data-testid="${pageTestId}-table-row-${id}"])`);
}

/**
 * Sets a checkbox/switch to the wanted state. The testid sometimes sits
 * on the `<input>` itself (blog form toggles) and sometimes on the
 * wrapping root (experience/project checkboxes) — the real input is
 * resolved either way. The toggle goes through the native
 * `HTMLInputElement.click()` (Playwright pointer clicks can land on the
 * sticky action bar / other overlays, and the styled sr-only tracks
 * intercept force-click coordinates).
 */
export async function setCheckbox(page: Page, testId: string, checked: boolean): Promise<void> {
    const root = page.getByTestId(testId);
    const isInput = (await root.getAttribute('type')) === 'checkbox';
    const box = isInput ? root : root.locator('input[type="checkbox"]').first();

    const currentlyChecked = await box.isChecked();

    if (currentlyChecked !== checked) {
        await box.evaluate((element) => {
            (element as HTMLInputElement).click();
        });
    }

    await expect(box).toBeChecked({ checked });
}

/**
 * Waits for a toast with the given text — the toast stack is shared per
 * page across Inertia visits (earlier toasts may still be dismissing),
 * so the expectation polls the whole stack instead of a fixed index.
 */
export async function expectToast(page: Page, text: string): Promise<void> {
    await expect
        .poll(
            async () => (await page.locator('[data-testid^="toast-"]').allInnerTexts()).join('\n'),
            { timeout: 60_000 },
        )
        .toContain(text);
}

/**
 * Types markdown into the WYSIWYG editor of the currently active locale
 * (line by line — the toolbar's raw-mode toggle only switches the shared
 * editor instance, which the locale-tab swap re-creates and resets).
 */
export async function typeMarkdown(page: Page, editorTestId: string, markdown: string): Promise<void> {
    const content = page.getByTestId(`${editorTestId}-content`);

    await expect(content).toBeVisible();

    const prose = content.locator('.ProseMirror');

    await expect(prose).toBeVisible();
    await prose.click();

    for (const line of markdown.split('\n')) {
        await page.keyboard.type(line, { delay: 1 });
        await page.keyboard.press('Enter');
    }
}
