import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * Script-size budget table shared with the Lighthouse CI job: reads
 * `devops/lighthouse-config/budgets.json` — the committed single source
 * of truth mirroring the architecture.md:1203-1210 Performance Budget
 * table. The file is resolved from this module's location, so loading
 * never depends on the current working directory.
 */

const BUDGETS_FILE = fileURLToPath(
    new URL('../lighthouse-config/budgets.json', import.meta.url),
);

export interface ScriptBudget {
    /** URL-path pattern from the budget table (e.g. `/blog/*`). */
    path: string;
    /** Gzipped-JS cap in KB. */
    budgetKb: number;
}

/**
 * Loads the `script` resource budgets. Returns null when the table is
 * unavailable or malformed — the build-time check is advisory, so the
 * caller warns and skips rather than failing the build.
 */
export function loadScriptBudgets(): ScriptBudget[] | null {
    try {
        const budgets = JSON.parse(readFileSync(BUDGETS_FILE, 'utf8')) as ReadonlyArray<{
            path?: string;
            resourceSizes?: ReadonlyArray<{
                resourceType?: string;
                budget?: number;
            }>;
        }>;

        return budgets.flatMap((budget) =>
            (budget.resourceSizes ?? [])
                .filter((size) => size.resourceType === 'script' && typeof size.budget === 'number')
                .map((size) => ({
                    path: budget.path ?? '',
                    budgetKb: size.budget as number,
                })),
        );
    } catch {
        return null;
    }
}

/** Indexes the table by URL-path pattern for O(1) lookups. */
export function scriptBudgetsByPath(budgets: ScriptBudget[]): Map<string, number> {
    return new Map(budgets.map((budget) => [budget.path, budget.budgetKb]));
}
