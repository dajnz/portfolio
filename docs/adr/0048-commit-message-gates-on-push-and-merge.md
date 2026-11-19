# ADR-0048: Commit-message gates on push and merge

- **Status:** Accepted
- **Date:** 2026-11-19
- **Source:** commit `e2e9c60` (`fix(seed): …`) reached `main` although `seed` is not an allowed commitlint scope: the commit was created with the hooks bypassed, the feature branch was merged locally without a pull request, and the next CI lint ran only after a 29-commit backlog had been pushed.
- **Supersedes:** ADR-0035

## Context

ADR-0035 added lefthook hooks: `pre-commit` (staged lint, typecheck, both suites), `commit-msg` (commitlint) and `pre-merge-commit` (full lint, both suites). The only commit-message check was `commit-msg`, which `LEFTHOOK=0`/`--no-verify` skips and which never sees commits created that way. A local non-fast-forward merge ran no message check over the branch it brought in, so the invalid message travelled with the merge. CI caught it on the push to `main`, after the commits were published.

Two constraints from ADR-0027/0035 still apply: tooling runs in the `dev` container while git runs on the host, and the container mounts only the main checkout, so linked worktrees cannot be checked locally.

## Decision

- **Carried forward from ADR-0035:** lefthook `^2.2.2` with the v2 `jobs` syntax, `piped: true` and `glob_matcher: doublestar`; hooks installed with `make lefthook`; `pre-commit` (staged lint, Spectral for OpenAPI changes, typecheck, Pest + Vitest), `commit-msg` (commitlint `--edit`) and `pre-merge-commit` (full-repo lint, both Spectral specs, typecheck, both suites); heavy jobs `skip: [rebase]`; every hook skips in linked worktrees; `devops/scripts/dev-exec.sh` is the single container indirection; `lefthook-local.yml` and `LEFTHOOK=0`/`--no-verify` are the documented escapes.
- **`pre-push` lints the commits a push introduces.** `.lefthook/pre-push/commitlint.sh` reads Git's pre-push ref lines and runs `commitlint --from <base> --to <ref>`. For a new ref the base is the parent of the oldest commit not on any remote; deletions are ignored. It is a lefthook *script* rather than a command because lefthook skips command jobs when a push changes no files, and an empty commit must still be checked.
- **`commit-msg` lints a merge's incoming range** with `commitlint --from HEAD --to MERGE_HEAD` (job skipped when `MERGE_HEAD` is absent) before linting the edited message. Git runs `pre-merge-commit` before writing `MERGE_HEAD`, so the merge-time range check lives in `commit-msg`; it covers clean merges and a conflicted merge finished with `git commit`.
- **`seed` is an allowed commitlint scope**, alongside the feature namespaces and tooling scopes; database seeding lives in `database/seeders`.
- **The manifest bot commit uses `chore(devops)`**; `chore(deploy)` was rejected by `scope-enum`, and `[skip ci]` meant CI never linted it.
- **CI validates the hook config** with `pnpm exec lefthook validate` in the lint job; the `skip: [rebase]` values use the schema-valid array form.

## Consequences

- Commit messages are checked at commit, push and local merge time, so bypassing `commit-msg` no longer carries an invalid message into `main` unnoticed; empty-commit pushes are covered as well.
- `pre-push` costs one commitlint run (~0.5 s) per pushed ref and needs the dev stack, like every other hook; only the documented escapes skip it.
- A locally merged branch is checked after `pre-merge-commit` has run the full suite, because Git invokes `commit-msg` only afterwards.
- The merge-time gate cannot see fast-forward, `--squash` or GitHub-side merges (the CI lint job remains the backstop), and `pre-push` is skipped in linked worktrees.
- CI lints only the range since the previous push or PR base, so a message that already reached `main` is never re-checked; only branch protection would make the CI check blocking.
