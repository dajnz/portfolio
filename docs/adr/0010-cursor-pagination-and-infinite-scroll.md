# ADR-0010: Cursor pagination & infinite scroll

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B72, B103, B158, B165
- **Supersedes:** —

## Context

Admin lists need stable keyset pagination with previous/next navigation, and public lists need infinite scroll with a reload-restore behaviour. The spec documented the admin cursor request/response fields and the public Load More semantics incompletely (no direction signal on the admin side; the public Load More and reload-restore sections were indistinguishable at the request level), so the wire format and the response superset rule had to be defined explicitly.

## Decision

- **Admin cursor direction (B72):** the admin request extends `?cursor[id]=&cursor[sort_value]=` with `cursor[before]=1` for previous-page requests, inverting the keyset comparison (`<` → `>` in desc, `>` → `<` in asc).
- **Admin `current_page` (B72):** derived server-side from the cursor's 1-based rank (index-only count of rows at-or-before the cursor: `(sort_col, id) >= (v, id)` for desc, `<=` for asc); `?page=N` jumps keep the documented keyset-positioning subquery. Sort state travels as `?sort_column=` / `?sort_direction=` (default `id` / `desc`; non-`[a-zA-Z0-9_.]` column names fall back to `id` with a warning). A cursor whose shape mismatches the active sort (id-sort cursor with a non-null `sort_value`, composite cursor without one, or a non-positive id) is logged as a warning and treated as a page-1 request. Cursor responses expose the requested `current_page` even for an empty page — no clamping.
- **Admin count cache (B72):** `Cache::remember('admin_cursor_count:{key}', 30)`; callers supply a per-filter-combo key (`null` disables caching).
- **Admin list consumers (B103):** `ListContactsUseCase` (`allowedSortColumns ['sort_order']`, id desc default) and `ListFeedbackUseCase` (id desc default) use `AdminCursorPaginator`; page props are `items` + `meta = {total, per_page, current_page, sort_column, sort_direction, cursors}` (the B72 cursor shape supersedes the committed `PaginationMeta` reference in the Inertia contract for admin lists only — no contract change).
- **Public infinite scroll (B158):** every response is a superset — the initial batch, then all batches keyed after the previously appended batch's last item while it sorts before the cursor, then one more batch keyed after the cursor itself. `next_cursor` is the final batch's last item when that batch is full, else `null` (exhaustion, no COUNT query). The client REPLACES its items with the response rather than appending. Malformed cursors yield the initial batch, never a 4xx.
- **Public cursor codec (B165):** the base64url `{id, v}` encode/decode lives in `Shared/Infra/Persistence/PublicCursorCodec` (reusing the `Cursor` VO). `CursorDecoder` is NOT reusable for public lists — it decodes the admin lists' structured `?cursor[id]=&cursor[sort_value]=` params against a `SortSpec`, a different wire format.

## Consequences

- Load More always advances and exhaustion is detected exactly without a COUNT query; reload restores the view possibly one batch over, which is why clients must replace rather than append.
- Admin and public pagination have deliberately separate cursor wire formats and decoders; do not attempt to unify them.
- The admin count cache is keyed per filter combination with a 30s TTL, so callers must compute a stable key or accept uncached counting.
- A mismatched or malformed cursor degrades gracefully (page-1 / initial batch) instead of erroring, which means invalid cursors never surface as client errors.
