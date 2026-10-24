# ADR-0016: API-based mail transport

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B20, B49, B100, B125
- **Supersedes:** —

## Context

The project needed to send transactional mail (feedback notifications) and originally planned an SMTP transport configured from the admin Settings page. A decision was needed to move to a third-party REST mail API and to define how the API credentials round-trip through the settings UI without leaking or wiping secrets.

## Decision

- Mail is sent via a third-party REST API using a single bearer token + custom API endpoint (Mailgun/Mailchimp-style); SMTP is no longer the transport.
- The six SMTP settings (`mail_driver`, `mail_host`, `mail_port`, `mail_encryption`, `mail_username`, `mail_password`) are replaced by `mail_api_endpoint` (the REST endpoint URL) and `mail_api_token` (encrypted at rest, masked as `''` in page props; an empty submission keeps the stored value). `mail_from_address`, `mail_from_name` and `email_on_feedback` are unchanged. Predefined keys: 21 → 17.
- `config/mail.php` gains an `api` mailer (`MAIL_API_ENDPOINT`/`MAIL_API_TOKEN`, empty defaults). The framework's generic `smtp`/`log` mailers stay as env-driven fallbacks (e.g. mailpit in dev).
- `SettingsMailConfigProvider` registers `Mail::extend('api', ...)` → `Settings\Infra\Mail\ApiMailTransport` (Symfony `AbstractTransport`; JSON POST `{from, to, subject, html, text}` to the endpoint with `Authorization: Bearer {token}`; failures → `TransportException`), and switches `mail.default` to `api` only when a DB `mail_api_endpoint` is configured; otherwise the env default applies (no transport change when the settings row is empty or the table is absent).
- "Test Connection" (B20) is `POST /admin/settings/test-mail`, inside the auth group: `TestMailRequest` (only the 5 mail keys accepted) + `TestMailUseCase` merge the submitted Mail-tab values over the stored settings (a masked empty token falls back to the stored one), then probe `GET {endpoint}` + bearer token (10 s timeout). 401/403 → `{ok: false, status: 'unauthorized'}`, network errors → `{ok: false, status: 'unreachable'}`, any other response → `{ok: true, status: 'ok'}`; no email is ever sent.
- Feedback notifications (`NotifyAdminOfFeedbackMail`) are addressed to `config('mail.from.address')` — the site's own from address — because the spec defines no dedicated feedback recipient setting.

## Consequences

- The `mail_api_token` masking requires the round-trip semantics (empty submission keeps the stored value) to prevent accidental wipe and double-encryption of the ciphertext.
- Mail transport degrades gracefully when the settings table or row is absent, falling back to the env-configured mailer.
- Changes to the mail settings must update `database.md` predefined keys, the Settings surface + validation tables, the admin Mail/API Connection tab/components, `architecture.md` Layer 4, and the settings mockup.
