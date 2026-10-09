/**
 * Shared list reordering for the keyboard move-up/down alternatives.
 * `move` returns a NEW array with the item at `index` shifted one
 * position, or `null` when it is already at the boundary (the caller
 * then keeps the current order); `canMoveUp` / `canMoveDown` drive the
 * boundary disabled states.
 */
export interface Reorder {
    move: <T>(items: readonly T[], index: number, direction: -1 | 1) => T[] | null;
    canMoveUp: (index: number) => boolean;
    canMoveDown: (index: number, length: number) => boolean;
}

export function useReorder(): Reorder {
    function move<T>(items: readonly T[], index: number, direction: -1 | 1): T[] | null {
        const target = index + direction;

        if (index < 0 || target < 0 || target >= items.length) {
            return null;
        }

        const next = [...items];
        const [moved] = next.splice(index, 1);

        if (moved === undefined) {
            return null;
        }

        next.splice(target, 0, moved);

        return next;
    }

    function canMoveUp(index: number): boolean {
        return index > 0;
    }

    function canMoveDown(index: number, length: number): boolean {
        return index >= 0 && index < length - 1;
    }

    return { move, canMoveUp, canMoveDown };
}
