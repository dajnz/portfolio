const DATE_PARTS: Intl.DateTimeFormatOptions = {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
};

const DATE_TIME_PARTS: Intl.DateTimeFormatOptions = {
    ...DATE_PARTS,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
};

interface CalendarParts {
    day: string;
    month: string;
    year: string;
    hour: string;
    minute: string;
}

export function formatShortDate(value: string | null, timeZone?: string): string {
    const parts = calendarParts(value, timeZone, DATE_PARTS);

    return parts === null ? '' : `${parts.day}.${parts.month}.${parts.year}`;
}

export function formatShortDateTime(value: string | null, timeZone?: string): string {
    const parts = calendarParts(value, timeZone, DATE_TIME_PARTS);

    return parts === null
        ? ''
        : `${parts.day}.${parts.month}.${parts.year} ${parts.hour}:${parts.minute}`;
}

export function resolveTimeZone(candidate: string | null | undefined, fallback = 'UTC'): string {
    if (candidate === null || candidate === undefined || candidate === '') {
        return fallback;
    }

    try {
        return new Intl.DateTimeFormat('en', { timeZone: candidate }).resolvedOptions().timeZone;
    } catch {
        return fallback;
    }
}

function calendarParts(
    value: string | null,
    timeZone: string | undefined,
    options: Intl.DateTimeFormatOptions,
): CalendarParts | null {
    if (value === null || value === '') {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const parts = new Intl.DateTimeFormat('en-GB', { ...options, timeZone }).formatToParts(date);
    const lookup = new Map(parts.map((part) => [part.type, part.value]));
    const day = lookup.get('day');
    const month = lookup.get('month');
    const year = lookup.get('year');
    const hour = lookup.get('hour') ?? '00';
    const minute = lookup.get('minute') ?? '00';

    if (day === undefined || month === undefined || year === undefined) {
        return null;
    }

    return { day, month, year, hour, minute };
}
