/**
 * The table-size picker grid state (B232 — Google Docs / Notion
 * style): the 6×6 cells, the hovered region grows from the top-left
 * corner, a live `rows × cols` label follows (initial 3×3), arrow keys
 * move the hover, Enter inserts. Owned by EditorConfigPopup (the
 * popup registers its own keydown listener for the table mode).
 */
import { type Ref, ref } from 'vue';

export const TABLE_GRID_SIZE = 6;

export interface TableGridHover {
    rows: number;
    cols: number;
}

export function useEditorTableGrid(): {
    size: number;
    hover: Ref<TableGridHover>;
    hoverFromCell: (cell: number) => TableGridHover;
    setHover: (cell: number) => void;
    cellActive: (cell: number) => boolean;
    move: (direction: string) => void;
    reset: () => void;
} {
    const hover = ref<TableGridHover>({ rows: 3, cols: 3 });

    function hoverFromCell(cell: number): TableGridHover {
        return {
            rows: Math.ceil(cell / TABLE_GRID_SIZE),
            cols: ((cell - 1) % TABLE_GRID_SIZE) + 1,
        };
    }

    function setHover(cell: number): void {
        hover.value = hoverFromCell(cell);
    }

    function cellActive(cell: number): boolean {
        const { rows, cols } = hoverFromCell(cell);

        return rows <= hover.value.rows && cols <= hover.value.cols;
    }

    function move(direction: string): void {
        const next = { ...hover.value };

        if (direction === 'ArrowUp') {
            next.rows = Math.max(1, next.rows - 1);
        } else if (direction === 'ArrowDown') {
            next.rows = Math.min(TABLE_GRID_SIZE, next.rows + 1);
        } else if (direction === 'ArrowLeft') {
            next.cols = Math.max(1, next.cols - 1);
        } else if (direction === 'ArrowRight') {
            next.cols = Math.min(TABLE_GRID_SIZE, next.cols + 1);
        }

        hover.value = next;
    }

    function reset(): void {
        hover.value = { rows: 3, cols: 3 };
    }

    return { size: TABLE_GRID_SIZE, hover, hoverFromCell, setHover, cellActive, move, reset };
}
