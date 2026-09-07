import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import type { Logger, Plugin, ResolvedConfig } from 'vite';

import { loadScriptBudgets, scriptBudgetsByPath } from './budgets.ts';
import { gzipTotalKb, staticClosureOf } from './chunk-graph.ts';

/**
 * Per-page gzip budget warnings (architecture.md:1256-1264, mechanism 1).
 *
 * Each budgeted page is matched by the file-name prefix of its async page
 * chunk (Inertia resolves pages lazily via `import.meta.glob`, so every
 * page ships its own chunk). After a client build the total gzipped JS a
 * page would transfer is the entry's static chunk closure (vue-vendor,
 * app, ...) plus the page chunk's own static closure — a warning fires
 * when that total exceeds the page's documented budget from
 * `devops/lighthouse-config/budgets.json`. Data-driven async chunks
 * (icon packs fetched when icons first render) are outside the static
 * closure — those are covered by the Lighthouse CI budget assertions
 * (mechanism 2), which measure the real transferred bytes.
 */

/** Page label + async chunk prefix + the budget-table URL path. */
const PAGE_CHUNKS: ReadonlyArray<{ page: string; marker: string; budgetPath: string }> = [
    { page: 'Landing', marker: 'LandingPage', budgetPath: '/' },
    { page: 'Blog index', marker: 'BlogIndexPage', budgetPath: '/blog' },
    { page: 'Blog article', marker: 'BlogArticlePage', budgetPath: '/blog/*' },
    { page: 'Projects index', marker: 'ProjectsIndexPage', budgetPath: '/projects' },
    { page: 'Project detail', marker: 'ProjectDetailPage', budgetPath: '/projects/*' },
    { page: 'Experience', marker: 'ExperienceTimelinePage', budgetPath: '/experience' },
];

/** The emitted chunk file for the app entry, or null when unknown. */
function entryChunkFile(files: string[], manifest: Record<string, { file?: string }>): string | null {
    const entry = manifest['resources/js/app.ts']?.file?.split('/').pop();

    return entry && files.includes(entry) ? entry : null;
}

/** The page chunk file matching the marker, or null when not emitted. */
function pageChunkFile(files: string[], marker: string): string | null {
    return files.find((file) => file.startsWith(marker) && file.endsWith('.js')) ?? null;
}

function reportBudget(
    logger: Logger,
    page: string,
    totalKb: number,
    budgetKb: number,
): void {
    const label = `${page.padEnd(14)} ${totalKb.toFixed(1).padStart(7)} KB gzip / ${budgetKb} KB budget`;

    if (totalKb > budgetKb) {
        logger.warn(
            `[page-js-budget] ${label} — page chunk exceeds its documented budget (architecture.md Performance Budget).`,
        );
    } else {
        logger.info(`[page-js-budget] ${label}`);
    }
}

function checkBudgets(
    logger: Logger,
    assetsDir: string,
    files: string[],
    entry: string,
): void {
    const budgets = loadScriptBudgets();
    if (budgets === null) {
        logger.warn(
            '[page-js-budget] devops/lighthouse-config/budgets.json is unavailable — skipping budget check',
        );

        return;
    }

    const budgetsByPath = scriptBudgetsByPath(budgets);
    const entryClosure = staticClosureOf(assetsDir, entry);

    for (const { page, marker, budgetPath } of PAGE_CHUNKS) {
        const budgetKb = budgetsByPath.get(budgetPath);
        const pageChunk = pageChunkFile(files, marker);

        if (budgetKb === undefined || pageChunk === null) {
            continue;
        }

        const totalKb = gzipTotalKb(
            assetsDir,
            entryClosure,
            staticClosureOf(assetsDir, pageChunk),
        );
        reportBudget(logger, page, totalKb, budgetKb);
    }
}

export function pageJsBudgetWarnings(): Plugin {
    let config: ResolvedConfig;

    return {
        name: 'page-js-budget-warnings',
        apply: 'build',
        configResolved(resolved) {
            config = resolved;
        },
        closeBundle() {
            if (config.build.ssr || config.command === 'serve') {
                return;
            }

            const assetsDir = join(config.build.outDir, config.build.assetsDir);

            let files: string[];
            let manifest: Record<string, { file?: string }>;
            try {
                files = readdirSync(assetsDir);
                manifest = JSON.parse(
                    readFileSync(join(config.build.outDir, 'manifest.json'), 'utf8'),
                );
            } catch {
                config.logger.warn(
                    '[page-js-budget] build output or manifest.json is unavailable — skipping budget check',
                );

                return;
            }

            const entry = entryChunkFile(files, manifest);
            if (entry === null) {
                config.logger.warn(
                    '[page-js-budget] app entry chunk not found in manifest — skipping budget check',
                );

                return;
            }

            checkBudgets(config.logger, assetsDir, files, entry);
        },
    };
}
