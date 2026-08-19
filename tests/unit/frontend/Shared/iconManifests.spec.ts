import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

// Mirrors the coverage guard of scripts/build-icon-subsets.mjs: every
// `set:name` icon literal in the frontend source AND the integration test
// fixtures must be listed in one of the strict icon manifests
// (resources/js/iconManifests). An unlisted icon would silently fall back
// to a runtime Iconify API fetch (B78 — offline, no trackers), so this
// guard fails the test suite instead.

const sourceDir = join(process.cwd(), 'resources/js');
const fixturesDir = join(process.cwd(), 'tests/integration/frontend');
const manifestsDir = join(sourceDir, 'iconManifests');
const excludedDirs = new Set(['Types', 'iconManifests', 'iconSubsets']);
const knownPrefixes = new Set(['heroicons', 'simple-icons', 'devicon', 'lucide']);

function readJson(file: string): unknown {
    return JSON.parse(readFileSync(file, 'utf8')) as unknown;
}

function listManifestIdentifiers(): string[] {
    const identifiers: string[] = [];

    for (const file of ['brands.json', 'technical.json']) {
        const entries = readJson(join(manifestsDir, file)) as string[];
        identifiers.push(...entries);
    }

    return identifiers;
}

function collectSourceIdentifiers(): string[] {
    const identifiers: string[] = [];
    const regex = /([a-z0-9-]+):([a-zA-Z0-9-]+)/g;

    function walk(dir: string, excluded: Set<string>): void {
        for (const entry of readdirSync(dir, { withFileTypes: true })) {
            const full = join(dir, entry.name);

            if (entry.isDirectory()) {
                if (!excluded.has(entry.name)) {
                    walk(full, excluded);
                }
                continue;
            }

            if (!/\.(ts|vue)$/.test(entry.name)) {
                continue;
            }

            const source = readFileSync(full, 'utf8');
            let match;

            while ((match = regex.exec(source)) !== null) {
                if (!knownPrefixes.has(match[1])) {
                    continue;
                }

                // Skip matches embedded in longer words (e.g. URLs).
                const before = source[match.index - 1] ?? '';
                const after = source[match.index + match[0].length] ?? '';
                if (/[a-zA-Z0-9-]/.test(before) || /[a-zA-Z0-9-]/.test(after)) {
                    continue;
                }

                identifiers.push(match[0]);
            }
        }
    }

    walk(sourceDir, excludedDirs);
    walk(fixturesDir, new Set());
    return identifiers;
}

describe('icon manifests', () => {
    it('cover every icon literal used in the source code', () => {
        const allowed = new Set(listManifestIdentifiers());
        const used = collectSourceIdentifiers();
        const missing = [...new Set(used)].filter((identifier) => !allowed.has(identifier));

        expect(missing).toEqual([]);
    });

    it('reference only icons present in the iconify packs', () => {
        const identifiers = listManifestIdentifiers();

        for (const identifier of identifiers) {
            const [prefix, name] = identifier.split(':');
            const pack = readJson(join('node_modules', '@iconify-json', prefix, 'icons.json')) as {
                icons: Record<string, unknown>;
                aliases?: Record<string, unknown>;
            };

            expect(pack.icons[name] ?? pack.aliases?.[name], identifier).toBeDefined();
        }
    });
});
