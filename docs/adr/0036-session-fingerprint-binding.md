# ADR-0036: Session fingerprint binding & fixation hardening

- **Status:** Accepted
- **Date:** 2026-10-23
- **Source:** — (maintenance decision; supplements ADR-0013)
- **Supersedes:** —

## Context

ADR-0013 covers admin authentication and session-version invalidation, but the
session was bound to nothing but the cookie itself: a stolen session cookie
worked from any browser and network until the 120-minute idle expiry, or up to
400 days with the remember-me recaller. Laravel's `SessionGuard::login()`
already regenerates the session ID on full authentication, yet nothing tested
it, and the TOTP half-auth state (`totp.pending_*`) was written into the
pre-authentication session without rotating its ID — a fixation window on a
privilege boundary.

## Decision

- **Fixation.** `AdminSessionInterface::regenerate()` rotates the session ID;
  `LoginSessionService::beginTotpVerification()` calls it before writing the
  half-auth keys. Full logins keep relying on `SessionGuard::login()` →
  `updateSession()` → `Session::regenerate(true)`; integration tests pin the ID
  change for password login, half-auth entry and TOTP completion.
- **Fingerprint.** Every authenticated admin session carries
  `auth_fingerprint`: a keyed HMAC-SHA256 over `user_agent|ip_network`, computed
  in `LaravelSessionFingerprint` with the application key. IPs are masked to
  IPv4 `/24` / IPv6 `/64` (configurable prefixes), and IPv4-mapped IPv6
  addresses are treated as IPv4. Raw IP/UA values are never stored or logged.
- **Recording.** The `Login` event listener writes the fingerprint for every
  guard login (password, TOTP completion, GitHub OAuth, re-authentication after
  password/TOTP changes, recaller restore). There is no separate call site to
  forget.
- **Enforcement.** The `auth.fingerprint` middleware runs after
  `auth.session_version` on the admin routes, the session-authenticated `/api/*`
  endpoints and Horizon. A missing or different fingerprint is a hard failure:
  warn log with `admin_id` + `request_id`, `Auth::logout()`, session
  invalidation + CSRF token regeneration, then a login redirect with the
  `auth.session_expired` flash (a 401 via `AuthenticationException` on
  `/api/*`). Fail closed on the missing key.
- **Remember-me.** The guard's remember duration is capped at 30 days
  (`auth.guards.web.remember`). The recaller re-authentication re-binds the
  fingerprint from the presenting client, so the binding cannot retroactively
  protect a stolen recaller cookie — the lifetime cap is the mitigation.
- **Transport.** Production provisions `SESSION_SECURE_COOKIE=true`;
  `http_only=true` and `same_site=lax` stay (Lax is required for the GitHub
  OAuth round-trip). `SESSION_BINDING_ENABLED` is an operational escape hatch
  and defaults to true.

## Consequences

- A legitimate IP-network change (mobile CGNAT, VPN toggle) or a browser
  user-agent change logs the admin out and requires password + TOTP again. This
  is the accepted cost of hard enforcement.
- An attacker who can replay the user agent and originate from the same
  `/24` or `/64` network passes; the binding is defense in depth, not an
  authentication substitute.
- Sessions issued before this change have no fingerprint and are logged out on
  their first request (one-time).
- Any future login path must go through the guard so the `Login` listener binds
  the fingerprint; writing the auth key into the session directly would create
  a session that the middleware immediately rejects.
- Rotating `APP_KEY` invalidates every stored fingerprint as well as every
  encrypted cookie — consistent with the existing behaviour.
