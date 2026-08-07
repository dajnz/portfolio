/**
 * Random UUID v4 (RFC 4122). `crypto.randomUUID()` is only available in
 * secure contexts — the in-container e2e base URL (`http://nginx`) is not
 * one, so a Math.random-based fallback keeps the app mounting there.
 */
export function randomUuid(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = (Math.random() * 16) | 0;
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}
