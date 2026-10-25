# ADR-0038: Deployment image build correctness

- **Status:** Accepted
- **Date:** 2026-10-25
- **Source:** VM deployment acceptance test
- **Supersedes:** —

## Context

Tracing the first real end-to-end deployment exposed three defects in the
deploy path behind ADR-0028's baked-config decision:

- `build-php-images.sh` sourced the env file in the shell, but `docker build`
  does not inherit the caller's environment and the Dockerfile declared no
  matching build args. `config:cache` therefore froze defaults —
  `DB_CONNECTION=sqlite`, empty `APP_KEY` — and the deployment failed at the
  migration step. The `--env-file` on the migration container cannot override
  a cached config.
- No build step produced `public/build`. The Vite manifest is gitignored, so
  the PHP image had no manifest (500 on every page) and the nginx image had no
  `public/` tree at all (static 404s); the spec's claim that the deploy build
  ran `pnpm install` was not implemented.
- Migrations ran before `docker stack deploy`, so a fresh server had no `db`
  service to migrate against. Gating migrations on the app tag also left a
  retry after a failed deploy unable to converge.

## Decision

- The profile env file is passed to the PHP image build as a BuildKit secret
  (`--secret id=app_env,src=…`), copied to `.env` only for the cache step and
  removed before the runtime stage. `APP_ENV_HASH` is passed as a build arg and
  referenced in that `RUN`: BuildKit excludes secret contents from the layer
  cache key, so the hash is what reruns `config:cache` when only the env
  changed. Dev builds omit the secret and are unaffected.
- `devops/deploy/scripts/build-assets.sh` runs `pnpm install
  --frozen-lockfile && pnpm run build` with the pinned pnpm binary (a small
  builder image combines it with Node), writing `public/build` into the release
  directory before the image builds. The PHP image picks it up through its
  existing `COPY . .`; the nginx image copies `public/` with the repository
  root as its build context.
- The CI manifest job treats frontend changes as inputs to `app`, `queue`,
  `scheduler`, `node`, and `nginx`, because the Vite manifest and the hashed
  files are baked into those images.
- Migrations run before the stack update while `portfolio_db` exists
  (`migrate --force` is idempotent, which also converges retries), and after
  the stack deploy on a first deploy, waiting for `pg_isready` first. The
  migration container runs the new image on the pre-created, attachable
  `portfolio_prod` overlay network, which the stack now declares as its
  external default network (previously the stack used its own
  `portfolio_default` network while the migration container joined an unused,
  non-attachable `portfolio_prod`). Services scaled to zero count as converged
  in `wait-for-convergence.sh`.

## Consequences

- The baked config now carries the real runtime values; env-only changes
  rebuild the PHP image and force-restart the three PHP services, as ADR-0028
  intended.
- Every deploy that rebuilds the PHP or nginx image pays one client build; the
  release directory remains free of `node_modules`.
- Rebuild semantics are fully captured by the manifest tags: a frontend-only
  push refreshes all asset-bearing images.
