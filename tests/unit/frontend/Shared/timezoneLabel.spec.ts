import { describe, expect, it } from 'vitest';

import { formatTimezoneLabel } from '@shared-cross/Utils/timezoneLabel';

describe('formatTimezoneLabel', () => {
    it('appends the current UTC offset to the identifier (Moscow never applies DST)', () => {
        expect(formatTimezoneLabel('Europe/Moscow')).toBe('Europe/Moscow (UTC+3)');
    });

    it('labels the UTC zone itself with a zero offset', () => {
        expect(formatTimezoneLabel('UTC')).toBe('UTC (UTC+0)');
    });

    it('keeps the minutes for fractional offsets', () => {
        expect(formatTimezoneLabel('Asia/Kolkata')).toBe('Asia/Kolkata (UTC+5:30)');
    });

    it('returns the raw identifier when the runtime does not know the zone', () => {
        expect(formatTimezoneLabel('Not/AZone')).toBe('Not/AZone');
    });
});
