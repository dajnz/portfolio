# Lighthouse configuration

Performance-budget enforcement for the public site (architecture.md
"Performance Budget" §1197-1264, plan step 13.3).

## Files

- `budgets.json` — the budget table itself (gzipped-JS caps per page,
  mirroring the architecture.md:1203-1210 table — the single source of
  truth). Format is the Lighthouse budgets.json schema.
- `lighthouserc.cjs` — the LHCI config. It derives per-URL `assert`
  matrix entries from `budgets.json` (`resource-summary:script:size`),
  all at **warn** severity: the CI gate is non-blocking until the budgets
  are consistently met (architecture.md:1262-1263), then the matrix
  severity flips to `error`/blocking.
- `README.md` — this file.

## How to run

Requires the app to be served (frontend built + artisan serve or the
nginx stack) and the canonical public routes reachable from the Chrome
binary. The repo wraps this in `make lighthouse` (dedicated `portfolio_e2e`
database + `php artisan serve` on 127.0.0.1:8002 inside the dev
container, dev DB untouched):

```
make lighthouse
```

or against an already-running server:

```
LH_URLS=http://127.0.0.1:8002/,http://127.0.0.1:8002/blog npx lhci autorun --config=devops/lighthouse-config/lighthouserc.cjs
```

The GitHub Actions `lighthouse` job (non-blocking, `.github/workflows/ci.yml`)
mirrors the test-e2e environment and runs the same command with the demo
content URLs.

Local runs store the collected LHRs under `.lighthouseci/` (gitignored) —
the spot-check docs read the Core Web Vitals metrics from there.

## Caveats

- `php artisan serve` sends no `Content-Encoding: gzip`, so collected
  script sizes exceed the gzipped caps the budgets document (~3x). The
  production nginx stack gzips (devops/docker/nginx/default.conf), where
  the caps apply as documented. Treat CI/local `make lighthouse` numbers
  as upper-bound signals; the deltas are documented in the 13.3
  spot-check doc.
- Data-driven async chunks (icon packs fetched when the first icon
  renders) count toward the transferred script size and are visible in
  these runs; the Vite per-page budget warnings (vite.config.ts,
  `page-js-budget-warnings`) cover only the static chunk closure.
