export interface Cursor {
    id: number;
    sort_value: string | null;
}

export type CursorNavigationPayload = { cursor: Cursor } | { page: number };

/**
 * Builds the query params for a cursor-pagination navigation (B72): a
 * `page` jump produces `?page=N`; a cursor navigation produces
 * `cursor[id]` + `cursor[sort_value]` (when present), plus
 * `cursor[before]=1` when the cursor equals the list's `cursors.first`
 * (the previous-page marker per admin-section-ui.md:181). Shared by the
 * admin list pages (extracted from the duplicated per-page `navigate()`
 * functions, 4.2 rework).
 */
export function cursorNavigationParams(
    payload: CursorNavigationPayload,
    cursors?: { first: Cursor },
): Record<string, string | number> {
    if ('page' in payload) {
        return { page: payload.page };
    }

    const params: Record<string, string | number> = { 'cursor[id]': payload.cursor.id };

    if (payload.cursor.sort_value !== null) {
        params['cursor[sort_value]'] = payload.cursor.sort_value;
    }

    const first = cursors?.first;

    if (
        first !== undefined &&
        payload.cursor.id === first.id &&
        payload.cursor.sort_value === first.sort_value
    ) {
        params['cursor[before]'] = 1;
    }

    return params;
}
