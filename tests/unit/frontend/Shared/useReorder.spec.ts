import { describe, expect, it } from 'vitest';

import { useReorder } from '@shared-cross/Composables/useReorder';

describe('useReorder', () => {
    const { move, canMoveUp, canMoveDown } = useReorder();

    it('moves an item one position and returns a new array', () => {
        const items = ['a', 'b', 'c'];

        expect(move(items, 0, 1)).toEqual(['b', 'a', 'c']);
        expect(move(items, 2, -1)).toEqual(['a', 'c', 'b']);
        // The source array is untouched.
        expect(items).toEqual(['a', 'b', 'c']);
    });

    it('returns null at the boundaries', () => {
        const items = ['a', 'b', 'c'];

        expect(move(items, 0, -1)).toBeNull();
        expect(move(items, 2, 1)).toBeNull();
        expect(move(items, -1, 1)).toBeNull();
    });

    it('reports the movable boundaries', () => {
        expect(canMoveUp(0)).toBe(false);
        expect(canMoveUp(1)).toBe(true);
        expect(canMoveDown(1, 3)).toBe(true);
        expect(canMoveDown(2, 3)).toBe(false);
        expect(canMoveDown(3, 3)).toBe(false);
        expect(canMoveDown(0, 1)).toBe(false);
    });
});
