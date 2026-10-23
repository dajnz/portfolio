const SUPPORTED_HTML_TAGS = new Set([
    'a',
    'b',
    'blockquote',
    'br',
    'code',
    'col',
    'colgroup',
    'del',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'i',
    'img',
    'li',
    'ol',
    'p',
    'pre',
    's',
    'span',
    'strike',
    'strong',
    'sub',
    'sup',
    'table',
    'tbody',
    'td',
    'th',
    'thead',
    'tr',
    'u',
    'ul',
]);

const FENCED_CODE = /(`{3,}|~{3,})[^\n]*\n[\s\S]*?^\1\s*$/gm;
const INLINE_CODE = /(`+)([\s\S]*?)\1/g;
const HTML_TAG = /<(\/?)([a-zA-Z][a-zA-Z0-9-]*)([^<>]*)>/g;
const PLACEHOLDER = /\uE000(\d+)\uE001/g;

/**
 * Escapes HTML tags the editor schema cannot represent so the Markdown
 * parser shows them as literal text instead of silently dropping them.
 * Fenced and inline code content is left untouched.
 */
export function escapeUnsupportedHtml(markdown: string): string {
    const codeChunks: string[] = [];

    const protectCode = (chunk: string): string => {
        codeChunks.push(chunk);

        return `\uE000${codeChunks.length - 1}\uE001`;
    };

    let text = markdown.replace(FENCED_CODE, protectCode).replace(INLINE_CODE, protectCode);

    text = text.replace(HTML_TAG, (match, _closing: string, tag: string) => {
        if (SUPPORTED_HTML_TAGS.has(tag.toLowerCase())) {
            return match;
        }

        const inner = match.slice(1, -1);

        if (isAutolink(inner)) {
            return match;
        }

        return `&lt;${inner}&gt;`;
    });

    return text.replace(PLACEHOLDER, (_match, index: string) => codeChunks[Number(index)] ?? '');
}

function isAutolink(inner: string): boolean {
    const value = inner.startsWith('/') ? inner.slice(1) : inner;

    return /^[a-z][a-z0-9+.-]*:\/\/\S+$/i.test(value) || /^[^<>\s]+@[^<>\s]+$/.test(value);
}
