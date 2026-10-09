import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReorder } from '@shared-cross/Composables/useReorder';
import { useToast } from '@shared-cross/Composables/useToast';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { localesWithErrors } from '@shared-cross/Utils/localeErrors';
import type { SkillGroupData } from '@techskills-admin/SkillGroupCard.vue';
import { computed, type Ref, reactive, ref } from 'vue';

// SkillsListPage — group CRUD (SkillsListPage.vue is the orchestrator).
// Owns the group editor state (drafts, errors, saving flag) and the
// create/save/move actions; the skills editor state lives in
// `useSkillItems`. `groups` is the page's shared working copy; moving a
// group persists through the injected `persistGroupOrder` callback.
export function useSkillGroups(groups: Ref<SkillGroupData[]>, persistGroupOrder: () => void) {
    const editingGroupId = ref<number | 'new' | null>(null);
    const editingGroupLocale = ref('en');
    const savingGroup = ref(false);
    const groupErrors = ref<Record<string, string>>({});
    const groupDraft = reactive<{ names: Record<string, string>; icon: string | null }>({
        names: emptyNames(),
        icon: null,
    });

    const { t } = useI18n();
    const toast = useToast();
    const { move } = useReorder();

    // Locales whose required name translation is empty — the tab gets the
    // warning marker before the save round-trip.
    const warnedGroupLocales = computed(() =>
        SUPPORTED_LOCALES.filter((code) => (groupDraft.names[code] ?? '').trim() === ''),
    );

    function emptyNames(): Record<string, string> {
        return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
    }

    function groupNameError(): string | null {
        return groupErrors.value[`name.${editingGroupLocale.value}`] ?? null;
    }

    function startCreateGroup(): void {
        Object.assign(groupDraft, { names: emptyNames(), icon: null });
        groupErrors.value = {};
        editingGroupId.value = 'new';
        editingGroupLocale.value = 'en';
    }

    function startEditGroup(group: SkillGroupData): void {
        Object.assign(groupDraft, { names: { ...group.names }, icon: group.icon });
        groupErrors.value = {};
        editingGroupId.value = group.id;
        editingGroupLocale.value = 'en';
    }

    function startEditGroupById(id: number): void {
        const group = groups.value.find((row) => row.id === id);

        if (group !== undefined) {
            startEditGroup(group);
        }
    }

    function cancelGroupEdit(): void {
        editingGroupId.value = null;
        groupErrors.value = {};
    }

    function updateGroupDraft(field: string, value: unknown): void {
        if (field === 'name') {
            groupDraft.names = { ...(value as Record<string, string>) };
        } else if (field === 'icon') {
            groupDraft.icon = value as string | null;
        }
    }

    function saveNewGroup(): void {
        if (savingGroup.value) {
            return;
        }

        savingGroup.value = true;
        groupErrors.value = {};

        router.post(
            '/admin/skill-groups',
            {
                icon: groupDraft.icon,
                name: { ...groupDraft.names },
                sort_order: groups.value.length,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    savingGroup.value = false;
                },
                onError: (serverErrors) => {
                    groupErrors.value = { ...serverErrors };
                    notifyIncompleteTranslations(serverErrors);
                },
            },
        );
    }

    function saveGroup(id: number): void {
        if (savingGroup.value) {
            return;
        }

        savingGroup.value = true;
        groupErrors.value = {};

        router.put(
            `/admin/skill-groups/${id}`,
            {
                icon: groupDraft.icon,
                name: { ...groupDraft.names },
                sort_order: groups.value.find((group) => group.id === id)?.sort_order ?? 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (editingGroupId.value === id) {
                        editingGroupId.value = null;
                    }
                },
                onFinish: () => {
                    savingGroup.value = false;
                },
                onError: (serverErrors) => {
                    groupErrors.value = { ...serverErrors };
                    notifyIncompleteTranslations(serverErrors);
                },
            },
        );
    }

    function moveGroup(id: number, direction: -1 | 1): void {
        const index = groups.value.findIndex((group) => group.id === id);
        const next = move(groups.value, index, direction);

        if (next === null) {
            return;
        }

        groups.value = next;

        persistGroupOrder();
    }

    function notifyIncompleteTranslations(serverErrors: Record<string, string>): void {
        for (const locale of localesWithErrors(serverErrors)) {
            toast.show(
                t('admin.toasts.translation_incomplete', {
                    locale: t(`admin.locales.${locale}`),
                }),
                { variant: 'error' },
            );
        }
    }

    return {
        editingGroupId,
        editingGroupLocale,
        groupDraft,
        groupErrors,
        savingGroup,
        warnedGroupLocales,
        groupNameError,
        startCreateGroup,
        startEditGroupById,
        cancelGroupEdit,
        updateGroupDraft,
        saveNewGroup,
        saveGroup,
        moveGroup,
    };
}
