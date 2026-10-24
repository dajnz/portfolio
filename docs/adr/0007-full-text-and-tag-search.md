# ADR-0007: Full-text & tag search

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A11, B16, B179, B223
- **Supersedes:** —

## Context

The public site needs keyword search over articles (and, later, projects) plus lightweight autocomplete for skills and tags. The spec required PostgreSQL full-text search with highlighted snippets in the Inertia response, but the JSON response variant and the exact ranking/keyset behaviour had to be pinned down consistently across features.

## Decision

- **Tag/skill autocomplete ordering (A11):** name ascending, limit 20, for `/api/skills/search`, `/api/tags/search`, `/api/blog-tags/search`.
- **Snippets (B16):** Inertia HTML search results include `ts_headline()` snippets wrapping matches in `<mark>` (parameters: `MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>`). The JSON response variant returns raw field values with no highlights.
- **Shared `FtsQueryBuilder` (B16):** lives in `Shared/Infra/Search/` and provides `search()` (`plainto_tsquery` + `ts_rank(..., 32)` normalization, no `setweight`), `autocomplete()` (prefix `:*` with an empty-tsquery CASE guard), and `headline()` (queries longer than 200 chars are truncated; an empty query returns all).
- **`search_vector` definition (B16):** built with `to_tsvector(dictionary, title || ' ' || brief_description || ' ' || content)`; for projects `title || ' ' || brief_description || ' ' || full_description`.
- **Projects `?q=` ordering (B179):** filter via `plainto_tsquery` over the locale-resolved `search_vector`, ORDER BY `ts_rank(..., 32)` with a keyset cursor `(rank, id)` and the rank expression repeated in the seek predicates. The committed `ProjectCardData`/`ProjectItem` contracts carry no snippet field, so the projects search returns the documented card data only; `ts_headline`/`<mark>` snippets remain the B16 shared-builder concern.
- **Security hardening (B223):** the user query and dictionary always travel as bound parameters; the only interpolated strings are caller-supplied column expressions, validated against a strict identifier/`coalesce()` whitelist (no operators, quotes, string literals or nested parens — invalid expressions throw). Highlighting is verified XSS-safe end-to-end: `scrivo/highlight.php` HTML-escapes code content, the `data-raw`/`data-language` attributes are `ENT_QUOTES`-escaped, and the span post-processor rewrites only literal `<span class="hljs-...">` openings.

## Consequences

- Ranking is intentionally simple (`ts_rank` with normalization flag 32, no `setweight`); changing weights would be a cross-feature behavioural change.
- The keyset cursor must repeat the rank expression in its seek predicates, so any change to the rank expression must be applied in both the ORDER BY and the cursor predicate.
- The JSON variant and the Inertia HTML variant deliberately diverge (no highlights in JSON); clients must not expect snippet fields there.
- Column expressions passed into `FtsQueryBuilder` are trusted-but-validated: the whitelist means no arbitrary SQL expression can be injected, and callers must keep expressions within it.
