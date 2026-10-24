# ADR-0037: Time storage and display timezones

- **Status:** Accepted
- **Date:** 2026-10-23
- **Source:** — (maintenance decision; supplements ADR-0017, ADR-0024, ADR-0031)
- **Supersedes:** —

## Context

The database stores `timestamp` columns (without time zone) and `config/app.php`
pins the application zone to UTC. The `about_info.timezone` column held the
author's IANA zone, but it was only rendered as a label on the landing page. The
admin surface had no display-zone setting, and the public surface formatted
publication dates in the visitor's browser zone. That mixed conventions:
feedback timestamps were printed as raw UTC wall-clock without a label, the
admin autocomplete showed the UTC calendar day of an instant, and the blog
publication date could differ between the SSR pass (container UTC) and the
hydrated client (visitor zone).

## Decision

- **Storage is UTC.** Instants keep using `DateTimeImmutable` in the domain layer
  and `timestamp without time zone` in PostgreSQL. The application zone stays
  `UTC`; changing it is not a supported way to change display behavior.
- **Wire format follows the kind of value:**
  - instants — ISO-8601 with an explicit offset (`format('c')`, `DATE_ATOM`);
  - calendar dates — `Y-m-d`, never an instant;
  - bucket boundaries — computed server-side in one explicit zone.
- **Calendar dates are never converted.** `started_at` / `ended_at` (experience),
    `analytics_daily_metrics.date`, and `AdminDatePicker` values are day labels.
  Converting them to another zone shifts the day. Date-only strings are parsed
  from their parts, never through `new Date(str)`.
- **Admin surface:** instants render in the admin's browser zone through
  `Intl.DateTimeFormat` (`dd.mm.yyyy` / `dd.mm.yyyy HH:mm`). There is no
  admin-timezone setting. Values that are intrinsically UTC — backup timestamps
  and filenames, and the blog article's first-published stamp — keep an explicit
  `UTC` label instead of being localized.
- **Public surface:** publication dates render in the author's zone taken from
  `about_info.timezone` (shared prop `about_timezone`, resolved and validated
  server-side, `UTC` fallback). The explicit `timeZone` option makes the SSR
  output and the hydrated output identical. Calendar years and the `?year=`
  filter of the public blog are bucketed in the same zone (`AT TIME ZONE` with a
  bound identifier). The admin list's year filter remains UTC.
- **Analytics** daily metrics and report spans are bucketed in UTC (the
  application zone). The spec's former "server-local timezone" wording means
  UTC.
- **Scheduled tasks** use UTC cron strings. A task that must fire at a local
  wall-clock time adds `->timezone(...)` to the event; it must not rely on
  `config('app.timezone')`.
- **No server-side conversion to a visitor zone.** The browser already knows its
  zone; converting on the server would require trusting a client-supplied zone
  and would make SSR non-deterministic. A future time-of-day input must carry an
  offset (or the zone) explicitly at the boundary.

## Consequences

- The landing's timezone label and the publication-date display come from the
  same stored value, so the public surface stays internally consistent.
- Changing `about_info.timezone` retroactively shifts published article dates by
  up to one day around midnight. This is accepted for a personal portfolio; a
  frozen `published_on` date column would be the alternative if the dates ever
  need to be immutable.
- The public blog `?year=` filter and the `years` prop now agree with the dates
  the cards show. A post published within hours of New Year can move between
  year buckets when the author's zone changes.
- The live "local time in {city}" widget is deliberately out of scope: it is
  dynamic and would need client-only rendering to avoid a hydration mismatch.
- Admin pages are SSR-rendered too. A non-UTC admin's timestamps therefore
  arrive as UTC in the server HTML and are corrected to the browser zone on
  hydration — a one-frame difference, not a functional one. The public dates
  do not have this issue because they use an explicit zone.
