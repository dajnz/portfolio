# ADR-0045: Public page performance — SSR enablement and page-data cache

- **Status:** Accepted
- **Date:** 2026-10-27
- **Source:** load test of the deployed stack on a 2 vCPU / 4 GB target (landing page plateaued at ~39 rps with Postgres at ~100 % of one core and 47 statements per request)
- **Supersedes:** —

## Context

The landing page executed 47 SQL statements and returned ~1 166 rows per anonymous request, so the database core saturated long before PHP or nginx did: app containers sat at ~42 % while `portfolio_db` ran at ~100 % of a core. The dashboard also showed that Inertia SSR never ran in any deployment: `inertia.ssr.ensure_bundle_exists` defaults to true, the probe looks for `bootstrap/ssr` **inside the PHP image** (only the node image builds the bundle), and `throw_on_error = false` turned the miss into a silent client-side fallback. The node SSR service itself answered correctly when called directly.

Settings and feature flags are cached in Redis, but the settings cache stored bare values, so a `null` value read back as a miss and was re-queried on every request; the settings table probe in `SettingsMailConfigProvider` ran `Schema::hasTable()` on the request path.

## Decision

- SSR stays the rendering mode. Rendering is delegated to the `node` service over HTTP, so deployed env files set `INERTIA_SSR_ENSURE_BUNDLE_EXISTS=false`; the bundle-existence probe is meaningless in the PHP image. Local development keeps SSR opt-in.
- Anonymous public page **props** are cached in the application cache through `PublicPageCache` behind `PUBLIC_PAGE_CACHE_ENABLED` (off in development and tests, on in the deployed env files) with `PUBLIC_PAGE_CACHE_TTL` (300 s). It wraps the landing page, the blog index, the projects index, the experience index, the DB-backed shared Inertia props (owner name, timezone, active locales, feature flags, console greeting, `items_per_page`), and the merged mail configuration. Query-string requests bypass the page caches so filters, search and cursor pagination never accumulate keys.
- Cache keys carry a version token; every successful admin mutation bumps it from `LogAdminMutationsMiddleware`, which already sees all admin writes. Edits are therefore visible immediately and abandoned keys expire by TTL.
- The settings cache stores values wrapped in an array and uses an hour-long TTL, missing rows are still never cached, the settings-table probe is memoized per provider instance, and the feature-flag TTL matches the settings one.
- HTTP-level caching (nginx `fastcgi_cache` or a CDN) is deliberately not enabled: it would require stripping or merging session/CSRF cookies, keying on the locale cookie, and keeping the CSP nonce consistent with cached markup.

## Consequences

- The landing page issues roughly 45 fewer statements per request on a cache hit, so the database stops being the throughput ceiling; cacheable content becomes stale only until the next admin mutation or the TTL.
- Every cache hit still pays the framework boot and the Redis read, so the gain is bounded by PHP startup rather than by Postgres.
- SSR adds one internal HTTP request and a Vue render to every page render; the node service has two single-process replicas, which is the next ceiling to watch.
- The props cache stores scalars only (no domain entities), so the framework's cache unserialize whitelist stays untouched.
