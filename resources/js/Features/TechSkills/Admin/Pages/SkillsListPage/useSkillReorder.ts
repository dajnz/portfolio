import { router } from '@inertiajs/vue3';
import type { CrossDragInfo } from '@shared-cross/Components/SortableList/SortableList.vue';
import type { SkillGroupData } from '@techskills-admin/SkillGroupCard.vue';
import type { SkillRow } from '@techskills-admin/SkillItem.vue';
import type { Ref } from 'vue';

// SkillsListPage — reorder persistence (SkillsListPage.vue is the
// orchestrator). Applies the SortableList drag results to the page's
// shared `groups` working copy and posts the payloads
// (`{ "skills": [{ "id", "skill_group_id" }] }` — the zero-indexed array
// position becomes `sort_order`; groups use `{ ids }`), rolling back to
// the original props snapshot on server errors.
export function useSkillReorder(
    groups: Ref<SkillGroupData[]>,
    originalGroups: () => SkillGroupData[],
) {
    function setGroupSkills(groupId: number, skills: Array<Record<string, unknown>>): void {
        const group = groups.value.find((row) => row.id === groupId);

        if (group !== undefined) {
            group.skills = skills as SkillRow[];
        }
    }

    function onSkillsDragEnd(
        groupId: number,
        skills: Array<Record<string, unknown>>,
        cross: CrossDragInfo | null | undefined,
    ): void {
        setGroupSkills(groupId, skills);

        // Cross-group drop: the target list already inserted the moved
        // item (shared object reference); drop it from the source group's
        // list.
        if (cross !== null && cross !== undefined) {
            const fromGroupId = Number(cross.from_list_id.replace('skills-', ''));
            const source = groups.value.find((row) => row.id === fromGroupId);

            if (source !== undefined) {
                source.skills = source.skills.filter((row) => row.id !== Number(cross.item.id));
            }
        }

        persistSkillOrder();
    }

    function persistSkillOrder(): void {
        const skills = groups.value.flatMap((group) =>
            group.skills.map((skill) => ({ id: skill.id, skill_group_id: group.id })),
        );

        if (skills.length === 0) {
            return;
        }

        router.post(
            '/admin/skills/reorder',
            { skills },
            {
                preserveScroll: true,
                onError: rollback,
            },
        );
    }

    function persistGroupOrder(): void {
        const ids = groups.value.map((group) => group.id);

        if (ids.length === 0) {
            return;
        }

        router.post(
            '/admin/skill-groups/reorder',
            { ids },
            {
                preserveScroll: true,
                onError: rollback,
            },
        );
    }

    function rollback(): void {
        groups.value = originalGroups().map((group) => ({
            ...group,
            skills: [...group.skills],
        }));
    }

    return {
        setGroupSkills,
        onSkillsDragEnd,
        persistSkillOrder,
        persistGroupOrder,
    };
}
