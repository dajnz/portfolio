# ADR-0035: Local git hooks with lefthook

- **Status:** Accepted
- **Date:** 2026-10-23
- **Source:** — (maintenance decision; supplements ADR-0027 and ADR-0034)
- **Supersedes:** —

## Context

The repository had no client-side git hooks: `.git/hooks` contained only samples, and the quality gate lived entirely in CI (`lint`, `typecheck`, `test-backend`, `test-frontend`). A commit, and especially a merge into `main`, could therefore reach CI broken even though the local suites are fast (~12 s Pest, ~10 s Vitest, ~10 s typecheck).

Two constraints shape the implementation. All PHP/Node tooling runs in the `dev` container (ADR-0027), while git runs on the host; and the container bind-mounts only the main checkout (`.:/var/www/html`), so linked git worktrees would be checked against the wrong tree. `pnpm-workspace.yaml` sets `ignoreScripts: true` (ADR-0034), so dependency install scripts never run.

## Decision

- `lefthook` (pinned `^2.2.2`, `min_version` enforced) is a devDependency; `lefthook.yml` uses the v2 `jobs` syntax with `glob_matcher: doublestar` and `piped: true` (stop at the first failing job).
- Hooks:
  - `pre-commit` — staged-file lint (Pint, Biome, ESLint, Stylelint), Spectral when `docs/openapi/**` is staged, `pnpm run typecheck`, full Pest + Vitest. Heavy jobs carry `skip: rebase`.
  - `commit-msg` — commitlint (`--edit {1}`).
  - `pre-merge-commit` — full-repo lint, both Spectral specs, typecheck and both test suites.
- E2E, coverage thresholds and Lighthouse stay CI-only: they need servers/browser and minutes, not seconds.
- `devops/scripts/dev-exec.sh` is the single indirection for the container split: it execs the command directly under `/.dockerenv`, otherwise `docker compose exec -T dev "$@"` (`-T` because hooks have no TTY). It makes commits from `make open-dev` work as well as host commits.
- Hooks are installed explicitly with `make lefthook` (`pnpm exec lefthook install`), invoked from `make setup` and `make install`. There is deliberately no pnpm `prepare` script: CI runs `pnpm install --frozen-lockfile`, and installing hooks there would try to invoke Docker. `ignoreScripts: true` also prevents the package's own postinstall from installing them implicitly.
- The generated hook launches lefthook via `node_modules/lefthook/bin/index.js` (pnpm does not hoist the platform package to the root `node_modules`), so Node.js is required on the host for commits; the checks themselves still run in the container.
- Every hook skips when `git rev-parse --git-dir` differs from `git rev-parse --git-common-dir`, i.e. in linked worktrees, where the container would inspect the main checkout instead.
- `merge` is added to commitlint's `type-enum`: the AGENTS merge convention (`merge: <branch> into main`) was previously rejected by commitlint, which also made the CI lint job fail on merge commits pushed to `main`.
- `lefthook-local.yml` is gitignored for personal overrides. `LEFTHOOK=0` and `git commit --no-verify` are the documented escapes.

## Consequences

- A commit costs ~40 s (staged lint + typecheck + both suites); a merge costs ~90 s (full lint + specs + typecheck + both suites).
- Hooks are convenience, not enforcement: they can be bypassed and only exist after `make lefthook`. CI remains the authoritative gate and runs a superset (plus e2e, coverage and Lighthouse).
- `pre-merge-commit` fires only for non-fast-forward merges created locally; fast-forward merges, GitHub-side merges and squash merges bypass it. A merge stopped on conflicts is finished with `git commit`, which runs `pre-commit` — so the tests are covered there.
- Worktree commits are unchecked locally (documented in devops.md); supporting them would require mounting the worktree into a container (`docker compose run --rm -v "$PWD":/var/www/html dev …`).
- Renaming a hook or changing `lefthook.yml` is picked up automatically on the next hook run (`sync hooks`); a fresh clone needs `make lefthook` once.
