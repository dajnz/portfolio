import { nextTick, type Ref, watch } from 'vue';

import type { SkillEditorTarget } from './types';

export interface EditorScrollOptions {
    editingGroupId: Ref<number | 'new' | null>;
    editingSkill: Ref<SkillEditorTarget | null>;
    testId: string;
}

// The transient editors render below their list/group, so on a long page
// they can open off-screen — scroll them into view when they open.
export function useEditorScroll(options: EditorScrollOptions): void {
    watch(options.editingGroupId, async (value) => {
        if (value !== 'new') {
            return;
        }

        await nextTick();
        scrollToTestId(`${options.testId}-new-group`);
    });

    watch(options.editingSkill, async (value) => {
        if (value?.skillId !== 'new') {
            return;
        }

        await nextTick();
        scrollToTestId(`${options.testId}-new-skill-${value.groupId}`);
    });
}

function scrollToTestId(testId: string): void {
    document
        .querySelector<HTMLElement>(`[data-testid="${testId}"]`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
