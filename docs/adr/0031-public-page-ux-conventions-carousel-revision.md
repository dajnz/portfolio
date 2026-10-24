# ADR-0031: Public page UX conventions (carousel navigation revision)

- **Status:** Accepted
- **Date:** 2026-10-12
- **Source:** ADR-0025 (carried forward); this change revises the carousel bullet.
- **Supersedes:** ADR-0025

## Context

ADR-0025 settled the public surface's navigation visibility, card behavior, projects
list/detail, and small-screen rules. Its carousel bullet left navigation controls
always-on, which read poorly: the prev/next buttons lost contrast over dark imagery,
the dot indicators were too small to tap on phones, and a single-item carousel still
showed controls for content that cannot scroll. This ADR carries ADR-0025 forward and
replaces its carousel bullet with the revised rules.

## Decision

- **Nav visibility:** Blog and Experience links are always visible; Projects is conditional on ≥1 published project; landing anchors (About/Contacts) are always shown. The Projects condition is derived as `featured_projects.length > 0 || has_more` (no contract change).
- **Nav links** in `PublicPageHeader` are plain anchors styled as text links with an animated underline (`.link--nav`) — the mockup wins over the `PublicBaseButton(variant: ghost)` tree.
- **Timezone label:** the landing maps the IANA timezone to an Intl-derived label (`timeZoneName: 'short'` + compact `longOffset`, e.g. "CEST (UTC+2)"), falling back to the raw value for unknown zones (deterministic SSR/hydration).
- **Contact rows** carry a right-aligned icon-only copy button (`heroicons:clipboard` → `check` flash, copies `url` else `value`, `title`/`aria-label` "Copy contact"; clipboard unavailable → silent); simple-icons glyphs are scaled `transform: scale(0.9)` so they are not clipped.
- **Featured cards** do not prefetch/navigate on hover — `usePrefetch().onHover` calling `router.visit` was removed. The shared `.card`/`.card--hoverable`/`.card__{image,title,description,tags,actions}` pattern lives in the **global** `resources/css/app.css` (it spans index, landing and the detail writeup); feature modifiers stay scoped.
- **Projects grid:** 3 cards per row driven by container queries hosted by `.projects-shell` (`container-type: inline-size`; `@container` 640px → 2 cols, 980px → 3 cols, 1 col below), so the layout is robust to `--layout-content`. The toolbar is sticky (`top: var(--header-height)`) with search + grid/list toggle and tag filter + sort; its inline overhang mirrors the container padding, so it bleeds to the viewport edges below 1084px and widens by `--space-xl` (32px) per side from 1084px so the cards' hover shadows (22px horizontal reach) stay hidden under it; tag pills are exactly `--input-height` tall, one line with horizontal overflow. `ProjectCard` caps tags via `tags_limit` (3 grid, 5 list) with a "+N" chip.
- **Tag filter:** unwraps the skill-search `{data: [...]}` payload, closes on a document-level outside click (suggestions use `@mousedown.prevent`), and projects with no image render a shared placeholder.
- **Project detail:** the "back to projects" button is removed; markdown element styles live in a **non-scoped** `<style>` block (v-html content never carries the Vue scope attribute) and the overview body uses a `div` (block output cannot nest in `<p>`); the Sources/Live Demo block (`.project-detail__bottom`) is two columns when both exist, one when one exists, hidden when neither; the lightbox stage uses a keyed directional fade+slide transition.
- **Card carousels** use compact dots (`compact_dots` → `carousel--compact-dots`, 9px with 8px padding); the detail gallery keeps the larger look. Carousel navigation is progressive: a single item renders **neither** arrow buttons **nor** dots (nothing to scroll), dots are hidden below 768px (too small for finger taps; the two arrow buttons already cover touch navigation), and the arrow buttons carry a 1px light border plus a subtle dark shadow so they stay legible over both dark and light imagery (a disabled arrow keeps its shape and only mutes its border and chevron). The no-image placeholder has no text label, a 5.25em photo icon at opacity 0.5, and is shared by the landing cards.
- **Search-autocomplete** rows show the date as `dd.mm.yyyy` with the localized `common.published` label, meta flush right and the title ellipsized.
- **Small screens (below 768px):** the projects index hides the grid/list switch, stacks the tag filter and sort on their own full-width rows, and adds a funnel ↔ chevron-up collapse toggle (`projects.show_filters`/`hide_filters`, `aria-expanded`/`aria-controls`, `projects-toolbar--filters-hidden`); the detail bottom block collapses to one column. The filter toolbar's inline overhang follows the container padding (16px, then 24px from 768px), so the bar spans the viewport up to 1083px without horizontal overflow, and from 1084px it widens by 32px per side to keep card shadows under it (the wide breakpoint is 1084px, not 1064px, because media queries include the scrollbar while the padded box needs 1064px of layout viewport).
- **Blog list/detail (step 9.4):** `ArticleReactions` owns its POST (fetch + XSRF header, emits `react` for analytics only); selected pills hydrate from the `tags` prop / first batch `skill_tags`; `ArticleContent` upgrades server-emitted code-block/callout markers by mounting real components (`h()`+`render()`, re-run on `content_html` changes); TOC extraction is the page's job (h2/h3 with ids via DOMParser); the year filter renders only when `years.length > 1` and its "All" sentinel `''` maps to `null`; the grid/list toggle is hidden below 768px; article meta dates use `Intl.DateTimeFormat`; search snippets carry sanitized `<mark>` rendered via `v-html`.

## Consequences

- Public visual decisions defer to the mockups when mockups and component specs disagree.
- The shared `.card` pattern is global; new card consumers must not redefine it locally.
- Projects list/detail responsive behavior is coupled to the `1084px` and `768px` breakpoints and to `.projects-shell` container queries.
- Blog rendering relies on server-sanitized HTML markers that the client upgrades in place.
- Carousel navigation visibility now depends on item count (single item shows no controls) and viewport width (dots only at `width >= 768px`); the arrow affordance (light border + dark shadow) is the shared legibility treatment for overlay controls on imagery.
