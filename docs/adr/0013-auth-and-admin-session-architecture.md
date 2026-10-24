# ADR-0013: Authentication & admin session architecture

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B27, B35, B54, B56, B57, B59, B60, B61, B63, B127, B129
- **Supersedes:** —

## Context

Admin authentication spans password login, TOTP, GitHub OAuth linking and session-version invalidation, and the original design mixed domain logic with framework code while the spec itself contained broken validation syntax and dead derived fields. A decision was needed on domain boundaries, where middleware checks and flows live, and how session versioning interacts with re-authentication.

## Decision

- `Admin` is an aggregate root with an embedded `AdminTimedOtp` entity, backed by a separate `AdminTimedOtpRepositoryInterface`, `AdminTimedOtpMapper` and Eloquent repo over the same `admins` row.
- The auth domain is framework-free: `DateTimeImmutable` replaces Carbon throughout (mappers convert the model's Carbon via `toDateTimeImmutable()`), and recovery-code hashing uses native `password_hash`/`password_verify`.
- `AdminAuthService` (Auth/Infra/Services) owns middleware checks as semantic named methods — `isAuthenticatedAdmin()`, `sessionVersionMatches()`, `forceLogout()`, `hasPendingTotpLogin()`, `pendingTotpLoginExpired()`, `pendingTotpLoginAdmin()`, `clearPendingTotpLogin()`, `githubLinkPendingForCurrentAdmin()`, `pendingGithubLinkExpired()`, `pendingGithubLinkAdmin()`, `clearPendingGithubLink()`, `clearPendingAuthenticationState()`, `adminIsTotpLocked()`, `totpLockoutFlash()`; the four auth middleware are thin redirect+flash shells.
- Auth flows are use cases: `VerifyLoginTotpUseCase` and `VerifyGithubLinkTotpUseCase` own the half-auth session state and rethrow `TotpLockedException` with the localized lockout message. The reusable mechanisms live in `App/Services/`: `TotpVerifier` (verification + escalating backoff), `LoginSessionService` (half-auth state + guard login) and `GithubLinkService` (`prepareAccountLinking()` returns a `GithubLinkOutcome` (AlreadyLinked/Conflict/TotpRequired/Linked), plus `link()`/`unlink()`); the current-password check lives in `UpdateProfileUseCase`.
- `SessionVersionSynchronizer` lives in `App/Services/` as a plain service (not a `BaseUseCase`); `bumpAndReauthenticate()` is the single place for the bump + re-auth + store sequence.
- Re-auth ordering is fixed: the version increment runs BEFORE `Auth::loginUsingId()` so the guard's cached user carries the new version, and the version is stored AFTER re-authentication because `Auth::loginUsingId()` regenerates the session ID and wipes its data. The `auth.session_version` middleware compares against `Auth::user()->session_version`.
- Repositories are entity-based (`save(Admin $admin)` syncs credentials, GitHub link and session version; TOTP writes via `AdminTimedOtpRepositoryInterface::saveForAdmin()`). Remaining low-level arguments are confined to the bcrypt boundary (`create()`/`changePassword()` take plaintext) and spec-mandated atomic counters (`incrementSessionVersion()`, `incrementFailedAttempts()` — no read-modify-write).
- `login` and `totp` named rate limiters (5/min/IP) are defined in `AppServiceProvider::boot()`.
- `admin:create` sets `name` = login, since the spec prompts for login + email only and `admins.name` is NOT NULL.
- The profile-update rule is implemented as `required_with:password` instead of the spec's broken `required_if:password`, keeping the intent that `current_password` is required exactly when a new password is submitted.
- `is_oauth_primary` is the computed formula `(github_id IS NOT NULL) && (password IS NULL)`, never stored; it is dead under the current schema (`admins.password` NOT NULL) but is derived and passed anyway for forward compatibility.
- The login page's GitHub button is shown only when the `github_oauth` flag is ON AND at least one admin has `admins.github_id` set; the flag-only `github_oauth_enabled` prop is replaced by the combined `github_login_available` (computed via `AdminRepositoryInterface::hasGithubLinkedAdmin()`), and the profile GitHub linking section is hidden while the flag is off.
- Clicking the login page's GitHub button arms the card's loading/waiting state and disables the trigger until the OAuth redirect leaves the page.

## Consequences

- The ordering inside `bumpAndReauthenticate()` must not be reordered; reordering desynchronizes the guard's cached user from the stored session version.
- The domain remains unit-testable without the container.
- Adding an auth check means adding a semantic method to `AdminAuthService`, not embedding logic in middleware.
- Any new place that changes credentials or TOTP state must go through `SessionVersionSynchronizer::bumpAndReauthenticate()`.
- The `is_oauth_primary` field currently carries no behaviour and exists only for future schema changes.
