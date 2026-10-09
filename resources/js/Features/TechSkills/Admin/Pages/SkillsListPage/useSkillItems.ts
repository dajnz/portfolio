import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReorder } from '@shared-cross/Composables/useReorder';
import type { SkillGroupData } from '@techskills-admin/SkillGroupCard.vue';
import { computed, type Ref, reactive, ref } from 'vue';

// SkillsListPage — skill CRUD (SkillsListPage.vue is the orchestrator).
// Owns the skill editor state (editing target, drafts, errors, saving
// flag) and the create/save/move/live-update actions; the group editor
// state lives in `useSkillGroups`. `groups` is the page's shared working
// copy; moving a skill persists through the injected `persistSkillOrder`
// callback.
export function useSkillItems(groups: Ref<SkillGroupData[]>, persistSkillOrder: () => void) {
    const editingSkill = ref<{ groupId: number; skillId: number | 'new' } | null>(null);
    const savingSkill = ref(false);
    const skillErrors = ref<Record<string, string>>({});
    const skillDraft = reactive<{ name: string; icon: string | null; proficiency: number }>({
        name: '',
        icon: null,
        proficiency: 3,
    });
    const newSkillDraft = reactive<{
        name: string;
        icon: string | null;
        proficiency: number;
        includeInResume: boolean;
    }>(emptyNewSkill());

    const { t } = useI18n();
    const { move } = useReorder();

    // The localized proficiency-level options for the dropdowns
    // (`skills.proficiency.{1-5}` — the domain's `toProficiencyLabelKey()`
    // keys).
    const proficiencyOptions = computed(() =>
        Array.from({ length: 5 }, (_, index) => ({
            value: String(index + 1),
            label: t(`skills.proficiency.${index + 1}`),
        })),
    );

    function emptyNewSkill(): {
        name: string;
        icon: string | null;
        proficiency: number;
        includeInResume: boolean;
    } {
        return { name: '', icon: null, proficiency: 3, includeInResume: false };
    }

    function startAddSkill(groupId: number): void {
        Object.assign(newSkillDraft, emptyNewSkill());
        skillErrors.value = {};
        editingSkill.value = { groupId, skillId: 'new' };
    }

    function startEditSkill(groupId: number, skillId: number): void {
        const skill = groups.value
            .find((group) => group.id === groupId)
            ?.skills.find((row) => row.id === skillId);

        if (skill === undefined) {
            return;
        }

        Object.assign(skillDraft, {
            name: skill.name,
            icon: skill.icon,
            proficiency: skill.proficiency,
        });
        skillErrors.value = {};
        editingSkill.value = { groupId, skillId };
    }

    function cancelSkillEdit(groupId: number, skillId: number | 'new'): void {
        if (editingSkill.value?.groupId === groupId && editingSkill.value.skillId === skillId) {
            editingSkill.value = null;
        }

        skillErrors.value = {};
    }

    function isEditingSkill(groupId: number, skillId: number): boolean {
        return (
            editingSkill.value !== null &&
            editingSkill.value.groupId === groupId &&
            editingSkill.value.skillId === skillId
        );
    }

    function isNewSkillEditorOpen(groupId: number): boolean {
        return (
            editingSkill.value !== null &&
            editingSkill.value.groupId === groupId &&
            editingSkill.value.skillId === 'new'
        );
    }

    function saveNewSkill(groupId: number): void {
        if (savingSkill.value) {
            return;
        }

        savingSkill.value = true;
        skillErrors.value = {};

        router.post(
            '/admin/skills',
            {
                name: newSkillDraft.name,
                icon: newSkillDraft.icon,
                proficiency: newSkillDraft.proficiency,
                skill_group_id: groupId,
                include_in_resume: newSkillDraft.includeInResume,
                sort_order: groups.value.find((group) => group.id === groupId)?.skills.length ?? 0,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    savingSkill.value = false;
                },
                onError: (serverErrors) => {
                    skillErrors.value = { ...serverErrors };
                },
            },
        );
    }

    function saveSkill(groupId: number, skillId: number): void {
        if (savingSkill.value) {
            return;
        }

        const group = groups.value.find((row) => row.id === groupId);
        const skill = group?.skills.find((row) => row.id === skillId);

        if (group === undefined || skill === undefined) {
            return;
        }

        savingSkill.value = true;
        skillErrors.value = {};

        router.put(
            `/admin/skills/${skillId}`,
            {
                name: skillDraft.name,
                icon: skillDraft.icon,
                proficiency: skillDraft.proficiency,
                skill_group_id: groupId,
                include_in_resume: skill.include_in_resume,
                sort_order: skill.sort_order,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (isEditingSkill(groupId, skillId)) {
                        editingSkill.value = null;
                    }
                },
                onFinish: () => {
                    savingSkill.value = false;
                },
                onError: (serverErrors) => {
                    skillErrors.value = { ...serverErrors };
                },
            },
        );
    }

    function updateSkillDraft(field: string, value: unknown): void {
        if (field === 'name') {
            skillDraft.name = String(value ?? '');
        } else if (field === 'icon') {
            skillDraft.icon = value as string | null;
        } else if (field === 'proficiency') {
            skillDraft.proficiency = Number(value);
        }
    }

    // The editing draft owns name/icon/proficiency (the row's dropdown
    // feeds it); the include-in-resume checkbox stays a live quick action
    // (full-row PUT with the current values, mirroring the contacts page's
    // toggle behavior). The viewing proficiency dots are DISPLAY-ONLY
    // (5.2 rework — proficiency is edited via the inline dropdown).
    function updateSkillField(
        groupId: number,
        skillId: number,
        field: string,
        value: unknown,
    ): void {
        if (
            isEditingSkill(groupId, skillId) &&
            (field === 'name' || field === 'icon' || field === 'proficiency')
        ) {
            updateSkillDraft(field, value);
            return;
        }

        const group = groups.value.find((row) => row.id === groupId);
        const skill = group?.skills.find((row) => row.id === skillId);

        if (group === undefined || skill === undefined) {
            return;
        }

        if (field === 'include_in_resume') {
            skill.include_in_resume = Boolean(value);
        }

        router.put(
            `/admin/skills/${skillId}`,
            {
                name: skill.name,
                icon: skill.icon,
                proficiency: skill.proficiency,
                skill_group_id: groupId,
                include_in_resume: skill.include_in_resume,
                sort_order: skill.sort_order,
            },
            { preserveScroll: true },
        );
    }

    function moveSkill(groupId: number, skillId: number, direction: -1 | 1): void {
        const group = groups.value.find((row) => row.id === groupId);

        if (group === undefined) {
            return;
        }

        const index = group.skills.findIndex((row) => row.id === skillId);
        const next = move(group.skills, index, direction);

        if (next === null) {
            return;
        }

        group.skills = next;

        persistSkillOrder();
    }

    return {
        editingSkill,
        skillDraft,
        newSkillDraft,
        skillErrors,
        savingSkill,
        proficiencyOptions,
        startAddSkill,
        startEditSkill,
        cancelSkillEdit,
        isEditingSkill,
        isNewSkillEditorOpen,
        saveNewSkill,
        saveSkill,
        updateSkillField,
        moveSkill,
    };
}
