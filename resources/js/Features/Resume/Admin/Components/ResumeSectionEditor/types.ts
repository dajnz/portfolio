import type { ResumeSectionKey } from '@resume/Admin/Types/layout';

// A type alias (not an interface) — SortableList's items are
// `Record<string, unknown>` and type aliases receive the implicit index
// signature interfaces lack.
export type SectionRow = {
    id: ResumeSectionKey;
    visible: boolean;
    order: number;
};
