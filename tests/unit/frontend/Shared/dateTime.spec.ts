import { describe, expect, it } from 'vitest';

import { formatShortDate, formatShortDateTime, resolveTimeZone } from '@shared-cross/Utils/dateTime';

describe('dateTime', () => {
    it('formats a date in the explicit timezone', () => {
        expect(formatShortDate('2026-01-15T00:30:00Z', 'America/New_York')).toBe('14.01.2026');
        expect(formatShortDate('2026-01-15T00:30:00Z', 'Europe/Warsaw')).toBe('15.01.2026');
    });

    it('formats a date and time in the explicit timezone', () => {
        expect(formatShortDateTime('2026-07-14T09:32:00Z', 'UTC')).toBe('14.07.2026 09:32');
        expect(formatShortDateTime('2026-07-14T09:32:00Z', 'Asia/Kolkata')).toBe('14.07.2026 15:02');
    });

    it('renders an empty string for a missing or unparseable value', () => {
        expect(formatShortDate(null)).toBe('');
        expect(formatShortDate('')).toBe('');
        expect(formatShortDate('not-a-date')).toBe('');
        expect(formatShortDateTime(null)).toBe('');
    });

    it('falls back to UTC for a missing or unknown timezone', () => {
        expect(resolveTimeZone(undefined)).toBe('UTC');
        expect(resolveTimeZone(null)).toBe('UTC');
        expect(resolveTimeZone('')).toBe('UTC');
        expect(resolveTimeZone('Not/AZone')).toBe('UTC');
    });

    it('keeps a known IANA timezone', () => {
        expect(resolveTimeZone('Europe/Berlin')).toBe('Europe/Berlin');
    });

    it('returns the canonical zone for a valid alias', () => {
        expect(resolveTimeZone('US/Eastern')).toBe('America/New_York');
    });
});
