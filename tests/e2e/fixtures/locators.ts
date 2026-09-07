import type { Page } from '@playwright/test';

/**
 * Dynamic-testid locators (testing.md:387 — lists append the runtime id,
 * e.g. `article-card-${article.id}`). When the id is not known statically,
 * tests resolve the element through its testid prefix instead of a CSS
 * class or tag selector — the DOM attribute stays the only locator input.
 */
export function getByTestIdPrefix(page: Page, prefix: string) {
    return page.locator(`[data-testid^="${prefix}"]`);
}

/**
 * Resolves the full testid of the single element whose id-suffixed
 * testid starts with `prefix` AND whose text contains `text`. Returns
 * null when nothing matches (e.g. an entity is absent from a list).
 */
export async function testidOfCardWithText(page: Page, prefix: string, text: string): Promise<string | null> {
    const candidates = page.locator(`[data-testid^="${prefix}"]`);

    const count = await candidates.count();

    for (let index = 0; index < count; index++) {
        const candidate = candidates.nth(index);

        if ((await candidate.textContent())?.includes(text)) {
            return (await candidate.getAttribute('data-testid')) ?? '';
        }
    }

    return null;
}

/** Types into an autocomplete input and clicks the first suggestion. */
export async function pickFirstSuggestion(page: Page, inputTestId: string, query: string): Promise<void> {
    const input = page.getByTestId(inputTestId);

    await input.click();
    await input.fill(query);

    // The suggestion rows are keyed off the control's testid, not the
    // input's (`{control}-suggestion-{index}`) — the input suffix is
    // stripped before the lookup.
    const base = inputTestId.replace(/-input$/, '');
    const suggestion = page.locator(`[data-testid^="${base}-suggestion-"]`).first();

    await suggestion.click();
}
