import { describe, expect, it } from 'vitest';

import { cursorNavigationParams } from '@shared-cross/Utils/cursorNavigationParams';

const CURSORS = {
    first: { id: 2, sort_value: null },
    last: { id: 1, sort_value: null },
};

describe('cursorNavigationParams', () => {
    it('builds a page jump', () => {
        expect(cursorNavigationParams({ page: 3 }, CURSORS)).toEqual({ page: 3 });
    });

    it('builds the next-page cursor params', () => {
        expect(cursorNavigationParams({ cursor: { id: 1, sort_value: null } }, CURSORS)).toEqual({
            'cursor[id]': 1,
        });
    });

    it('adds cursor[before]=1 for the previous-page cursor (B72)', () => {
        expect(cursorNavigationParams({ cursor: { id: 2, sort_value: null } }, CURSORS)).toEqual({
            'cursor[id]': 2,
            'cursor[before]': 1,
        });
    });

    it('carries a composite sort value', () => {
        expect(
            cursorNavigationParams({ cursor: { id: 5, sort_value: '2026-07-14T09:32:00+00:00' } }, CURSORS),
        ).toEqual({
            'cursor[id]': 5,
            'cursor[sort_value]': '2026-07-14T09:32:00+00:00',
        });
    });

    it('matches the first cursor only on the full shape (id + sort value)', () => {
        expect(
            cursorNavigationParams(
                { cursor: { id: 2, sort_value: '2026-07-14T09:32:00+00:00' } },
                CURSORS,
            ),
        ).toEqual({
            'cursor[id]': 2,
            'cursor[sort_value]': '2026-07-14T09:32:00+00:00',
        });
    });

    it('works without the cursors reference (never previous)', () => {
        expect(cursorNavigationParams({ cursor: { id: 7, sort_value: null } })).toEqual({
            'cursor[id]': 7,
        });
    });
});
