import { readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import type { Plugin, ResolvedConfig } from 'vite';

/**
 * The bunny fonts plugin emits one `@font-face` per downloaded format —
 * woff2 AND the legacy woff fallback, with identical descriptors. CSS
 * font matching resolves identical faces to the LAST declared rule, so
 * modern browsers download the larger `.woff` files and never use the
 * `.woff2`. Every `.woff` face is dropped from the emitted fonts CSS
 * (the Laravel `@fonts` style block is inlined from that same file) and
 * the now-unreferenced `.woff` files are removed from the build.
 */

const WOFF_SRC_MARKER = 'format("woff")';
const WOFF2_SRC_MARKER = 'format("woff2")';

/** True when the CSS carries any legacy `.woff` face declaration. */
function hasWoffFallbackFaces(source: string): boolean {
    return source.includes(WOFF_SRC_MARKER);
}

/**
 * Removes every `@font-face` block whose src is woff-only. Blocks that
 * also list a woff2 source are kept intact.
 */
function stripWoffOnlyFaces(source: string): string {
    return source
        .split(/(?=@font-face)/)
        .filter((part) => {
            if (!part.includes(WOFF_SRC_MARKER)) {
                return true;
            }

            return part.includes(WOFF2_SRC_MARKER);
        })
        .join('');
}

/** The emitted fonts stylesheets (the only files carrying font faces). */
function fontsCssFiles(files: string[]): string[] {
    return files.filter(
        (file) => file.startsWith('fonts-') && file.endsWith('.css'),
    );
}

function stripWoffFacesFromStylesheets(assetsDir: string, cssFiles: string[]): void {
    for (const file of cssFiles) {
        const path = join(assetsDir, file);
        const source = readFileSync(path, 'utf8');

        if (hasWoffFallbackFaces(source)) {
            writeFileSync(path, stripWoffOnlyFaces(source));
        }
    }
}

/** Deletes the physical `.woff` files once the CSS no longer references them. */
function unlinkWoffFiles(assetsDir: string, files: string[]): void {
    for (const file of files) {
        if (file.endsWith('.woff')) {
            rmSync(join(assetsDir, file));
        }
    }
}

export function stripWoffFallbackFaces(): Plugin {
    let config: ResolvedConfig;

    return {
        name: 'strip-woff-fallback-faces',
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
            try {
                files = readdirSync(assetsDir);
            } catch {
                return;
            }

            const cssFiles = fontsCssFiles(files);
            stripWoffFacesFromStylesheets(assetsDir, cssFiles);
            unlinkWoffFiles(assetsDir, files);
        },
    };
}
