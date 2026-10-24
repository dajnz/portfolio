# ADR-0004: OpenAPI contract workflow & generated TypeScript types

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B1, B2, B3, B89, B161, B176, B180, B188, B189, B208, B211, B212, B224, B253, B268
- **Supersedes:** —

## Context

The REST and Inertia page-props contracts are shared between backend and frontend. The team needed a single workflow to change contracts, keep generated TypeScript in sync, and add missing fields without ad-hoc divergence when the committed schemas could not express required UI or behaviour.

## Decision

- **Committed generated types (B1):** generated `api.ts` and `inertia.ts` are committed so `pnpm run build` is self-contained; they are regenerated via `pnpm run typecheck`.
- **Contract change workflow (B89, B161, B176, B188, B208, B224, B253, B268):** a contract change adds a `CHANGELOG.md` entry, bumps `info.version`, then regenerates the types. Where the committed schema cannot express required behaviour it is extended as an explicit gap-fill (e.g. `TimelineItemTableRowData.include_in_resume`; `ProjectEditData` self-contained per-locale maps with `data-dynamic` keys; `ProjectDetailData.tech_writeup` resolved against the article's current publication state; `BlogListPageProps.years`; `has_projects` on the experience and blog pages; `ArticleCardData.skill_tags`/`lead_image_alt`). The `inertia.yaml` version advanced through these increments (1.0.4, 1.0.8, 1.0.10–1.0.12, 1.0.14–1.0.17) with `openapi.yaml` at 1.0.3.
- **Shared conventions (B2, B3):** admin blog list filtering reuses the public query `?article_tags=&skill_tags=&year=`; the `auth.user` shared prop is `{ id, name, email, login, github_linked, totp_enabled, is_oauth_primary }`.
- **Preview routes (B176, B188, B211):** signed 1-hour preview URLs are exposed as `preview_url`; the preview renders the public page with raw Markdown content, zeroed reactions and a minimal `noindex` SEO prop.
- **Mutation responses (B180, B189, B212):** quick actions (feature/publish/tag) return the full table-row shape so a row can be replaced without a reload; bulk + reorder endpoints return `{}` and the page refetches props; `POST /api/tags` responds 201 with `{id, slug, name}`.
- **Frontend baseline (B89):** `crypto.randomUUID()` is unavailable on non-secure contexts (e.g. `http://nginx`), so `Shared/CrossSurface/Utils/randomUuid.ts` is used by `useAnalytics` + `toastStore`; the `@auth` alias mirrors `@blog-shared` in `vite.config.ts` + `tsconfig.json`; new i18n keys are added to both catalogs and regenerated via `lang:export`.

## Consequences

- A backend mapping change must update the contract and regenerate the types in the same change; fields already present in the contract but unmapped (e.g. `skill_tags` in the REST `ArticleItem`) are treated as gap-fills, not left undocumented.
- New shared props and shapes are documented once and reused (e.g. the shared `SkillTagData` `{id, name, icon}` shape and `has_projects`), avoiding per-feature duplicates.
- Forgetting the version bump or CHANGELOG entry breaks the workflow, since the committed types are the build input.
