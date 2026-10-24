# ADR-0034: Node package management with pnpm

- **Status:** Accepted
- **Date:** 2026-10-22
- **Source:** — (maintenance decision; amends the npm usage described in ADR-0027)
- **Supersedes:** —

## Context

The front end and Node tooling (Vite, Vitest, Playwright, Biome, ESLint, Stylelint, Spectral, Lighthouse, icon scripts) installed dependencies with npm and a flat `package-lock.json`. npm's hoisted layout tolerates imports of packages the project never declares, and it duplicates files for every checkout and worktree.

We want faster installs, lower disk usage, a dependency graph that only exposes declared packages, and one explicitly pinned package manager.

The repo lives on `/dev/sdd1` (bind-mounted into the dev container) while `$HOME` is on `/dev/nvme0n1p2`. pnpm hard-links packages from its content-addressable store into `node_modules`; hard links require the same filesystem. The default per-user store would therefore be cross-device and pnpm would copy.

pnpm 12 reads only authentication and registry settings from `.npmrc`; all other settings live in `pnpm-workspace.yaml`.

## Decision

- pnpm 12.15.0 is pinned via `packageManager`; `pnpm-lock.yaml` is the only lockfile (`package-lock.json` is removed and gitignored).
- `pnpm-workspace.yaml` is the project settings file even though this is a single package: `storeDir: .pnpm-store`, `ignoreScripts: true`, and explicit `allowBuilds` denials for `@scarf/scarf` and `fsevents` (the only install scripts in the graph; no package needs a build).
- The store lives in the repo (`.pnpm-store`, gitignored and dockerignored) so it shares the bind-mounted filesystem with `node_modules`. It survives `node_modules` removal and container rebuilds; `pnpm store prune` is the cleanup.
- The isolated `node_modules` layout is intentional: every package the code imports must be declared. `@tiptap/core` (runtime) and `@iconify/types`, `@inertiajs/core`, `axe-core`, `vue-component-type-helpers` (types and tests) became explicit dependencies during the switch.
- All Node commands go through pnpm (`pnpm run`, `pnpm exec`, `pnpm dlx`). npm is removed from the dev image, so an accidental `npm install` fails instead of creating a second lockfile.
- Docker and CI install with `--frozen-lockfile`. The dev and SSR images copy the pnpm binary from `ghcr.io/pnpm/pnpm:12.15.0-alpine`; CI uses `pnpm/setup` with the version read from `packageManager`. Image builds pass `--store-dir` so the store is not baked into a workdir layer.
- The deployment change detector treats `pnpm-lock.yaml` and `pnpm-workspace.yaml` as Node changes.

## Consequences

- `node_modules` is a symlink farm into `node_modules/.pnpm`; only declared dependencies are resolvable. Phantom imports now fail at build or typecheck time.
- Hard links mean editing a file under `node_modules` edits the store copy shared by every checkout using it. `packageImportMethod: copy` restores isolation at the cost of disk and speed; not needed for normal workflows.
- Each git worktree keeps its own `.pnpm-store`. If that duplication becomes a problem, set `storeDir: ${PNPM_STORE_DIR:-.pnpm-store}` and point `PNPM_STORE_DIR` at a directory on the same disk shared by the worktrees (not enabled now).
- On Docker Desktop (macOS/Windows) the bind mount is virtiofs/gRPC-FUSE, where hard-linking is slow and a store on another filesystem (a named volume) is faster; such hosts can override the store the same way.
- The lockfile major version is coupled to the pinned pnpm; a major upgrade requires regenerating it and keeping the dev image, `packageManager` and CI in sync.
- The dev image no longer ships npm, so `npx` is unavailable; `pnpm exec` and `pnpm dlx` replace it in the Makefile, CI and docs.

## Alternatives considered

- npm (status quo): hoisted tree hides undeclared dependencies and duplicates files per checkout.
- Yarn Berry PnP: not viable with this Vite/Playwright/ESLint toolchain without a migration of its own.
- `nodeLinker: hoisted`: restores npm's implicit resolution, defeating the explicit-dependency goal.
- Default per-user store: cross-device on this host, so pnpm copies instead of hard-linking.
- Named Docker volume for the store: also cross-device from the bind mount, plus image ownership seeding.
- `node_modules/.pnpm-store`: pnpm's automatic fallback when the project is the only writable location; rejected because removing `node_modules` discards the cache.
