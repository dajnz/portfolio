import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { Page, TestInfo } from '@playwright/test';
import { HtmlValidate, type ConfigData } from 'html-validate';

/**
 * Inline W3C HTML validation of the hydrated public DOM. Runs
 * `html-validate` on `page.content()` and records every message as a
 * `console.warn`-equivalent annotation. Violations never fail the test —
 * HTML validation is a diagnostic, not a gate.
 *
 * `.htmlvalidate.json` notes (all diagnostics-only, never a gate):
 * - `attribute-boolean-style` runs with `style: "empty"` — Vue renders
 *   boolean props as empty-valued attributes (`required=""`), and the
 *   project standardizes on that serialization.
 * - `no-trailing-whitespace` and `attribute-empty-style` are relaxed:
 *   whitespace-only lines and empty-valued attributes (`crossorigin=""`
 *   on Vite asset tags) appear inside the serialized DOM by framework
 *   emission — semantically identical to the bare forms.
 */
export async function validatePublicHtml(page: Page, testInfo: TestInfo): Promise<void> {
    await page.waitForLoadState('networkidle');

    // `new HtmlValidate()` without options does NOT pick up the project
    // `.htmlvalidate.json` — load it explicitly so the repo-wide rules
    // (and their documented relaxations) apply to the inline checks.
    const configPath = resolve(fileURLToPath(new URL('..', import.meta.url)), '../../.htmlvalidate.json');
    const config = JSON.parse(readFileSync(configPath, 'utf8')) as {
        extends: string[];
        rules: Record<string, unknown>;
    };

    // The constructor takes the inline (programmatic) schema — severity
    // strings are file-schema-only, so they are normalized to numbers.
    const SEVERITY: Record<string, number> = { off: 0, warn: 1, error: 2 };

    const rules = Object.fromEntries(
        Object.entries(config.rules).map(([name, value]) => {
            if (typeof value === 'string') {
                return [name, SEVERITY[value] ?? 2];
            }

            if (Array.isArray(value) && typeof value[0] === 'string') {
                return [name, [SEVERITY[value[0]] ?? 2, ...value.slice(1)]];
            }

            return [name, value];
        }),
    );

    const options: ConfigData = {
        extends: config.extends,
        rules: rules as ConfigData['rules'],
    };

    const htmlvalidate = new HtmlValidate(options);
    const report = await htmlvalidate.validateString(await page.content());

    const messages: string[] = [];

    for (const result of report.results) {
        for (const message of result.messages) {
            messages.push(`${result.filePath}:${message.line}:${message.column} ${message.message}`);
        }
    }

    if (messages.length > 0) {
        await testInfo.annotations.push({ type: 'html-validate', description: messages.join('\n') });

        for (const line of messages) {
            console.warn(`[html-validate] ${line}`);
        }
    }
}
