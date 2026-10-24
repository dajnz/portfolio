# ADR-0011: Settings & feature flags

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A8, B52, B118, C14
- **Supersedes:** —

## Context

The spec required settings and feature flags to be persisted, typed, bounded and cached, but only mandated type validation and left caching details and numeric bounds to the implementation. Laravel 13's cache unserialize whitelist also conflicts with caching rich domain objects, so a decision was needed on where validation lives and how values survive the cache round-trip.

## Decision

- Settings and feature flags are real domain objects, not DTOs.
- `SettingName` enum holds the 21 predefined keys, each mapping to its allowed `SettingType` (strict name→type mapping); unknown keys are rejected via the enum.
- `SettingType` enum: `string|boolean|integer|json`; owns `isValidValue()` and raw parse/serialize.
- `SettingValue` VO: immutable; constructor-enforced type conformance (null string input normalizes to `''`; numeric strings such as `'12'` canonicalize to `int 12`); provides `equals()`, `fromRaw()`/`toRaw()`, `asBool()`/`asInt()`/`asString()`/`asArray()`.
- `Setting` entity holds name + value only (no type property); the constructor rejects value-type mismatch and enforces per-name value domains, so every `Setting` is valid by construction (persistence and reads included). `updateValue()` restricts changes to the name's type; `isValidName()`, `isBoolean()`/`isString()`/`isInteger()`/`isJson()`.
- Numeric bounds are domain rules: per-page settings and `backup_max_count` use `min:1`; `trash_retention_days` uses `min:0` (0 disables pruning); report frequency is a constrained enum.
- `FeatureFlagName` enum (`github_oauth`, `social_reactions`, `console_greeting`) + `FeatureFlag` entity (name/enabled/description, 500-char description limit, `isValidName()`).
- `SettingsService` is removed. Caching lives in `EloquentSettingRepository` under key `settings:{key}` with a 1-min TTL; `find()` caches hits only, `save()`/`saveValue()` forget, plus a convenience `value(name, default)`.
- The cache stores the typed value (plain data), not the entity: Laravel 13's `cache.serializable_classes` (scaffold default `false`) turns arbitrary objects into `__PHP_Incomplete_Class`; entities are rebuilt from name + value on read.
- Feature-flag cache key is `feature_flag:{key}` (1-min TTL); flag writes go through `EloquentFeatureFlagRepository::update()` (cache-invalidating), and raw model writes bypass the cache.
- `TypeValidateRule` is removed. `UpdateSettingsRequest::after()` only checks key validity (enum + `exists:settings,key`) and constructs the domain objects, surfacing `"Invalid value type for '{key}'."` for type conformance failures and the entity's message for domain violations.
- The DB `type` column is still written (name-derived) for schema fidelity and backup exports.

## Consequences

- Adding a setting means adding a `SettingName` case + type mapping; value bounds belong in the `Setting` constructor, not request rules.
- Cached values must survive a type round-trip; `SettingValue` canonicalization prevents errors like the `AdminCursorPaginator::paginate()` 500 caused by Redis returning numeric strings.
- `cache.serializable_classes` stays at its default `false`; never cache `Setting`/`FeatureFlag` entities.
- Consumers read settings through the repository's `value()` convenience, not a service facade.
- Writing a `FeatureFlag` model directly skips cache invalidation — always go through the repository.
