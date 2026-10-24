# ADR-0033: Console commands and queued jobs as thin entry points

- **Status:** Accepted
- **Date:** 2026-10-21
- **Source:** command/job use-case audit
- **Supersedes:** —

## Context

ADR-0001 requires controllers to be transport-only and to delegate every
operation to an app use case. Commands and queued jobs are entry points of
the same kind, but the rule was never stated for them. An audit found
business logic and persistence inside `admin:create`, `admin:reset-password`,
`IncrementArticleViewJob`, `CreateBackupJob`, and the two maintenance
commands, while several other commands/jobs were already thin shells.

## Decision

- Console commands (`app/Features/*/Infra/Commands`, `app/Console/Commands`)
  and queued jobs (`app/Features/*/Infra/Jobs`) are transport shells, like
  controllers. Every action delegates to an app use case.
- Commands and jobs must not import domain repository interfaces, Eloquent
  models, Infra repositories or other job classes. They must not call `DB`,
  `Storage`, `Bus` or `Config` facades directly. `Cache` and `Log` remain
  allowed in job failure hooks (the backup status cache is an infra concern).
- A command or job must declare at least one `*UseCase` dependency, either as
  a constructor property or a `handle()` parameter. Infra utility services
  that are not persistence/orchestration (for example the CLI
  `PasswordGenerator`) remain allowed.
- Build tooling is an explicit exception: `lang:export` stays a plain
  `app/Console/Commands` exporter because it has no domain behavior.
- The maintenance commands keep their behavior but move the orchestration to
  `PruneTrashedItemsUseCase` and `CleanupOrphanedFilesUseCase`. The
  query-builder work lives in Infra adapters behind
  `TrashedItemPrunerInterface` and `AssetPathProviderInterface`.
- ADR-0032 still applies: use cases never depend on other use cases; shared
  algorithms live in app services.

## Consequences

- New command/job behavior requires a use case (and, when persistence is
  involved, an app contract implemented on the Infra layer).
- Commands and jobs stay small enough to review as wiring: options/prompts,
  output formatting and delegation.
- The rule is enforced by
  `tests/unit/backend/Architecture/CommandJobLayerPurityTest.php`. The only
  entry-point allowlist is `app/Console/Commands/ExportLangCommand.php`.
- ADR-0012's maintenance-command behavior is unchanged from the outside; only
  its location moved.
