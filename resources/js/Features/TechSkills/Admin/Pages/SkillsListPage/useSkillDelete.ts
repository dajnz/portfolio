import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

// SkillsListPage — shared delete-confirmation flow (SkillsListPage.vue is
// the orchestrator): one ConfirmationDialog serves both groups and skills.
export interface DeleteTarget {
    type: 'group' | 'skill';
    id: number;
}

export function useSkillDelete() {
    const deleteTarget = ref<DeleteTarget | null>(null);
    const isDeleting = ref(false);

    function requestDelete(type: DeleteTarget['type'], id: number): void {
        deleteTarget.value = { type, id };
    }

    function dismissDelete(): void {
        deleteTarget.value = null;
    }

    function confirmDelete(): void {
        if (deleteTarget.value === null || isDeleting.value) {
            return;
        }

        isDeleting.value = true;
        const target = deleteTarget.value;

        router.delete(
            target.type === 'group'
                ? `/admin/skill-groups/${target.id}`
                : `/admin/skills/${target.id}`,
            {
                preserveScroll: true,
                onFinish: () => {
                    isDeleting.value = false;
                    deleteTarget.value = null;
                },
            },
        );
    }

    return {
        deleteTarget,
        isDeleting,
        requestDelete,
        dismissDelete,
        confirmDelete,
    };
}
