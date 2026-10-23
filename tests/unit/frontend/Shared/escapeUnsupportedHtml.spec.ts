import { describe, expect, it } from 'vitest';

import { escapeUnsupportedHtml } from '@shared-admin/TextEditor/escapeUnsupportedHtml';

describe('escapeUnsupportedHtml', () => {
    it('escapes HTML tags the editor cannot represent', () => {
        expect(escapeUnsupportedHtml('Before <script>alert(1)</script> after')).toBe(
            'Before &lt;script&gt;alert(1)&lt;/script&gt; after',
        );
    });

    it('keeps supported editor HTML tags', () => {
        expect(escapeUnsupportedHtml('H<sub>2</sub>O and <strong>bold</strong>')).toBe(
            'H<sub>2</sub>O and <strong>bold</strong>',
        );
    });

    it('leaves fenced and inline code untouched', () => {
        const markdown = [
            'Inline `<div>` code.',
            '',
            '```html',
            '<script>alert(1)</script>',
            '```',
        ].join('\n');

        expect(escapeUnsupportedHtml(markdown)).toBe(markdown);
    });

    it('keeps Markdown autolinks', () => {
        expect(escapeUnsupportedHtml('<https://example.com> <user@example.com>')).toBe(
            '<https://example.com> <user@example.com>',
        );
    });
});
