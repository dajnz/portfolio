<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use Highlight\Highlighter;
use Throwable;

/**
 * Server-side code highlighting for the content pipeline
 * (content-management.md:473-481): wraps the pure-PHP
 * `scrivo/highlight.php` port of highlight.js (the same engine family
 * as the admin editor's lowlight — tech-stack.md:42) and renders the
 * tokens as inline-color spans matching the mockup's palette
 * (`docs/mockups/public/blog-article.html` — the "Shiki-processed
 * HTML" of the components spec is replaced by this engine per the
 * step-8.2 deviation; the mockup's visual output — colored tokens,
 * no-JS functionality — is preserved).
 *
 * The emitted block carries the progressive-enhancement markers the
 * public `CodeBlock` component mounts on (public-section-components.md:
 * 1285): `<pre data-code-block data-language="..." data-raw="...">`
 * wrapping the highlighted `<code>`. `data-raw` holds the raw source
 * (the copy button's payload) and the wrapper is fully static — no
 * JavaScript is required to read the highlighted code.
 *
 * Unknown languages degrade to plain escaped text (no token spans),
 * so a typo in a fence info string can never break the render.
 */
final class CodeHighlighter
{
    /**
     * The token class → color map of the inline-style theme — the
     * mockup's moss/plum/gold palette with sensible defaults for the
     * token kinds the mockup does not exercise.
     *
     * @var array<string, string>
     */
    private const THEME = [
        'keyword' => '#1e7a5b',
        'meta' => '#1e7a5b',
        'literal' => '#1e7a5b',
        'tag' => '#1e7a5b',
        'section' => '#1e7a5b',
        'addition' => '#1e7a5b',
        'built_in' => '#2f9e6e',
        'type' => '#2f9e6e',
        'class' => '#2f9e6e',
        'number' => '#2f9e6e',
        'symbol' => '#2f9e6e',
        'regexp' => '#2f9e6e',
        'attr' => '#2f9e6e',
        'attribute' => '#2f9e6e',
        'title' => '#6e52a0',
        'name' => '#6e52a0',
        'selector-id' => '#6e52a0',
        'string' => '#b8842a',
        'params' => '#b8842a',
        'deletion' => '#d64545',
        'comment' => '#7a8699',
        'quote' => '#7a8699',
        'variable' => '#16211b',
        'template-variable' => '#16211b',
        'subst' => '#16211b',
        'doctag' => '#1e7a5b',
        'bullet' => '#1e7a5b',
        'emphasis' => '#1e7a5b',
        'strong' => '#1e7a5b',
        'formula' => '#2f9e6e',
    ];

    public function __construct(
        private readonly Highlighter $highlighter,
    ) {
    }

    /**
     * Renders the highlighted code block — the `data-code-block`
     * wrapper with the language + raw-code attributes (the CodeBlock
     * mount markers) around the inline-colored token spans. Language-
     * less fences (the editor's "none" option) fall back to
     * auto-detection (ADR-0008 — the public blocks were plain otherwise;
     * the default autodetect subset covers the common web languages).
     */
    public function highlight(string $code, string $language): string
    {
        $languageId = $this->languageId($language);
        [$languageId, $tokens] = $this->tokens($code, $languageId);

        return '<pre data-code-block data-language="'.htmlspecialchars($languageId, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            .'" data-raw="'.htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            .'"><code>'.$tokens.'</code></pre>';
    }

    /**
     * The token HTML of the block + the resolved language id (the
     * auto-detected one for language-less fences, which the CodeBlock
     * label displays).
     *
     * @return array{0: string, 1: string}
     */
    private function tokens(string $code, string $languageId): array
    {
        if ($languageId === '' || $languageId === 'text' || $languageId === 'plaintext' || $languageId === 'plain') {
            return $this->autodetect($code);
        }

        return [$languageId, $this->tokenHtml($code, $languageId)];
    }

    /**
     * Auto-detection for language-less fences: `highlightAuto` scores
     * the code against the library's default subset (xml, json,
     * javascript, css, php, http). A failed or empty detection
     * degrades to plain escaped text.
     *
     * @return array{0: string, 1: string}
     */
    private function autodetect(string $code): array
    {
        try {
            $auto = $this->highlighter->highlightAuto($code);
        } catch (Throwable) {
            $auto = null;
        }

        $languageId = is_object($auto) ? (string) ($auto->language ?? '') : '';
        $value = is_object($auto) ? (string) ($auto->value ?? '') : '';

        if ($languageId === '' || $value === '') {
            return ['', htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
        }

        return [$languageId, $this->colorize($value)];
    }

    private function tokenHtml(string $code, string $languageId): string
    {
        if ($languageId === '' || $languageId === 'text' || $languageId === 'plaintext' || $languageId === 'plain') {
            return htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        try {
            $html = $this->highlighter->highlight($languageId, $code)->value;
        } catch (Throwable) {
            return htmlspecialchars($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $this->colorize($html);
    }

    /**
     * Rewrites the engine's `hljs-*` token classes into the mockup's
     * inline-color theme spans (the palette of `blog-article.html`).
     */
    private function colorize(string $html): string
    {
        return preg_replace_callback(
            '/<span class="(?:hljs-)?([a-z0-9_-]+)(?:\s+[a-z0-9_-]+)*"[^>]*>/i',
            static function (array $match): string {
                $color = self::THEME[$match[1]] ?? null;

                return $color === null
                    ? '<span>'
                    : '<span style="color:'.$color.'">';
            },
            $html,
        ) ?? $html;
    }

    /**
     * The first info word of a fence (` ```typescript foo ` →
     * `typescript`) — the language identifier of the block.
     */
    private function languageId(string $info): string
    {
        $first = strtok($info, " \t\n");

        return $first === false ? '' : $first;
    }
}
