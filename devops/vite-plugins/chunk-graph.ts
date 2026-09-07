import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { gzipSync } from 'node:zlib';

/**
 * Static chunk-graph helpers for the build-output audit. Chunk files in
 * the built `assets` dir reference their statically-imported siblings
 * through `from "./file.js"` specifiers; walking those edges from the
 * app entry (plus a page chunk) yields the JS a cold page load
 * transfers before any data-driven async import runs.
 */

export function gzipKbOf(assetsDir: string, file: string): number {
    return gzipSync(readFileSync(join(assetsDir, file))).length / 1024;
}

/** The chunk files a given emitted chunk statically imports. */
export function staticImportsOf(assetsDir: string, file: string): string[] {
    const source = readFileSync(join(assetsDir, file), 'utf8');
    const imports: string[] = [];

    for (const match of source.matchAll(/from\s*"\.\/([^"]+\.js)"/g)) {
        imports.push(match[1]);
    }

    return [...new Set(imports)];
}

/** The transitive static-import closure of one emitted chunk. */
export function staticClosureOf(assetsDir: string, start: string): Set<string> {
    const seen = new Set<string>();
    const visit = (file: string): void => {
        if (seen.has(file)) {
            return;
        }

        seen.add(file);
        staticImportsOf(assetsDir, file).forEach(visit);
    };
    visit(start);

    return seen;
}

/** Gzipped total (KB) of the union of two chunk closures. */
export function gzipTotalKb(assetsDir: string, ...closures: ReadonlySet<string>[]): number {
    const union = new Set<string>();

    for (const closure of closures) {
        for (const file of closure) {
            union.add(file);
        }
    }

    let totalKb = 0;
    for (const file of union) {
        totalKb += gzipKbOf(assetsDir, file);
    }

    return totalKb;
}
