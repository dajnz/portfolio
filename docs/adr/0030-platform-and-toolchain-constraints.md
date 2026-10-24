# ADR-0030: Platform & toolchain constraints

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B104, B177, B178, B205, B209, B210, B218, C1, C2, C3, C4, C5, C11, C13
- **Supersedes:** —

## Context

The stack pins PHP 8.5 / Laravel 13 / Vue 3 and a specific front-end toolchain. Several framework and toolchain behaviours differ from the spec's assumptions, and composite-primary-key plus soft-delete models required query-builder workarounds. These constraints govern how domain, persistence and tooling code must be written.

## Decision

- Composite-PK translation models (`EloquentContactTranslationRepository`, `EloquentAboutInfoTranslationRepository`) sync via query-builder `updateOrInsert`; Eloquent `updateOrCreate` throws because `getKeyForSaveQuery()` indexes `original` with an array key.
- Instance update/delete on composite-PK models crashes (`Cannot access offset of type array on array`), so translation syncs use delete+insert; pivot `deleted` events are impossible, so `tech_tags_count` decrements are explicit query-builder decrements.
- `parent::forceDelete()` inside a `SoftDeletes` model resolves to `Model::forceDelete()` and silently soft-deletes; invoke the trait's flow via an alias (`use SoftDeletes { forceDelete as protected softDeletesForceDelete; }`).
- `whereNotIn` with an empty array evaluates to `1 = 1`; reconciliation captures the pre-existing id set before upserts and diffs against it.
- In Laravel 13 `where($col, $array)` binds a single placeholder, not `whereIn(...)` — use an explicit `whereIn`. Route parameters resolve positionally in controllers, so read them by name via `$request->route('project')`. FormRequests validate route parameters by merging them in `prepareForValidation()`.
- `reading_time_minutes` is `ceil(word_count / 200)` over the `content` field only, computed by the domain `ReadingTime` VO at save time; `search_vector` (`title || ' ' || short_description || ' ' || content`) is rebuilt in the same `saved` event / batch UPDATE.
- `not_bot_user_agent_pattern` uses an `env(...) ?: '(default)'` falsy fallback so an accidentally-empty env value cannot disable the blocklist.
- `composer.json` pins `"php": "8.5.*"` (the spec's `"8.5"` is an exact 8.5.0 match), and `inertiajs/inertia-laravel` v3.3.0 is required for Inertia page responses and `assertInertia`.
- Front-end: the Tiptap 3 markdown package is `@tiptap/markdown` (3.29.2), not `@tiptap/extension-markdown`; TypeScript is pinned to `^5.9` (TypeScript 7 is blocked by `openapi-typescript@7`'s peer range); Vite 8 (rolldown) needs `manualChunks` as a function (the `vue-vendor` chunk uses the `id.includes(...)` form).
- `biome.json` follows the Biome 2.5.6 schema (`files.includes`, `semicolons`, `quoteStyle`, `useSingleVarDeclarator`); generated `Types/*.ts` are excluded via `overrides`, and `overrides[].assist` must not be set.
- Laravel 13 has no `Blueprint::check()`; CHECK constraints are added with raw `DB::statement()` after `Schema::create` under explicit short names (`about_info_availability_status_check`, `about_info_work_type_check`, `skills_proficiency_check`), and native `enum` columns are avoided in favour of varchar+CHECK.

## Consequences

- Domain/persistence code must use the query builder (not Eloquent instance mutation) for composite-PK translations and an explicit `whereIn` throughout.
- Tooling versions are deliberately pinned; bumping them (TypeScript 7, `openapi-typescript`) requires verifying peer ranges first.
