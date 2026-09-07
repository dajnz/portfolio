import type { Page, TestInfo } from '@playwright/test';
import type { Result } from 'axe-core';
import { getViolations, injectAxe } from 'axe-playwright';

/**
 * Page-level axe-core audits (accessibility.md:249-267). Public pages:
 * critical/serious violations fail the test, moderate/minor are recorded
 * as warnings. Admin pages: every impact is a warning — the admin
 * surface serves a single authenticated user and never blocks the
 * pipeline on a11y findings.
 */

const BLOCKING_IMPACTS = new Set(['critical', 'serious']);

async function violationsSummary(violations: Result[]): Promise<string> {
    return violations
        .map((violation) => {
            const nodes = violation.nodes.map((node) => `${node.target.join(' ')} (${node.html})`).join('\n');

            return `${violation.impact ?? 'n/a'} ${violation.help}\n${nodes}`;
        })
        .join('\n');
}

export async function expectPublicPageAxeClean(page: Page, testInfo: TestInfo): Promise<void> {
    await injectAxe(page);

    const violations = await getViolations(page);
    const blocking = violations.filter((violation) => BLOCKING_IMPACTS.has(violation.impact ?? ''));
    const warnings = violations.filter((violation) => !BLOCKING_IMPACTS.has(violation.impact ?? ''));

    if (warnings.length > 0) {
        await record('axe-warnings', await violationsSummary(warnings), testInfo);
    }

    if (blocking.length > 0) {
        const summary = await violationsSummary(blocking);

        throw new Error(`axe-core found ${blocking.length} critical/serious violation(s):\n${summary}`);
    }
}

export async function reportAdminAxeWarnings(page: Page, testInfo: TestInfo): Promise<void> {
    await injectAxe(page);

    const violations = await getViolations(page);

    if (violations.length > 0) {
        await record('axe-warnings', await violationsSummary(violations), testInfo);
    }
}

async function record(type: string, description: string, testInfo: TestInfo): Promise<void> {
    await testInfo.annotations.push({ type, description });

    for (const line of description.split('\n')) {
        console.warn(`[${type}] ${line}`);
    }
}
