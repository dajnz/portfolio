/**
 * Callout Tiptap node (admin-section-components.md:237) — renders as a
 * bordered block and round-trips through the `> [!TYPE]` GitHub-style
 * blockquote marker (the format the public content pipeline renders per
 * ADR-0008). Parsing uses a custom marked tokenizer so the marker syntax
 * survives markdown parse → editor → serialize without loss.
 *
 * The markdown contract lives in TOP-LEVEL extension fields
 * (`markdownTokenName`, `markdownTokenizer`, `parseMarkdown`,
 * `renderMarkdown` — declared natively on Tiptap's `NodeConfig`):
 * @tiptap/markdown's `registerExtension` reads them via
 * `getExtensionField` (MarkdownManager.registerExtension), while the
 * nested `markdown: { ... }` object is a type-only artifact of a local
 * augmentation that never reached the runtime serializer — a
 * nested-only config silently drops the node from BOTH serialize and
 * parse (2026-08-14 review find; the augmentation was removed).
 */

import type { MarkdownToken, MarkdownTokenizer } from '@tiptap/core';
import { mergeAttributes, Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';

import CalloutNodeView from './CalloutNodeView.vue';

export const CALLOUT_TYPES = ['warning', 'tip', 'important', 'tradeoff'] as const;

export type CalloutType = (typeof CALLOUT_TYPES)[number];

/** The per-type color set — shared by the NodeView (in-editor) and
 *  `renderHTML` (copy/paste + non-view contexts). */
export const CALLOUT_CLASSES: Record<CalloutType, string> = {
    warning:
        'border-warning-500 bg-warning-50 dark:bg-warning-950 text-warning-700 dark:text-warning-400',
    tip: 'border-success-500 bg-success-50 dark:bg-success-950 text-success-700 dark:text-success-400',
    important:
        'border-primary-500 bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300',
    tradeoff:
        'border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 text-slate-600 dark:text-slate-300',
};

/** The style-guide callout frame (style-guide.html #callouts): full
 *  `--radius-md` border radius (0.625rem), 4px left border, `--space-md
 *  var(--space-lg)` padding (1rem 1.5rem) and `--space-lg` block margin
 *  — shared by the NodeView and `renderHTML`. */
export const CALLOUT_FRAME_CLASSES = 'my-6 rounded-[0.625rem] border-l-4 px-6 py-4';

const CALLOUT_MARKER = /^[ \t]*>[ \t]*\[!(WARNING|TIP|IMPORTANT|TRADEOFF)\][ \t]*$/i;
const CALLOUT_INLINE_MARKER = /^\[!(WARNING|TIP|IMPORTANT|TRADEOFF)\][ \t]*$/i;

function calloutTokenizer(): MarkdownTokenizer {
    return {
        name: 'callout',
        level: 'block',
        start(src: string): number {
            return CALLOUT_MARKER.test(src) ? 0 : -1;
        },
        tokenize(src: string, _tokens: MarkdownToken[], lexer) {
            const lines = src.split('\n');
            const contentLines: string[] = [];
            let consumed = 0;
            let index = 0;
            const blockquoteLine = /^[ \t]*>[ \t]?(.*)$/;

            while (index < lines.length) {
                const match = blockquoteLine.exec(lines[index]);

                if (match !== null) {
                    contentLines.push(match[1]);
                    consumed += lines[index].length + 1;
                    index += 1;
                    continue;
                }

                if (lines[index].trim() === '') {
                    const next = index + 1;

                    if (next < lines.length && blockquoteLine.test(lines[next])) {
                        contentLines.push('');
                        consumed += lines[index].length + 1;
                        index += 1;
                        continue;
                    }
                }

                break;
            }

            if (index >= lines.length && !src.endsWith('\n')) {
                consumed -= 1;
            }

            const marker = CALLOUT_INLINE_MARKER.exec(contentLines[0] ?? '');

            if (marker === null) {
                return undefined;
            }

            const type = marker[1].toLowerCase() as CalloutType;
            contentLines.shift();

            return {
                type: 'callout',
                calloutType: type,
                raw: src.slice(0, Math.max(consumed, 0)),
                tokens: lexer.blockTokens(contentLines.join('\n')),
            };
        },
    };
}

export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return {
            type: {
                default: 'warning',
            },
        };
    },
    parseHTML() {
        return [
            {
                tag: 'div[data-callout]',
                getAttrs: (element) => ({
                    type: (element as HTMLElement).getAttribute('data-callout') ?? 'warning',
                }),
            },
        ];
    },
    addNodeView() {
        return VueNodeViewRenderer(CalloutNodeView);
    },
    renderHTML({ HTMLAttributes }) {
        const type = (HTMLAttributes.type as CalloutType) ?? 'warning';

        return [
            'div',
            mergeAttributes(HTMLAttributes, {
                'data-callout': type,
                class: `${CALLOUT_FRAME_CLASSES} ${CALLOUT_CLASSES[type]}`,
            }),
            0,
        ];
    },
    markdownTokenName: 'callout',
    markdownTokenizer: calloutTokenizer(),
    parseMarkdown: (token, helpers) =>
        helpers.createNode(
            'callout',
            { type: token.calloutType ?? 'warning' },
            helpers.parseChildren(token.tokens ?? []),
        ),
    renderMarkdown: (node, helpers) => {
        const type = String((node.attrs?.type as CalloutType) ?? 'warning').toUpperCase();
        // The "\n\n" separator mirrors the doc node's own join — without
        // it multi-block callouts (para + para, heading + para) collapse
        // into one line and the blank `>` rows vanish on the round trip.
        const body = helpers.renderChildren(node.content ?? [], '\n\n');
        const lines = body.split('\n').map((line) => (line.trim() === '' ? '>' : `> ${line}`));

        return `> [!${type}]\n${lines.join('\n')}`;
    },
});
