# ADR-0025: Public page UX conventions

- **Status:** Superseded by ADR-0031
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A10, B79, B238, B239, B242, B262, B264, B266, B267, B269
- **Supersedes:** —

## Context

The public surface follows static HTML mockups as its visual reference, but mockups and component specs conflicted on several interactions. These conventions settle navigation visibility, card/grid behavior, the projects list and detail page, and small-screen behavior for all public features.

## Decision

- **Nav visibility:** Blog and Experience links are always visible; Projects is conditional on ≥1 published project; landing anchors (About/Contacts) are always shown. The Projects condition is derived as `featured_projects.length > 0 || has_more` (no contract change).
- **Nav links** in `PublicPageHeader` are plain anchors styled as text links with an animated underline (`.link--nav`) — the mockup wins over the `PublicBaseButton(variant: ghost)` tree.
- **Timezone label:** the landing maps the IANA timezone to an Intl-derived label (`timeZoneName: 'short'` + compact `longOffset`, e.g. "CEST (UTC+2)"), falling back to the raw value for unknown zones (deterministic SSR/hydration).
- **Contact rows** carry a right-aligned icon-only copy button (`heroicons:clipboard` → `check` flash, copies `url` else `value`, `title`/`aria-label` "Copy contact"; clipboard unavailable → silent); simple-icons glyphs are scaled `transform: scale(0.9)` so they are not clipped.
- **Featured cards** do not prefetch/navigate on hover — `usePrefetch().onHover` calling `router.visit` was removed. The shared `.card`/`.card--hoverable`/`.card__{image,title,description,tags,actions}` pattern lives in the **global** `resources/css/app.css` (it spans index, landing and the detail writeup); feature modifiers stay scoped.
- **Projects grid:** 3 cards per row driven by container queries hosted by `.projects-shell` (`container-type: inline-size`; `@container` 640px → 2 cols, 980px → 3 cols, 1 col below), so the layout is robust to `--layout-content`. The toolbar is sticky (`top: var(--header-height)`) with search + grid/list toggle and tag filter + sort; tag pills are exactly `--input-height` tall, one line with horizontal overflow. `ProjectCard` caps tags via `tags_limit` (3 grid, 5 list) with a "+N" chip.
- **Tag filter:** unwraps the skill-search `{data: [...]}` payload, closes on a document-level outside click (suggestions use `@mousedown.prevent`), and projects with no image render a shared placeholder.
- **Project detail:** the "back to projects" button is removed; markdown element styles live in a **non-scoped** `<style>` block (v-html content never carries the Vue scope attribute) and the overview body uses a `div` (block output cannot nest in `<p>`); the Sources/Live Demo block (`.project-detail__bottom`) is two columns when both exist, one when one exists, hidden when neither; the lightbox stage uses a keyed directional fade+slide transition.
- **Card carousels** use compact dots (`compact_dots` → `carousel--compact-dots`, 9px with 8px padding); the detail gallery keeps the larger look. The no-image placeholder has no text label, a 5.25em photo icon at opacity 0.5, and is shared by the landing cards.
- **Search-autocomplete** rows show the date as `dd.mm.yyyy` with the localized `common.published` label, meta flush right and the title ellipsized.
- **Small screens (below 768px):** the projects index hides the grid/list switch, stacks the tag filter and sort on their own full-width rows, and adds a funnel ↔ chevron-up collapse toggle (`projects.show_filters`/`hide_filters`, `aria-expanded`/`aria-controls`, `projects-toolbar--filters-hidden`); the detail bottom block collapses to one column. The toolbar widening is gated behind `@media (width >= 1064px)` to avoid horizontal overflow between ~768px and ~1063px.
- **Blog list/detail (step 9.4):** `ArticleReactions` owns its POST (fetch + XSRF header, emits `react` for analytics only); selected pills hydrate from the `tags` prop / first batch `skill_tags`; `ArticleContent` upgrades server-emitted code-block/callout markers by mounting real components (`h()`+`render()`, re-run on `content_html` changes); TOC extraction is the page's job (h2/h3 with ids via DOMParser); the year filter renders only when `years.length > 1` and its "All" sentinel `''` maps to `null`; the grid/list toggle is hidden below 768px; article meta dates use `Intl.DateTimeFormat`; search snippets carry sanitized `<mark>` rendered via `v-html`.

## Consequences

- Public visual decisions defer to the mockups when mockups and component specs disagree.
- The shared `.card` pattern is global; new card consumers must not redefine it locally.
- Projects list/detail responsive behavior is coupled to the `1064px` and `768px` breakpoints and to `.projects-shell` container queries.
- Blog rendering relies on server-sanitized HTML markers that the client upgrades in place.
