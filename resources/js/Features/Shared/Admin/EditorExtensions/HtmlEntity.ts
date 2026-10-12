/**
 * HTML-entity Tiptap extension — decodes the HTML entities authors type in the
 * raw markdown source (`&nbsp;`, `&copy;`, `&euro;`, ...) to their characters
 * so the rendered view shows the character instead of the literal text.
 *
 * Only a curated allow-list of entities is decoded: spacing helpers and common
 * symbols. Everything else stays literal text, which keeps invisible control
 * and formatting characters (bidirectional marks, zero-width joiners,
 * line/paragraph separators) out of a document.
 */

import type { MarkdownTokenizer } from '@tiptap/core';
import { Extension } from '@tiptap/core';

const ALLOWED_ENTITY_NAMES = new Set([
    // spacing
    'nbsp',
    'ensp',
    'emsp',
    'thinsp',
    'hairsp',
    // punctuation & typography
    'mdash',
    'ndash',
    'hellip',
    'bull',
    'middot',
    'prime',
    'Prime',
    'laquo',
    'raquo',
    'lsquo',
    'rsquo',
    'ldquo',
    'rdquo',
    'sbquo',
    'bdquo',
    'dagger',
    'Dagger',
    'deg',
    'para',
    'sect',
    'permil',
    // legal & currency
    'copy',
    'reg',
    'trade',
    'cent',
    'pound',
    'curren',
    'yen',
    'euro',
    // math & arrows
    'plusmn',
    'times',
    'divide',
    'micro',
    'minus',
    'ne',
    'le',
    'ge',
    'asymp',
    'equiv',
    'infin',
    'radic',
    'larr',
    'rarr',
    'uarr',
    'darr',
    'harr',
]);

// Spacing characters have no distinct glyph, so their entity spelling has to
// survive the serializer (which would otherwise emit the bare character).
const SPACING_ENTITIES: ReadonlyArray<readonly [character: string, name: string]> = [
    ['\u00A0', 'nbsp'],
    ['\u2002', 'ensp'],
    ['\u2003', 'emsp'],
    ['\u2009', 'thinsp'],
    ['\u200A', 'hairsp'],
];

const SPACING_CHARACTER_PATTERN = new RegExp(
    `[${SPACING_ENTITIES.map(([character]) => character).join('')}]`,
    'g',
);
const SPACING_ENTITY_BY_CHARACTER = new Map(SPACING_ENTITIES);

const ENTITY_ANYWHERE = /&[a-zA-Z][a-zA-Z0-9]*;/;
const ENTITY_AT_START = /^&([a-zA-Z][a-zA-Z0-9]*);/;

export function encodeSpacingEntities(markdown: string): string {
    return markdown.replace(SPACING_CHARACTER_PATTERN, (character) => {
        const name = SPACING_ENTITY_BY_CHARACTER.get(character);

        return name === undefined ? character : `&${name};`;
    });
}

let decoder: HTMLTextAreaElement | null = null;

function decodeHtmlEntity(entity: string): string {
    if (typeof document === 'undefined') {
        return entity;
    }

    decoder ??= document.createElement('textarea');
    decoder.innerHTML = entity;

    return decoder.value;
}

function htmlEntityTokenizer(): MarkdownTokenizer {
    return {
        name: 'htmlEntity',
        level: 'inline',
        start: (src: string): number => src.search(ENTITY_ANYWHERE),
        tokenize(src: string) {
            const match = ENTITY_AT_START.exec(src);

            if (match === null || !ALLOWED_ENTITY_NAMES.has(match[1])) {
                return undefined;
            }

            const decoded = decodeHtmlEntity(match[0]);

            if (decoded === match[0]) {
                return undefined;
            }

            return { type: 'text', raw: match[0], text: decoded };
        },
    };
}

export const HtmlEntity = Extension.create({
    name: 'htmlEntity',
    markdownTokenizer: htmlEntityTokenizer(),
});
