import { ADMIN_ESCAPE_EVENT } from '@shared-cross/Composables/useAdminShortcuts';
import { onBeforeUnmount, onMounted, type Ref } from 'vue';

// SkillsListPage — editor exit model (SkillsListPage.vue is the
// orchestrator). Editors exit only via click-outside, Escape, or Cancel;
// there is no blur-save (B135/B140 — the blur fires while the next
// control still holds focus and saved half-edited drafts). The group
// editor exits on a click outside the group header, the skill editor on a
// click outside the row. Capture-phase `pointerdown` runs BEFORE the
// click handlers, so a click that opens a new editor (Create Group,
// Add Skill, another row) first closes the previous one and the click
// then opens its target — no double-state races. The icon picker's panel
// is teleported to <body>, so each editor's containment includes its
// panel.
export interface EditorExitsOptions {
    editingGroupId: Ref<number | 'new' | null>;
    editingSkill: Ref<{ groupId: number; skillId: number | 'new' } | null>;
    groupErrors: Ref<Record<string, string>>;
    skillErrors: Ref<Record<string, string>>;
    cancelGroupEdit: () => void;
    cancelSkillEdit: (groupId: number, skillId: number | 'new') => void;
    testId: string;
}

export function useEditorExits(options: EditorExitsOptions): void {
    function onAdminEscape(): void {
        options.editingGroupId.value = null;
        options.editingSkill.value = null;
        options.groupErrors.value = {};
        options.skillErrors.value = {};
    }

    function onEditorPointerDown(event: PointerEvent): void {
        const target = event.target as Node;

        if (options.editingGroupId.value !== null) {
            const id = options.editingGroupId.value;
            const headerTestId =
                id === 'new'
                    ? `${options.testId}-new-group`
                    : `${options.testId}-group-${id}-header`;
            const header = document.querySelector(`[data-testid="${headerTestId}"]`);
            const panelTestId =
                id === 'new'
                    ? `${options.testId}-new-group-icon-panel`
                    : `${options.testId}-group-${id}-icon-panel`;
            const panel = document.querySelector(`[data-testid="${panelTestId}"]`);

            if (header !== null && !header.contains(target) && !panel?.contains(target)) {
                options.cancelGroupEdit();
            }
        }

        if (options.editingSkill.value !== null) {
            const { groupId, skillId } = options.editingSkill.value;
            const rowTestId =
                skillId === 'new'
                    ? `${options.testId}-new-skill-${groupId}`
                    : `${options.testId}-skill-${skillId}`;
            const row = document.querySelector(`[data-testid="${rowTestId}"]`);
            const panelTestId =
                skillId === 'new'
                    ? `${options.testId}-new-skill-${groupId}-icon-panel`
                    : `${options.testId}-skill-${skillId}-icon-panel`;
            const panel = document.querySelector(`[data-testid="${panelTestId}"]`);

            if (row !== null && !row.contains(target) && !panel?.contains(target)) {
                options.cancelSkillEdit(groupId, skillId);
            }
        }
    }

    onMounted(() => {
        window.addEventListener(ADMIN_ESCAPE_EVENT, onAdminEscape);
        document.addEventListener('pointerdown', onEditorPointerDown, true);
    });

    onBeforeUnmount(() => {
        window.removeEventListener(ADMIN_ESCAPE_EVENT, onAdminEscape);
        document.removeEventListener('pointerdown', onEditorPointerDown, true);
    });
}
