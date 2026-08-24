/**
 * Backup list formatting helpers (mockup `backups.html` — the visual
 * reference): byte sizes ("45 MB", "1.2 GB") and UTC date stamps
 * ("2026-07-09", "2026-07-09 02:00 UTC"). The mockup's admin dates are
 * plain ISO stamps, so no locale-aware Intl formatting is applied.
 */

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** "2026-07-09" — the DataTable Date column. */
export function formatBackupDate(iso: string): string {
    const date = new Date(iso);

    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
}

/** "2026-07-09 02:00 UTC" — the BackupStats last-backup stamp. */
export function formatBackupDateTime(iso: string): string {
    const date = new Date(iso);

    return `${formatBackupDate(iso)} ${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())} UTC`;
}

/** "0 B", "45 MB", "1.2 GB" — base-1024 units, no trailing ".0". */
export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    const rounded = value >= 10 ? Math.round(value) : Math.round(value * 10) / 10;
    const rendered = Number.isInteger(rounded) ? String(rounded) : String(rounded);

    return `${rendered} ${units[unit]}`;
}
