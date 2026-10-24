# ADR-0032: Use-case composition & capability services

- **Status:** Accepted
- **Date:** 2026-10-20
- **Source:** refactor of the use-case dependency graph
- **Supersedes:** —

## Context

Use cases are the app layer's entry points for a single action. Several entry points needed the same algorithm — the daily metric catalog, TOTP verification with its backoff, the GitHub link conflict matrix — so one use case constructor-injected another. Because some of the pairs lived in the same namespace, import-based architecture guards never saw the coupling. In one case an app service (`BackupWorkflowService`) depended on a use case (`PruneBackupsUseCase`). The resulting graph made the shared algorithms hard to reuse and test in isolation and blurred the line between "a user action" and "a reusable capability".

## Decision

- A use case (`{Feature}/App/UseCases/`) may depend on: domain entities, value objects, enums and repository interfaces; app contracts (ports); app DTOs; and app services (`{Feature}/App/Services/`). It must not depend on another use case.
- An app service may depend on the same set **except** use cases.
- An algorithm or orchestration shared by more than one entry point moves into an app service. The use cases remain the named entry points and delegate; they never wrap one another.
- Extraction targets of this refactor: `DailyMetricsAggregator`, `MetricSeriesBuilder`, `MetricRankingsBuilder` and `DailyDigestBuilder` (Analytics); `TotpVerifier`, `LoginSessionService` and `GithubLinkService` (Auth); `BackupImporter` and `BackupPruner` (Backup).
- `VerifyTotpUseCase` and `ToggleFeatureFlagUseCase` were deleted: neither was an entry point (`TotpVerifier` is the capability; the feature-flag batch update is the only route), so they named no user action of their own.
- A pass-through use case is acceptable when it names a user action or owns a policy (`defaultDateRange()`, `attempt()`), not when it exists only to forward to another use case.
- The rule is enforced by `tests/unit/backend/Architecture/UseCaseCompositionTest.php`: it scans both directories token-wise, so same-namespace references are caught and docblock prose is ignored.

## Consequences

- New shared behaviour requires an app service, not a use-case dependency — the architecture test fails otherwise.
- Use-case constructors list capabilities, keeping the app-layer dependency graph acyclic and each algorithm testable through its service or the delegating entry point.
- Feature slices no longer import each other's use cases; cross-feature reuse goes through `Shared` services or contracts, as ADR-0001 already requires.
