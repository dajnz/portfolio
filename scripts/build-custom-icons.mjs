#!/usr/bin/env node
// Builds the custom icon subset from drop-in SVG files.
//
// The strict-subset pipeline (scripts/build-icon-subsets.mjs) can only
// source icons from the @iconify-json packs in node_modules — it cannot
// ship glyphs that do not exist in Iconify (e.g. a private brand mark).
// This script is the manual counterpart: every file in
// resources/icons/custom/ becomes one icon in the hand-fed `custom` set
// (resources/js/iconSubsets/custom.json, registered statically in
// resources/js/iconRegistry.ts). The file name is the icon id, so
// `resources/icons/custom/messenger.svg` renders as `custom:messenger`.
//
// Conventions (enforced here, so the build fails loudly instead of
// shipping a corrupt glyph):
//   - flat, single-color SVG art (no gradients/patterns/embedded styles),
//   - a 100x100 viewBox ("0 0 100 100"),
//   - a lowercase [a-z0-9-]+ file name (the icon id).
// All non-empty colors are normalized to `currentColor`, so icons follow
// the text color and dark mode like the simple-icons glyphs.
//
// Workflow: drop an SVG into resources/icons/custom/, run
// `make custom-icons` (npm run icons:custom), commit the updated
// resources/js/iconSubsets/custom.json together with the new SVG.

import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { IconSet, SVG, cleanupSVG, parseColors, parseSVG } from '@iconify/tools';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sourceDir = join(root, 'resources/icons/custom');
const outputFile = join(root, 'resources/js/iconSubsets/custom.json');
const width = 100;
const height = 100;
// Flat SVG shapes a custom icon may contain; the `g` grouping element is
// allowed too. Anything else (defs, gradients, masks, text, ...) cannot
// render as a predictable single-color glyph and is rejected.
const allowedShapes = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon'];
const allowedGroups = ['g'];

function fail(message) {
    console.error(`\nCustom icon build failed:\n${message}`);
    process.exit(1);
}

function iconNameFromFile(file) {
    // The icon id is the file name (minus the .svg extension) verbatim —
    // `custom:{name}` must match what the file is called.
    const name = file.slice(0, -4);

    if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(name)) {
        fail(
            `"${file}" has an invalid icon name "${name}".\n`
            + 'Use a lowercase name with letters, digits and dashes only '
            + '(the id will be "custom:' + name + '").',
        );
    }

    return name;
}

function parseSvgFile(file) {
    let svg;

    try {
        svg = new SVG(readFileSync(join(sourceDir, file), 'utf8'));
    } catch (error) {
        fail(`"${file}" is not a valid SVG file (${error.message}).`);
    }

    return svg;
}

function assertConventionalViewBox(file, svg) {
    const viewBox = svg.viewBox;

    if (
        viewBox.left !== 0
        || viewBox.top !== 0
        || viewBox.width !== width
        || viewBox.height !== height
    ) {
        fail(
            `"${file}" does not match the "0 0 100 100" viewBox convention `
            + `(got ${viewBox.left} ${viewBox.top} ${viewBox.width}x${viewBox.height}). `
            + 'Design the icon on a 100x100 canvas and export it without cropping.',
        );
    }
}

function cleanSvgMarkup(file, svg) {
    try {
        // Converts inline styles to attributes and removes junk — the
        // rendered icon is the raw body inside the Iconify <svg>, so no
        // root-level SVG attribute can carry over (see below).
        cleanupSVG(svg);
    } catch (error) {
        fail(
            `"${file}" cannot be cleaned up (${error.message}). `
            + 'Export the icon with flat attributes — no <style> blocks, '
            + 'no gradients, no patterns.',
        );
    }
}

function convertToSingleColor(file, svg) {
    try {
        // Enforce the single-color convention before touching colors:
        // parseColors() silently skips url() fills, so a gradient icon
        // would otherwise pass through as-is. Only flat shapes are
        // allowed — anything that references defs (gradients, patterns,
        // masks, ...) cannot render as a predictable currentColor glyph.
        parseSVG(svg, ({ node, parents }) => {
            if (parents.length === 0) {
                // The root <svg> element — its attributes are stripped
                // from the body anyway; check only the shapes.
                return;
            }

            if (!allowedShapes.includes(node.tag) && !allowedGroups.includes(node.tag)) {
                throw new Error(
                    `unsupported element <${node.tag}> — keep the icon to flat `
                    + 'single-color shapes (no gradients, patterns, masks or text)',
                );
            }

            for (const [attr, value] of Object.entries(node.attribs)) {
                if (typeof value === 'string' && value.includes('url(')) {
                    throw new Error(
                        `${attr}="${value}" references a gradient/pattern — `
                        + 'the custom set is single-color only',
                    );
                }
            }
        });

        parseColors(svg, {
            // Every color becomes currentColor (except 'none'/'transparent',
            // which are kept) so the glyph inherits the text color in both
            // themes; elements without an explicit color get the default.
            callback: (_attr, colorString) => {
                const lower = colorString.trim().toLowerCase();

                if (['none', 'transparent', 'currentcolor'].includes(lower)) {
                    return colorString;
                }

                return 'currentColor';
            },
            defaultColor: 'currentColor',
        });
    } catch (error) {
        fail(
            `"${file}" cannot be converted to a single-color icon `
            + `(${error.message}).`,
        );
    }
}

function assertHasDrawableShapes(file, svg) {
    let drawableCount = 0;
    parseSVG(svg, ({ node, parents }) => {
        if (parents.length > 0 && allowedShapes.includes(node.tag)) {
            drawableCount += 1;
        }
    });

    if (drawableCount === 0) {
        fail(`"${file}" produced an empty icon — the SVG has no drawable shapes.`);
    }
}

function loadIcon(file) {
    const svg = parseSvgFile(file);
    assertConventionalViewBox(file, svg);
    cleanSvgMarkup(file, svg);
    convertToSingleColor(file, svg);
    assertHasDrawableShapes(file, svg);

    return svg;
}

function main() {
    mkdirSync(sourceDir, { recursive: true });

    const files = readdirSync(sourceDir)
        .filter((file) => file.endsWith('.svg'))
        .sort();
    const seen = new Set();
    const iconSet = new IconSet({ prefix: 'custom', width, height, icons: {} });

    for (const file of files) {
        const name = iconNameFromFile(file);

        if (seen.has(name)) {
            fail(`Duplicate icon id "custom:${name}" (check the file names).`);
        }

        seen.add(name);

        if (!iconSet.fromSVG(name, loadIcon(file))) {
            fail(`"${file}" failed to import into the custom icon set.`);
        }

        console.log(`custom:${name}  <-  ${relative(root, join(sourceDir, file))}`);
    }

    // Export validates the set; the subset is then re-assembled into the
    // canonical per-prefix shape the other iconSubsets files use (top-level
    // width/height, per-icon body only, no lastModified stamp — the strict
    // subsets are diffable, so the output must be deterministic).
    const exported = iconSet.export();
    const icons = {};

    for (const [name, icon] of Object.entries(exported.icons)) {
        icons[name] = { body: icon.body };
    }

    const subset = { prefix: 'custom', width, height, icons };
    writeFileSync(outputFile, `${JSON.stringify(subset, null, 4)}\n`);
    const size = (readFileSync(outputFile).length / 1024).toFixed(1);
    const count = Object.keys(icons).length;

    console.log(
        `${relative(root, outputFile)}  ${count} icon${count === 1 ? '' : 's'}  `
        + `${size} KB — add SVGs to ${relative(root, sourceDir)} and re-run \`make custom-icons\`.`,
    );
}

main();
