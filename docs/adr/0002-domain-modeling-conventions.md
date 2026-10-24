# ADR-0002: Domain modeling, entities, value objects & DTOs

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B48, B130, B131, B132, B162, B163, B166, B183, B187, B207, B213
- **Supersedes:** —

## Context

The domain needed consistent modelling conventions: how entities hide state, where invariants live, when a type is a value object versus an entity, and how data crosses the app-layer boundary. Ad-hoc DTOs, public properties and framework-tinged types caused duplicated validation and leaky boundaries.

## Decision

- **Entities (B48, B187):** private properties with `toXXX()` getters, extending `BaseEntity` (`toId(): int`, `equals()`); invariants are enforced in the constructor via dedicated methods. Entities are mutable via state-changing methods (`Project::publish()`/`unpublish()` mutate publication state after asserting the invariants) while embedded VOs stay immutable.
- **Value objects (B48, B130, B131, B183, B207):** immutable, identity-free, under `Domain/ValueObjects/`. Examples: `WorkType` (`remote|hybrid|office`), `StoredPicture`, `SortOrder` (≥0), `ProficiencyLevel` (1–5), `Slug` (kebab validation + EN/RU generation with Cyrillic→latin transliteration), `SkillGroupVo`, `PublishedState` (Shared, first-publish `published_at` semantics shared by projects and blog) and the Projects-specific `EngineeringDetailIcon`. Icons are split per kind: `SkillIcon` (devicon), `SkillGroupIcon` (heroicons), `ContactIcon`.
- **Read-model entities (B48, B130, B131):** `TechSkill`/`TechSkillGroup` are full domain entities serving both read and write surfaces (`GroupedTechSkillsDTO` removed). Per-locale group names are `TechSkillGroupName[]` with `nameFor($locale)`, exposed as `groupNames`/`toGroupNames()` (no `translations` naming).
- **Light skill representation (B163, B166):** `TechSkillTag` (`Shared/Domain/Entities`, `{id, name, icon}`) is reused by Experience and Projects; `FeaturedProjectTechTag` was merged into it.
- **DTOs (B185, B213):** app-layer, immutable, composed data structures named by the data they hold, with no page/form/request/row/json words — `ProjectDraftDto` (input) vs `ProjectDataDto` (stored), composed of `ProjectTranslationsDto`/`ProjectImageDto`/`ProjectScreenshotDto`/`ProjectEngineeringDetailDto`/`ProjectLinksDto`; `PaginationDto`/`CursorPaginationDto` + `PaginationBuilder` replace the abstract `meta`. Use-case-result DTOs are the app-layer data-flow mechanism and must not be replaced by entities or raw rows.
- **Uniqueness (B130, B132):** uniqueness is a persistence probe, not a shared service — `TechSkillWriteRepositoryInterface::skillNameExists($name, ?$ignoreId)` (case-insensitive `lower(name) = ?`); the use case throws `DuplicateSkillNameException` and the controller converts it to a `name` `ValidationException`.
- **Established validation rules (B162, B163):** `short_description` is the only required translatable TimelineItem field (NOT NULL column); the position/company/country identity trio is all-or-none per locale (empty country normalises to null); `RelatedProject` is ownProjectId XOR externalProjectName.
- **Tests (B132):** value objects use per-class test files (`SkillIconTest`, `SortOrderTest`, `ContactIconTest`, `TechSkillGroupNameTest`, `SkillGroupVoTest`).

## Consequences

- New domain types must choose entity vs VO and hold their own invariants; entity properties are never public/readonly.
- Controllers and repositories must map entities to DTOs at the boundary rather than leak entities outward.
- Cross-feature reuse requires promoting the type to `Shared` (as `PublishedState` was moved out of Projects so Blog does not import the Projects domain).
