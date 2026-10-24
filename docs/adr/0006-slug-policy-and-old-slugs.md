# ADR-0006: Slug policy, old-slug 301s & trash occupancy

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A3, B5, B6, B29
- **Supersedes:** —

## Context

Articles and projects are addressed by a human-readable slug, so URL stability, slug regeneration rules, redirects for renamed content, and interaction with soft deletes all needed a single consistent policy. Implicit route binding (`{article:slug}` / `{project:slug}`) cannot perform a fallback lookup, so the redirect behaviour needed a deliberate design.

## Decision

- **Slug rule (A3):** if the submitted slug equals the currently stored slug, regenerate it from the EN title (with a numeric collision suffix); if it differs, treat it as a manual override and keep it.
- **Old-slug lookup (B5):** the `{article:slug}` / `{project:slug}` 301 fallback is resolved in the controllers (main table → `old_slugs` → 301), because implicit route binding cannot do the fallback.
- **Old-slug recording (B6):** record into `old_slugs` on any slug change (published or not) and on force-delete.
- **Trash occupancy (B29):** soft-deleted entities occupy their slug — no slug reuse. Restore re-checks slug uniqueness against non-trashed entities.

## Consequences

- Any slug edit permanently produces a redirect source; the `old_slugs` table grows monotonically and must not be pruned without breaking historical URLs.
- Slugs cannot be recycled while the trashed row persists, so restoring content is safe but a new entity cannot claim a deleted entity's slug.
- Restore can fail (or must re-resolve) when the slug has become occupied by a non-trashed entity, since uniqueness is re-checked against non-trashed rows only.
- Redirect resolution lives in controllers, not in the routing layer, so any new controller exposing slug-addressed content must repeat the fallback logic.
