# ADR-0003: Cross-feature reads through Shared contracts

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B11
- **Supersedes:** —

## Context

The spec did not define how one feature reads another feature's data. Landing featured projects need data owned by the Projects feature, and unauthorised cross-feature class imports would couple slices together.

## Decision

- Cross-feature reads go through contracts in `Shared/Domain/RepositoryInterfaces/`, implemented by Eloquent classes in `Shared/Infra`, mirroring the existing TechSkill read pattern. Landing featured projects use `ProjectReadRepositoryInterface`.
- Where a consuming feature can express the read as a query-builder read inside its own Infra layer, it does so directly instead; this applies to dashboard stats and the project tech-writeup publication check.
- No cross-feature class imports: a feature never imports another feature's domain or Infra types.

## Consequences

- Feature slices stay isolated; shared read contracts are the only seam between them.
- A read that serves more than one feature belongs in `Shared/Domain/RepositoryInterfaces/`, not in a sibling feature.
- Query-builder reads embedded in a consuming feature's Infra layer must remain free of cross-feature class references.
