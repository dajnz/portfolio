# ADR-0026: Client state, cookies, SSR theming & CSP

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B12, B73, B74, B75, B76, B92, B325, B326
- **Supersedes:** —

## Context

The public surface renders first on the server (Inertia SSR) while theme and locale are written client-side, and a nonce-based Content-Security-Policy is active in production. These interact in ways the spec left open — inline SSR page-data, plaintext client cookies, lazily registered icon subsets, and Tailwind's CSS layering — so explicit rules were needed to keep SSR theming, icons and CSP working.

## Decision

- `theme` and `locale` cookies are **excluded from cookie encryption** in `bootstrap/app.php` (`$middleware->encryptCookies(except: ['theme', 'locale'])`): they are written client-side via `document.cookie` and read raw by `ThemeMiddleware`/`SetLocaleMiddleware`, and Laravel 13's `EncryptCookies` would otherwise null the plaintext values. Cookie tests use `withUnencryptedCookie()` (not `withCookie()`).
- Inertia SSR needs **no nonce propagation**: the nonce-less `<script data-page="app" type="application/json">` page-data block is not subject to `script-src`. Verified live with the real CSP over 8 URLs with zero violations (Step 13.3); nonce presence must be read from raw SSR HTML because Chromium hides the attribute from the serialized DOM.
- `useAnalytics` (registered once in `app.vue`): session UUID in `sessionStorage`, `MAX_BUFFER_SIZE` 80, a 45 KB byte cap dropping the newest events, 30 s flush, keepalive flush on `beforeunload`/`visibilitychange`, `page_view` + `time_on_page` via `router.on('start'/'finish')`, teardown via the unsubscribe functions returned by `router.on()` (Inertia 3 has no `router.off()`), SSR-safe no-op when `typeof window === 'undefined'`, silent fetch-failure handling.
- `usePrefetch` keeps its `AbortController` API and bridges cancellation to Inertia 3 via `onCancelToken(cancelToken)` → `cancelToken.cancel()` (`VisitOptions` has no `signal`).
- `useUnsavedChanges` uses `import isEqual from 'lodash-es/isEqual'` (per-method import, tree-shaken); `lodash-es` is a dependency and `@types/lodash-es` a dev dependency.
- `app.css` imports the design tokens with a layer: `@import './public-tokens.css' layer(base)` after `@import 'tailwindcss'`. Without the `layer()`, Tailwind v4 emits the imported reset unlayered and it silently overrides every `p-*`/`m-*` spacing utility.
- Iconify icons are registered from committed local subsets (`iconManifests/*.json`) before render: a shared `useDeviconIcons` readiness store (Shared/CrossSurface) gates `TechSkillTag` glyphs, and per-feature readiness gating handles the projects "Sources"/landing-CTA simple-icons GitHub glyphs.

## Consequences

- Any icon name stored in the DB must exist in a registered subset; hand-seeded data bypasses the icon-picker constraint and renders blank under production CSP.
- `@import ... layer()` depends on CSS-layer import syntax (LightningCSS + browsers).
- Known budgets/residuals are recorded in `docs/spot-checks/13.3.md`: devicon/simple-icons subsets push real gzipped JS to ~270–285 KB vs the 150–200 KB caps; markdown body images stay dimension-less; SSR'd CLS 0.2–0.5 under simulated throttling from post-hydration glyph/image insertion.
