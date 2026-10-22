import {
    RESUME_SECTION_KEYS,
    type ResumeSectionKey,
    type ResumeSectionsMap,
} from '@resume/Admin/Types/layout';
import { type ComputedRef, computed } from 'vue';

import type { SectionRow } from './types';

export interface SectionRowsProps {
    modelValue: ResumeSectionsMap;
    labels: Partial<Record<ResumeSectionKey, string>>;
}

export interface SectionRowsApi {
    visibleRows: ComputedRef<SectionRow[]>;
    hiddenRows: ComputedRef<SectionRow[]>;
    labelOf: (id: ResumeSectionKey) => string;
    renumbered: (ordered: readonly SectionRow[]) => ResumeSectionsMap;
    applyVisibleOrder: (items: readonly SectionRow[]) => ResumeSectionsMap | null;
}

export function useSectionRows(props: SectionRowsProps): SectionRowsApi {
    function rowsWithVisibility(visible: boolean): SectionRow[] {
        return RESUME_SECTION_KEYS.filter((id) => props.modelValue[id].visible === visible)
            .sort((left, right) => props.modelValue[left].order - props.modelValue[right].order)
            .map((id) => ({ id, ...props.modelValue[id] }));
    }

    const visibleRows = computed<SectionRow[]>(() => rowsWithVisibility(true));
    const hiddenRows = computed<SectionRow[]>(() => rowsWithVisibility(false));

    function labelOf(id: ResumeSectionKey): string {
        return props.labels[id] ?? id;
    }

    // Reassigns the order values to the rows' display positions — a
    // reorder always keeps the five unique 0–4 permutation (visible-only
    // renumbering could collide with the hidden values).
    function renumbered(ordered: readonly SectionRow[]): ResumeSectionsMap {
        const next = { ...props.modelValue };

        ordered.forEach((row, index) => {
            next[row.id] = { ...next[row.id], order: index };
        });

        return next;
    }

    // A drop that lands back in the original slot (or a cancelled drag)
    // must not rewrite the stored order values.
    function applyVisibleOrder(items: readonly SectionRow[]): ResumeSectionsMap | null {
        const current = visibleRows.value;
        const sameOrder =
            current.length === items.length &&
            items.every((row, index) => row.id === current[index]?.id);

        if (sameOrder) {
            return null;
        }

        const visible = items.map((row) => ({ id: row.id, ...props.modelValue[row.id] }));

        return renumbered([...visible, ...hiddenRows.value]);
    }

    return { visibleRows, hiddenRows, labelOf, renumbered, applyVisibleOrder };
}
