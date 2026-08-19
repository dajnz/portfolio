#!/usr/bin/env node
// Builds the strict icon subsets used at runtime.
//
// The app never imports whole @iconify-json packs (heroicons 616 KB,
// devicon 4.9 MB, simple-icons 4.6 MB raw — the two lazy icon chunks
// alone were ~10 MB). Instead the icon manifests in
// resources/js/iconManifests/{brands,technical}.json list every icon
// the app is allowed to use as `set:name` identifiers, possibly mixing
// several iconify sets in one list. This script:
//
//   1. groups the identifiers by prefix and validates every name against
//      the original pack in node_modules (unknown names fail the build),
//   2. resolves alias chains (an alias pulls in its parent),
//   3. writes a slim per-prefix collection to
//      resources/js/iconSubsets/{prefix}.json, keeping only the listed
//      icons (+ referenced aliases) — the only files the app imports,
//   4. runs the usage-coverage guard: every `set:name` literal found in
//      resources/js source must be listed in one of the manifests, so an
//      unlisted icon fails the build instead of silently falling back to
//      a runtime Iconify API fetch (B78 — offline, no trackers).
//
// Workflow: add/remove names in the manifests, run `npm run icons:build`
// (also wired as the `npm run build` pre-step), commit the updated
// subsets together with the manifest change.

import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const manifestsDir = join(root, 'resources/js/iconManifests');
const subsetsDir = join(root, 'resources/js/iconSubsets');
const sourceDir = join(root, 'resources/js');
const excludedDirs = new Set(['Types', 'iconManifests', 'iconSubsets']);

function fail(message) {
    console.error(`\nIcon subset build failed:\n${message}`);
    process.exit(1);
}

function readJson(file) {
    return JSON.parse(readFileSync(file, 'utf8'));
}

function iconifyPack(prefix) {
    return require(`@iconify-json/${prefix}/icons.json`);
}

function listManifests() {
    const identifiers = [];
    for (const file of ['brands.json', 'technical.json']) {
        const entries = readJson(join(manifestsDir, file));
        for (const entry of entries) {
            if (!/^[a-z0-9-]+:[a-zA-Z0-9-]+$/.test(entry)) {
                fail(`"${entry}" in ${file} is not a valid "set:name" identifier.`);
            }
            identifiers.push({ file, ...parseIdentifier(entry) });
        }
    }
    return identifiers;
}

function parseIdentifier(identifier) {
    const [prefix, name] = identifier.split(':');
    return { prefix, name, identifier };
}

function resolveAliases(pack, name) {
    // Returns the chain of names needed to render `name`: the terminal
    // icon plus every alias that leads to it.
    const chain = [];
    let current = name;

    while (pack.aliases[current] !== undefined) {
        chain.push(current);
        current = pack.aliases[current].parent;
    }

    if (pack.icons[current] === undefined) {
        return null;
    }

    chain.push(current);
    return chain;
}

function buildSubset(pack, entries) {
    const icons = {};
    const aliases = {};

    for (const { name } of entries) {
        const chain = resolveAliases(pack, name);

        if (chain === null) {
            fail(`"${name}" is not in the ${pack.prefix} icon set.`);
        }

        for (const link of chain) {
            if (pack.icons[link] !== undefined) {
                icons[link] = pack.icons[link];
            } else {
                aliases[link] = pack.aliases[link];
            }
        }
    }

    return {
        prefix: pack.prefix,
        width: pack.width,
        height: pack.height,
        icons,
        ...(Object.keys(aliases).length > 0 ? { aliases } : {}),
    };
}

function writeSubset(prefix, subset) {
    const output = join(subsetsDir, `${prefix}.json`);
    writeFileSync(output, `${JSON.stringify(subset, null, 4)}\n`);
    return output;
}

function collectSourceTokens() {
    // Collects every `set:name` literal from the frontend source and the
    // integration test fixtures (template `icon="..."` attributes,
    // `:icon="'...'"` bindings and TS string literals). Generated
    // OpenAPI types, the subset JSONs and the manifests themselves are
    // excluded. The test fixtures are covered too — a fixture-only icon
    // missing from the subsets would trigger the same runtime Iconify
    // API fetch in the specs (B78).
    const knownPrefixes = new Set(['heroicons', 'simple-icons', 'devicon', 'lucide']);
    const tokens = [];
    const regex = /([a-z0-9-]+):([a-zA-Z0-9-]+)/g;
    const roots = [join(root, 'resources/js'), join(root, 'tests/integration/frontend')];

    function walk(dir, excluded) {
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

                tokens.push({ identifier: match[0], file: relative(root, full) });
            }
        }
    }

    walk(roots[0], excludedDirs);
    walk(roots[1], new Set());
    return tokens;
}

function checkCoverage(manifestIdentifiers) {
    const allowed = new Set(manifestIdentifiers.map(({ identifier }) => identifier));
    const offenders = collectSourceTokens().filter(({ identifier }) => !allowed.has(identifier));

    if (offenders.length > 0) {
        const lines = offenders.map(({ identifier, file }) => `  ${identifier}  (${file})`);
        fail(
            'Icon(s) used in the source code are missing from the manifests:\n'
            + `${lines.join('\n')}\n`
            + 'Add them to resources/js/iconManifests/brands.json or technical.json and re-run.',
        );
    }
}

function groupByPrefix(identifiers) {
    const groups = new Map();

    for (const entry of identifiers) {
        if (!groups.has(entry.prefix)) {
            groups.set(entry.prefix, []);
        }
        groups.get(entry.prefix).push(entry);
    }

    return groups;
}

function main() {
    const identifiers = listManifests();
    checkCoverage(identifiers);

    for (const [prefix, entries] of groupByPrefix(identifiers)) {
        const pack = iconifyPack(prefix);
        const subset = buildSubset(pack, entries);
        const output = writeSubset(prefix, subset);
        const size = (readFileSync(output).length / 1024).toFixed(1);
        console.log(`${relative(root, output)}  ${Object.keys(subset.icons).length} icons  ${size} KB`);
    }
}

main();
