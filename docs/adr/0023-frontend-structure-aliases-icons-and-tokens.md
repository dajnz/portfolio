# ADR-0023: Frontend structure, aliases, icons & tokens

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B77, B78, B80, B83, B84, B85, B120, B133, B137, B138, B237, B274, C15, C16
- **Supersedes:** —

## Context

The Vue 3 front end is shared across public (BEM) and admin (Tailwind) surfaces and must stay self-hosted with no third-party trackers. Decisions were needed on the component layout, module aliases, icon delivery and design-token mirroring before the feature work began.

## Decision

- **Directory convention:** all Vue components live in `{Feature}/{Area}/Components/`; auxiliary modules live in sibling dirs (e.g. `Shared/Admin/EditorExtensions/`). Pages live in `{Feature}/{Area}/Pages/` (Inertia glob resolution); shared-feature dirs (`@blog-shared`, `@experience-shared`, `@shared-cross`) stay at their feature root. `Shared/Admin/*.vue` and `Shared/Public/*.vue` were moved into `Components/`.
- **Aliases:** registered in both `vite.config.ts` and `tsconfig.json`. All 19 feature keys use the `/*` wildcard (`@feature-x/*`) — keys without it are exact-match in TypeScript and never resolve. A root `@/*` alias (`resources/js/*`) covers non-feature imports; `@techskills` was added alongside `@portfolio`/`@settings`.
- **Contract casing:** props and events use snake_case verbatim per the committed component contracts; eslint `vue/prop-name-casing` is disabled. `AdminIconPicker`'s prop is `icon_set` — Vue maps only kebab-case to camelCase, so a camelCase prop silently kept its default.
- **Offline icons (no runtime fetches):** `resources/js/iconRegistry.ts` registers collections via `addCollection` at both app entries (`app.ts`, `ssr.ts`); `@iconify/vue` would otherwise fetch from the Iconify API at runtime. Registrations must be synchronous before first render (static import, not page-level async dynamic import) — a race causes runtime API calls.
- **Strict icon subsets:** the app ships no whole `@iconify-json` packs. Two manifests — `resources/js/iconManifests/brands.json` (devicon) and `technical.json` (heroicons chrome + simple-icons socials + lucide sub/superscript) — list every allowed icon as `set:name`. `pnpm run icons:build` (`devops/assets/build-icon-subsets.mjs`, wired as the `pnpm run build` pre-step) validates names against node_modules, resolves alias chains, and writes committed `resources/js/iconSubsets/{prefix}.json`. A coverage guard (build + `tests/unit/frontend/Shared/iconManifests.spec.ts`) fails when a used `set:name` is missing. `@iconify-json/*` are devDependencies.
- `AdminIconPicker` is restricted to the manifests with two modes: `brands` (devicon — skills) and `technical` (flat mixed list — groups/contacts). Skill pickers use `brands`/`devicon` (the domain `SkillIcon` VO accepts only `devicon:*`); group pickers use `ui`/heroicons.
- `@iconify/vue`'s offline `storage` is a plain object, so a lazy `addCollection` does **not** trigger re-renders and unregistered names render empty. Consumers guard on a reactive `iconsReady` ref (e.g. `Portfolio/Public/iconRegistration.ts`) and render `<Icon>` only once it flips; SSR renders icons empty and the client re-renders after hydration.
- **Tokens:** `resources/css/public-tokens.css` mirrors `docs/mockups/public/tokens.css` minus the Google Fonts `@import` (fonts are Vite-bundled via the bunny plugin); `tech-stack-tiles.svg` is copied so `.gradient-hero` resolves. Media-query `var()` is replaced with the literal `768px` (lightningcss rejects `var()` in media queries); `-webkit-backdrop-filter` prefixes are dropped.
- **Tailwind 4 admin:** the mockup CommonJS preset cannot be imported by this `"type": "module"` project, so `tailwind.config.js` inlines the Ledger theme verbatim; `darkMode: 'class'` is inert in v4 JS configs — the class dark variant is declared via `@custom-variant dark` in `app.css`. Admin uses bundled Inter + JetBrains Mono through the `font-admin` utility (`--font-admin` in `@theme`); the public Instrument Sans face is untouched. Mockup palette gaps (`danger-300/900/950`, `success-400/950`, `warning-400/950`) are kept as verbatim no-op classes.
- `PublicPageHeader`/`PublicPageLayout`/`AdminPageHeader` expose a `controls` slot for ThemeSwitch/LanguageSelector; `PublicPageLayout` renders `ConsoleGreeting` only when a `console_greeting` prop is passed. Admin contract extensions: `AdminBaseInput` supports `url`/`number`/`date`, `TextEditor` gained `is_loading`, `AdminPageSidebar` an optional `dashboard` prop.

## Consequences

- New icons must be added to a manifest and regenerated; unlisted names fail the guard (or silently trigger a runtime API fetch if the guard is bypassed).
- New aliases must be added to both Vite and tsconfig with the `/*` wildcard.
- Prop/event names stay snake_case; template listeners must match the emitted casing exactly.
- Admin preset tokens are inlined rather than imported, so mockup/preset changes must be mirrored manually.
