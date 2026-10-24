# ADR-0009: i18n & localization

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B9, B10, B28, B62, B102, B145, B147, B164, B169
- **Supersedes:** —

## Context

The portfolio is bilingual (EN/RU) with locale-aware content, admin editing of per-locale values, and a configurable set of active locales. Translation catalogs, ICU formatting, auth flash messages and the handling of disabled locales all needed consistent rules so that admin edits and the public surface agree on which locales exist and which translations survive.

## Decision

- **Catalog pipeline (B9):** a `lang:export` command plus a build-time `t()` key gate implemented as a Vite pre-build plugin.
- **Catalog authoring (B10):** the full `lang/{en,ru}` catalogs are authored once in Phase 2.6; the spec examples are illustrative only.
- **ICU formatting (B28):** an app-layer service `Shared\App\Services\IcuMessageFormatter` wraps the non-domain `\MessageFormatter::format()` (intl). It is not a global `icuf()` helper and not a domain class.
- **Auth flashes (B62):** localized via the default Laravel translation subsystem (`__('auth.*')`); minimal `lang/en/auth.php` + `lang/ru/auth.php` catalogs authored in 2.3, with the `TotpOrRecoveryCodeRule` message under `auth.code_format_invalid`.
- **About page props (B102):** `GET /admin/about` carries per-locale maps for `about.name/title/short_pitch/location` (`{en, ru}`), while the public landing `AboutData` keeps single locale-resolved strings; the same `AboutData` schema serves both.
- **Disabled locales (B145, B147):** a shared `supported_locales` prop (the ACTIVE codes from the `supported_locales` setting via the locale service) is exposed on `HandleInertiaRequests` and in the Inertia contract. Disabled locales are neither offered in the `LanguageSelector` nor shown as translation tabs anywhere, driven by `Shared/CrossSurface/Utils/localeOptions.ts` (codes → `{code, native_name}` with an en/ru fallback when the prop is absent). A disabled locale's existing translation rows survive updates untouched — the translation syncs delete only rows whose locale is ACTIVE (`whereIn('locale', $this->locales->activeLocales())`).
- **Locale service split (B164):** the settings-backed `LocaleService` was renamed `ActiveLocaleService` (it resolves active locales), and the duplicated per-locale projection helpers moved to `Shared\Infra\Services\PerLocaleValues` (`inputMap(array)` normalizes `{locale: mixed}` → `{locale: string|null}`, `mapPerLocale(array, callable)` maps a locale-keyed set).
- **Experience form props (B169):** `TimelineItemEditData` serves per-locale maps (`position`/`company_name`/`company_country`/`short_description` keyed by locale code, `achievements[].description` as per-locale nullable maps); `AchievementList`/`AchievementItem` edit per-locale description maps, rows carry a client-side `uid` SortableList key (stripped before submit), and the page drops fully-empty achievement rows before submit.

## Consequences

- Adding or enabling a locale is a data change (the `supported_locales` setting) that immediately affects selectors and tabs; disabled locales' translations are preserved rather than deleted, so re-enabling restores them.
- Per-locale editing surfaces must go through `PerLocaleValues`/entity projections instead of ad-hoc controller helpers.
- New admin forms exposing localized content should adopt per-locale maps to match the existing contract, rather than plain strings.
- Auth and app translations rely on real catalogs and the build-time `t()` key gate; missing keys fail the build rather than silently falling back.
