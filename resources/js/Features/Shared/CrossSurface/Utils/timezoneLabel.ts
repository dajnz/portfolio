// Timezone display labels for the admin pickers: the about form's
// timezone dropdown (user request 2026-09-04) shows each IANA identifier
// with its current UTC offset — "Europe/Moscow (UTC+3)" — so admins pick
// the right zone without knowing the identifier table. The offset is the
// runtime's current offset (DST-aware: Europe/Berlin reads UTC+2 in
// summer, UTC+1 in winter — the same semantics the public landing page
// uses, useLandingPage.ts formatTimezone). Unknown zones fall back to the
// raw identifier (it must stay selectable/savable).
export function formatTimezoneLabel(iana: string): string {
    try {
        const parts = new Intl.DateTimeFormat('en', {
            timeZone: iana,
            timeZoneName: 'longOffset',
        }).formatToParts();
        const offset = parts.find((part) => part.type === 'timeZoneName')?.value ?? '';

        return `${iana} (${compactOffset(offset)})`;
    } catch {
        return iana;
    }
}

// "GMT+03:00" → "UTC+3": GMT is prefixed with UTC (the user-facing form),
// the minutes are dropped when zero and the leading zeros of the hour are
// trimmed ("GMT-02:30" → "UTC-2:30" keeps the minutes). Engines report a
// bare "GMT" for zero-offset zones in some versions — normalize to UTC+0.
function compactOffset(offset: string): string {
    const normalized = offset
        .replace(/^GMT/, 'UTC')
        .replace(/:00$/, '')
        .replace(/([+-])0+(\d)/, '$1$2');

    return normalized === '' || normalized === 'UTC' ? 'UTC+0' : normalized;
}
