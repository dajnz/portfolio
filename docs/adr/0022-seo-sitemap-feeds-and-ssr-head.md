# ADR-0022: SEO, sitemap, feeds & SSR head

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B64, B65, B86, B214, B220, B324
- **Supersedes:** —

## Context

The SEO spec requires localized titles/meta/JSON-LD, hreflang alternates, a sitemap, an RSS feed and a correct `/robots.txt`. Because the app is Inertia + SSR with a CSP, decisions were needed on where head markup is produced and how the sitemap cache is invalidated without leaking framework concerns into the domain layer.

## Decision

- `SeoProps` is committed in `docs/openapi/inertia/shared.yaml` with **snake_case** field names (Inertia contract convention), plus a `seo` prop on the six public page schemas and a `json_ld` field (needed so the Blade layout can SSR-render localized JSON-LD with the CSP nonce). `SeoProps`/`JsonLd` are re-exported from `CrossSurface/Types/seo.ts` against the generated `inertia.ts`; Inertia spec was bumped to 1.0.3.
- **SSR/CSR head split:** the Blade root layout renders title/meta/JSON-LD from `$page['props']['seo']` during SSR. Meta tags carry `data-inertia=""` so `@inertiajs/core`'s head Renderer treats them as managed and replaces them with identical client-rendered elements after hydration (no duplicates). JSON-LD scripts carry the CSP nonce and are hex-escaped (`JSON_HEX_TAG|AMP|APOS|QUOT`) to prevent `</script>` breakout.
- `useSeoUpdater` is client-only (window guard): it mounts the Inertia `<Head>` component via `h()`+`render()` into an off-DOM container (recreated per sync), reads `usePage().props.seo`/`cspNonce`, inserts/removes `script[data-seo-jsonld]` on page change and scope dispose, and applies the OG defaults (`og_title`←title, `og_description`←description, `og_image`←`/images/og-default.png` at 1200×630, `og_type`←website).
- **hreflang:** `<link rel="alternate" hreflang>` is rendered on every public HTML page by derivation — no OpenAPI/`SeoProps` change. Locale-prefixed variants plus `x-default` are computed from the existing `canonical_url` + shared `supported_locales` props in `app.blade.php` (SSR) and `useSeoUpdater` (CSR), with identical sets so the head manager replaces 1:1; covered by `PublicHeadHreflangTest` (incl. disabled-locale omission) and useSeoUpdater unit cases.
- **Sitemap invalidation lives in the app layer**, not models/repositories: `Shared/App/Contracts/SitemapInvalidatorInterface` (implemented by `SitemapService`, cache key `sitemap:xml`) is injected into the five write use cases — store (published only), update (was or is published), destroy/restore/force (+Many, probed via `ArticleRepositoryInterface::publishedIdsOf()`), and publication toggles (unconditional). Eloquent models must not orchestrate invalidation; the translation model keeps only the `reading_time`/`search_vector` recompute.
- Sitemap `lastmod` is the **base table's `updated_at` only** — the translation tables carry no timestamps.
- RSS XML is built by `Shared/App/Services/RssXmlBuilder` (pure, framework-free string construction) with `RssFeedDto`/`RssItemDto` in `Shared/App/DTOs/`; `FeedController` only wires app-layer feed data and returns the response.
- The Laravel scaffold `public/robots.txt` was deleted: under nginx `try_files` the static file shadowed the `SitemapController` route, so `/robots.txt` served wrong content in real deployments (integration tests hit the controller directly).
- Also recorded in the same cross-surface step (B86): ThemeSwitch shows the **moon** icon in the light state and the sun in dark (target-mode convention); `SortableList` cannot inject classes into host slot content, so hosts own their handle styling.

## Consequences

- SEO contract changes require OpenAPI edits plus a CHANGELOG entry and version bump; new public pages must carry `seo`/`json_ld`.
- The SSR/CSR duplication depends on `data-inertia` and the head manager; new meta tags must preserve that contract.
- Any new write use case that changes published content must call the `SitemapInvalidatorInterface`; model-level hooks are forbidden.
- `/robots.txt` must always be served by the route, never by a static `public/` file.
