# ADR-0014: TOTP recovery codes & lockout policy

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A5, B26, B53, B58
- **Supersedes:** —

## Context

TOTP login needs one-time recovery codes and a brute-force lockout, but the spec left the recovery-code alphabet unspecified and created a tension where the verification page exposes a lockout countdown that the middleware actually prevents from being reached. A decision was needed on code format, lockout representation and where the policy lives.

## Decision

- Recovery codes: 10 codes in the `XXXX-XXXX-XXXX` (12-character) display format, bcrypt-hashed, stored as `[{ "hash", "used" }]` in `totp_recovery_codes`.
- The code alphabet excludes the ambiguous characters `0/O/1/I/L` (`23456789ABCDEFGHJKMNPQRSTUVWXYZ`); matching normalizes input to uppercase alphanumeric with dashes ignored, so the `XXXX-XXXX-XXXX` format is purely a display form.
- Using an invalid recovery code counts as a failed TOTP attempt and increments the lockout backoff counter.
- Lockout tiers, messages and the code-format rule live in the `AdminTimedOtp` entity (static `lockExpiryFor()`, `isPermanentLock()`, `lockoutMinutesRemaining()`, `isValidCodeFormat()`, `generateRecoveryCodes()`; instance `isLocked()`, `isRecoveryCodeValid()`, `isRecoveryCodeUsed()`, `withRecoveryCodeConsumed()`, `enableWith()`, `disable()`, plus counter/lock with-methods).
- A permanent lock is a +100-years `totp_locked_until` sentinel detected via `isPermanent()` (more than 24h remaining), not a separate flag.
- Locked admins are unreachable on the verification page: `RequireTotpPending`/`RequireGithubLinkTotpPending` redirect to the login/profile page with a lockout flash (remaining minutes, or the permanent-lock message from 15+ attempts). `TotpVerificationPage.lock_remaining_seconds` is therefore always `null` in practice, but is still passed from a session flash to satisfy the committed `auth.yaml` contract.

## Consequences

- Display and storage formats differ — never persist the user-facing dashed form.
- Tests must cover input normalization (lowercase, dashes, ambiguous characters), since matching depends on it.
- Lockout state/messages are a domain concern; middleware only redirects and flashes.
- `lock_remaining_seconds` is a contract-only prop and should be treated as effectively null.
