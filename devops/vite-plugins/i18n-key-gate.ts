import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import type { Plugin, ResolvedConfig } from 'vite';

/**
 * Build-time `t()` key gate (i18n-and-theming.md:145-147, ADR-0009).
 *
 * Scans the TypeScript and Vue sources under `resources/js` for literal
 * `t('key')` / `$t('key')` calls and fails the build when a key is absent
 * from either committed catalog (`resources/js/lang/en.json` and
 * `resources/js/lang/ru.json`). The catalogs are generated
 * from the PHP sources by `lang:export` and committed, so a missing key
 * means the frontend would silently render the key itself (see
 * `useI18n`). Dynamic keys (template literals with `${…}`, concatenation,
 * variables) are skipped — they cannot be resolved statically.
 *
 * The gate also asserts EN/RU key parity: `useI18n` falls back to the key
 * (not to `en`) when the active locale lacks an entry, so a key present in
 * only one catalog is a real gap.
 */

const SOURCE_DIR = 'resources/js';
const CATALOG_DIR = join(SOURCE_DIR, 'lang');
export const SOURCE_EXTENSIONS = ['.ts', '.vue'] as const;
export const CATALOG_LOCALES = ['en', 'ru'] as const;

/**
 * A literal translation call: `t('key')`, `$t("key")`, or a static
 * template literal `t(`key`)`. The trailing `[),]` rejects concatenation
 * (`t('prefix.' + value)`) and other operators; the lookbehind keeps
 * method calls such as `this.t(` or `format(` out. Keys containing `${`
 * are dynamic and filtered by the caller.
 */
const TRANSLATION_CALL = /(?<![\w.$])(\$?t)\s*\(\s*(['"`])([\s\S]*?)\2\s*[),]/g;

export function collectLiteralTranslationKeys(source: string): string[] {
    const keys: string[] = [];

    for (const match of source.matchAll(TRANSLATION_CALL)) {
        const key = match[3];

        if (key.includes('${')) {
            continue;
        }

        keys.push(key);
    }

    return keys;
}

/** Flattens a nested catalog object into dot-separated leaf keys. */
export function flattenCatalog(
    catalog: unknown,
    prefix = '',
    out = new Map<string, unknown>(),
): Map<string, unknown> {
    if (catalog !== null && typeof catalog === 'object' && !Array.isArray(catalog)) {
        for (const [name, value] of Object.entries(catalog)) {
            flattenCatalog(value, prefix === '' ? name : `${prefix}.${name}`, out);
        }
    } else {
        out.set(prefix, catalog);
    }

    return out;
}

export interface TranslationCatalog {
    locale: string;
    keys: Set<string>;
}

/** Returns `key (locale)` for every literal key missing from a catalog. */
export function findMissingKeys(
    keys: Iterable<string>,
    catalogs: ReadonlyArray<TranslationCatalog>,
): string[] {
    const missing: string[] = [];

    for (const key of keys) {
        for (const catalog of catalogs) {
            if (!catalog.keys.has(key)) {
                missing.push(`${key} (${catalog.locale})`);
            }
        }
    }

    return missing;
}

/** Returns `key (present-locale)` for keys not shared by all catalogs. */
export function findCatalogDrift(catalogs: ReadonlyArray<TranslationCatalog>): string[] {
    const drift: string[] = [];

    for (const catalog of catalogs) {
        for (const key of catalog.keys) {
            for (const other of catalogs) {
                if (other !== catalog && !other.keys.has(key)) {
                    drift.push(`${key} (${catalog.locale} only)`);
                }
            }
        }
    }

    return drift;
}

function collectSourceFiles(dir: string, out: string[] = []): string[] {
    for (const entry of readdirSync(dir)) {
        const full = join(dir, entry);

        if (statSync(full).isDirectory()) {
            collectSourceFiles(full, out);
        } else if (SOURCE_EXTENSIONS.some((extension) => entry.endsWith(extension))) {
            out.push(full);
        }
    }

    return out;
}

function loadCatalogs(root: string): TranslationCatalog[] {
    return CATALOG_LOCALES.map((locale) => ({
        locale,
        keys: new Set(
            flattenCatalog(
                JSON.parse(
                    readFileSync(join(root, CATALOG_DIR, `${locale}.json`), 'utf8'),
                ) as unknown,
            ).keys(),
        ),
    }));
}

function fail(message: string): never {
    throw new Error(`[i18n-key-gate] ${message}`);
}

export function i18nKeyGate(): Plugin {
    let config: ResolvedConfig;

    return {
        name: 'i18n-key-gate',
        apply: 'build',
        configResolved(resolved) {
            config = resolved;
        },
        buildStart() {
            const root = config.root;
            const catalogs = loadCatalogs(root);

            const drift = findCatalogDrift(catalogs);
            if (drift.length > 0) {
                fail(`EN/RU catalogs differ:\n  ${drift.join('\n  ')}`);
            }

            const files = collectSourceFiles(join(root, SOURCE_DIR));

            const usedKeys = new Set<string>();
            for (const file of files) {
                for (const key of collectLiteralTranslationKeys(readFileSync(file, 'utf8'))) {
                    usedKeys.add(key);
                }
            }

            const missing = findMissingKeys(usedKeys, catalogs);
            if (missing.length > 0) {
                fail(`literal t() keys without a catalog entry:\n  ${missing.join('\n  ')}`);
            }

            config.logger.info(
                `[i18n-key-gate] ${usedKeys.size} literal t() keys verified in ${catalogs.length} catalogs`,
            );
        },
    };
}
