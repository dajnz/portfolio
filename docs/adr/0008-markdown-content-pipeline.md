# ADR-0008: Shared Markdown content pipeline & highlighting

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B23, B217, B261, B270, B273
- **Supersedes:** —

## Context

Markdown bodies appear on the public blog and (later) project detail pages, and code blocks must be syntax-highlighted server-side. The spec originally chose Shiki for highlighting, but Shiki is a JS library that cannot run in the PHP-FPM production image (no Node, and a per-render subprocess bridge would break the production architecture). A single reusable render path was needed so editor preview and public render stay consistent.

## Decision

- **Shared pipeline (B23):** the CommonMark + callouts + HTML-cache pipeline is a `Shared` service reused by the blog article detail, the projects detail bodies (`full_description`, `problem`, `solution`), and any feature rendering Markdown.
- **Highlighting engine (B217):** the pure-PHP `scrivo/highlight.php` port of highlight.js replaces Shiki — the same engine family as the admin editor's lowlight, with a custom inline-color theme matching the mockup's moss/plum/gold palette. The `data-code-block`/`data-language`/`data-raw` markers and the no-JS static output are preserved.
- **No client re-highlight (B217):** the public page never re-highlights in the browser; the server renders `content_html` for every Inertia navigation, and the `CodeBlock` component only adds a copy button — so no client/server divergence is possible.
- **Snippet sanitization (B217):** `ts_headline` snippets return raw article text with `<mark>` tags; `ArticleCardDto::fromRow` escapes every other tag so an admin-authored raw-HTML excerpt can never execute.
- **Project detail bodies (B261):** `ProjectDetailDtoBuilder` injects the shared `MarkdownContentPipelineInterface` and renders `full_description`/`problem`/`solution` for the request locale (en fallback) into `ProjectDetailBodiesDto`; `ProjectDetailPropsBuilder::detail()` maps the rendered HTML through for both the public and signed-preview controllers, and the frontend injects it as-is (`v-html`).
- **HTML cache key (B270):** `article:html:{id}:{locale}:{content-hash}` where the hash is xxh128 (16 chars); any content change rotates the key, while non-content base touches (publish flags, tags) keep it. `articleHtml()` dropped its `$updatedAt` parameter.
- **Callout headings (B273):** `CalloutBlock` always renders the heading row with the per-type icon (`heroicons:exclamation-triangle`/`light-bulb`/`information-circle`/`arrow-path`) — the Markdown title when present, otherwise the localized type label via `blog.callout_{warning,tip,important,tradeoff}` (EN/RU). Raw pipeline asides on project detail pages still show title-less callouts without the label row.

## Consequences

- Highlighting is server-rendered and cached; the copy button is the only client-side code-block behaviour, so editors see a slightly different (lowlight) preview than the public render only in theme, not in engine.
- The `article:html` cache is self-invalidating on content changes but still keyed per locale; non-content updates deliberately reuse the cached HTML.
- The project detail bodies render identically in preview and public because both go through the shared pipeline and props builder.
- The pipeline is `Shared`; any new Markdown surface must consume `MarkdownContentPipelineInterface` rather than rolling its own renderer.
