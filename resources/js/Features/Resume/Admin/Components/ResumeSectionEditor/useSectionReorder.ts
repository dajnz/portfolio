import type { ResumeSectionKey, ResumeSectionsMap } from '@resume/Admin/Types/layout';
import { useReorder } from '@shared-cross/Composables/useReorder';
import type { ComputedRef } from 'vue';

import type { SectionRow } from './types';

export interface SectionReorderProps {
    modelValue: ResumeSectionsMap;
}

export interface SectionReorderDependencies {
    visibleRows: ComputedRef<SectionRow[]>;
    hiddenRows: ComputedRef<SectionRow[]>;
    renumbered: (ordered: readonly SectionRow[]) => ResumeSectionsMap;
}

export interface SectionReorderApi {
    canMoveUp: (id: ResumeSectionKey) => boolean;
    canMoveDown: (id: ResumeSectionKey) => boolean;
    toggleVisibility: (id: ResumeSectionKey, visible: boolean) => ResumeSectionsMap;
    moveUp: (id: ResumeSectionKey) => ResumeSectionsMap | null;
    moveDown: (id: ResumeSectionKey) => ResumeSectionsMap | null;
}

export function useSectionReorder(
    props: SectionReorderProps,
    dependencies: SectionReorderDependencies,
): SectionReorderApi {
    const { move, canMoveUp: canMoveIndexUp, canMoveDown: canMoveIndexDown } = useReorder();

    function indexOfVisible(id: ResumeSectionKey): number {
        return dependencies.visibleRows.value.findIndex((row) => row.id === id);
    }

    function canMoveUp(id: ResumeSectionKey): boolean {
        return canMoveIndexUp(indexOfVisible(id));
    }

    function canMoveDown(id: ResumeSectionKey): boolean {
        return canMoveIndexDown(indexOfVisible(id), dependencies.visibleRows.value.length);
    }

    // Toggling visibility keeps the order value — the row snaps to its
    // place within the new group by that value.
    function toggleVisibility(id: ResumeSectionKey, visible: boolean): ResumeSectionsMap {
        const next = { ...props.modelValue };
        next[id] = { ...next[id], visible };

        return next;
    }

    function moved(id: ResumeSectionKey, direction: -1 | 1): ResumeSectionsMap | null {
        const list = dependencies.visibleRows.value;
        const index = list.findIndex((row) => row.id === id);
        const movedList = move(list, index, direction);

        if (movedList === null) {
            return null;
        }

        return dependencies.renumbered([...movedList, ...dependencies.hiddenRows.value]);
    }

    function moveUp(id: ResumeSectionKey): ResumeSectionsMap | null {
        return moved(id, -1);
    }

    function moveDown(id: ResumeSectionKey): ResumeSectionsMap | null {
        return moved(id, 1);
    }

    return { canMoveUp, canMoveDown, toggleVisibility, moveUp, moveDown };
}
