# ADR-0024: Admin UI interaction conventions

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B19, B135, B136, B140, B153, B154, B168, B170, B172, B174, B175, B230, B231, B232, B233, B315
- **Supersedes:** —

## Context

The admin surface has no visual mockup to settle interaction disputes, so its conventions were fixed through implementation reviews. These rules standardize keyboard behavior, inline editing, lists, dates, forms, the rich-text editor and the responsive sidebar for every future admin feature.

## Decision

- **Keyboard shortcuts:** `Ctrl+Enter` submits the current form, `Escape` closes modals/cancels editing, `/` on list pages focuses the search input.
- **Inline editors (skills/groups):** blur-save is removed. Editors exit only via click-outside, `Escape`, or explicit Cancel; `Enter` still saves. Click-outside uses a capture-phase `pointerdown` document listener whose containment includes teleported icon-picker panels; capture ordering makes a click that opens a new editor first close the previous one. Clicking any viewing skill row (or the group header) opens its editor.
- **Reorder/a11y:** Move up/down buttons are disabled at the top/bottom of an item's own list (`aria-disabled` + `disabled`); cross-group moves are drag-only; groups get the same buttons; reorder posts the full flattened payload with an optimistic swap.
- **SortableList:** the `props.modelValue` watcher resets all preview state (`previewActive`/`previewIndex`/`previewItemKey`), and `clearPreview` removes the preview **by key**, not by a possibly stale index.
- **AdminBaseModal:** the body block renders only when the default slot actually produces content (inspects vnodes, filtering `Comment`).
- **Experience list:** no bulk actions (routes define no bulk endpoints) — per-row Restore / Delete Permanently only, no selection checkboxes, no bulk-actions slot; `trashed_ids` remains for muted trashed styling; the Edit button is removed and a row click navigates to `/admin/experience/{id}/edit` (trashed rows do not navigate); row-action wrappers use `@click.stop`.
- **DataTable:** emits snake_case events (`update:sort_column`, `update:sort_direction`, `update:selected_ids`) — consumers must bind the exact snake names (`@update:sort_column`); sorting cycles asc → desc only. `AdminCursorPaginator::invalidateCountCache('experience'|'contacts'|'feedback')` runs on every write use case; a `?page=N` beyond the last page redirects to the last valid page with filters preserved.
- **Dates:** admin lists show full `dd.mm.yyyy` (`15.01.2023 — Present`); `AdminDatePicker` is a read-only field always displaying `dd.mm.yyyy`, with a Monday-first calendar popup (Escape/Arrows/Enter, outside-click close) and a clear button; the ISO `modelValue` contract is unchanged.
- **Experience continuity:** `ended_at > started_at`; no overlaps; only the chronologically last item may be ongoing; appending after an ongoing item auto-fills its `ended_at`; empty `ended_at` is the ongoing marker (no `is_current` field); same-start items are ordered by the `(started_at, id)` tuple. Violations surface as `started_at`/`ended_at` validation errors.
- **Forms/layout:** sticky header controls are mandatory via the shared `AdminStickyActionBar`; list-row delete buttons use `icon-outline-danger`; the "Cancel" button is removed from experience and blog edit forms (the back link replaces it); the unsaved-changes guard baselines a deep copy of the same built form. Nested array-field errors (`related_articles.N`/`tags.N`/`skill_tags.N`) surface via `nestedFieldError()`; the update rule waives the published requirement for already-linked related articles.
- **Shared utilities:** `admin-autocomplete-panel` styles autocomplete surfaces; `useFocusManagement` skips the h1 focus steal when an input is active; sidebar active state matches exact route or prefix (the dashboard link is exact-match only); the tech-tags picker's outside click also clears the query.
- **TextEditor:** a single non-wrapping `h-8 w-8` toolbar row (`overflow-x-auto`) grouped into block/inline/script/code/media; option controls live in one shared popup (`activePopup`) anchored to the clicked button and closed by outside click, `Escape`, same-button click, or applying an option — never by editor blur. Supports underline/strikethrough, sub/superscript (`<sub>`/`<sup>`), GFM tables, hyperlinks, callouts, and selectable H1–H6. Callout config uses top-level `markdownTokenName`/`parseMarkdown`/`renderMarkdown` fields (a nested `markdown` block is ignored at runtime) and renders through `CalloutNodeView` via `VueNodeViewRenderer`, round-tripping `> [!TYPE]` markers. Image drag-resize is an editor-session aid only (width is dropped on the markdown round trip); the placeholder uses `isDocumentEmpty`; the toolbar is sticky at `top-[133px]`.
- **Sidebar (mobile):** the icons-only collapse mode is removed. Below `lg` the single `<aside id="admin-mobile-menu">` is a fixed full-width overlay under the 64px header at `z-overlay` (250); at `lg+` the same element is the in-flow `w-64` sticky rail. Open state is page-local (`mobileMenuOpen`, not persisted) and closes on hamburger toggle, nav-item click, `Escape`, and Inertia navigation `start`; a `lg:hidden` Logout row is last; no open/close animation.
- **i18n:** `resources/js/lang/*.json` are generated artifacts — new keys go into `lang/{en,ru}/*.php` and reach the client via `php artisan lang:export`.

## Consequences

- New inline editors must adopt the click-outside/Escape/Cancel exit model and must not save on blur.
- New list pages must bind DataTable's exact snake_case emit names and invalidate the per-entity count cache on writes.
- Rich-text behavior changes must keep the SSR/public markdown pipeline in parity with the editor.
- The desktop and mobile sidebar share one nav markup; pages must not reintroduce a persisted collapse key.
