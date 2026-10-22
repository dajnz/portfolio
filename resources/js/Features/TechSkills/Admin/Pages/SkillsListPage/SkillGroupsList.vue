<script setup lang="ts">
import SortableList, {
    type CrossDragInfo,
} from '@shared-cross/Components/SortableList/SortableList.vue';
import type { SkillGroupData } from '@techskills-admin/SkillGroupCard.vue';
import SkillGroupCard from '@techskills-admin/SkillGroupCard.vue';

import SkillsList from './SkillsList.vue';
import type { NewSkillDraft, ProficiencyOption, SkillDraft, SkillEditorTarget } from './types';

const props = withDefaults(
    defineProps<{
        groups: SkillGroupData[];
        editing_group_id: number | 'new' | null;
        group_draft_names: Record<string, string>;
        group_draft_icon: string | null;
        group_errors?: Record<string, string>;
        saving_group?: boolean;
        editing_skill: SkillEditorTarget | null;
        skill_draft: SkillDraft;
        new_skill_draft: NewSkillDraft;
        skill_errors?: Record<string, string>;
        saving_skill?: boolean;
        proficiency_options?: ProficiencyOption[];
        test_id?: string;
    }>(),
    {
        group_errors: () => ({}),
        saving_group: false,
        skill_errors: () => ({}),
        saving_skill: false,
        proficiency_options: () => [],
        test_id: 'skills-list-page',
    },
);

const emit = defineEmits<{
    'update:groups': [groups: SkillGroupData[]];
    'group-drag-end': [];
    'update-group': [field: string, value: unknown];
    'edit-group': [id: number];
    'save-group': [id: number];
    'cancel-group': [id: number];
    'delete-group': [id: number];
    'move-group': [id: number, direction: -1 | 1];
    'add-skill': [groupId: number];
    'update-skill': [groupId: number, id: number, field: string, value: unknown];
    'edit-skill': [groupId: number, id: number];
    'save-skill': [groupId: number, id: number];
    'cancel-skill': [groupId: number, id: number];
    'delete-skill': [id: number];
    'move-skill': [groupId: number, id: number, direction: -1 | 1];
    'update-skills': [groupId: number, skills: Array<Record<string, unknown>>];
    'skills-drag-end': [
        groupId: number,
        skills: Array<Record<string, unknown>>,
        cross: CrossDragInfo | null | undefined,
    ];
    'new-skill-update': [field: string, value: unknown];
    'new-skill-save': [groupId: number];
    'new-skill-cancel': [groupId: number];
}>();

function editingSkillId(group: SkillGroupData): number | 'new' | null {
    if (props.editing_skill?.groupId !== group.id) {
        return null;
    }

    return props.editing_skill.skillId;
}
</script>

<template>
  <SortableList
    :model-value="props.groups"
    item_key="id"
    handle=".group-card-handle"
    :classes="{ container: 'space-y-4' }"
    :test_id="`${props.test_id}-groups-sortable`"
    @update:model-value="(groups) => emit('update:groups', groups as SkillGroupData[])"
    @drag-end="emit('group-drag-end')"
  >
    <template #default="{ item, index }">
      <SkillGroupCard
        :group="item as SkillGroupData"
        :index="index"
        :editing="props.editing_group_id === (item as SkillGroupData).id"
        :draft_names="
          props.editing_group_id === (item as SkillGroupData).id
            ? props.group_draft_names
            : null
        "
        :draft_icon="
          props.editing_group_id === (item as SkillGroupData).id
            ? props.group_draft_icon
            : null
        "
        :error_names="props.group_errors"
        :is_saving="props.saving_group"
        :can_move_up="index > 0"
        :can_move_down="index < props.groups.length - 1"
        :test_id="props.test_id"
        @update-group="(payload) => emit('update-group', payload.field, payload.value)"
        @edit-group="(id) => emit('edit-group', id)"
        @save-group="(id) => emit('save-group', id)"
        @cancel-group="(id) => emit('cancel-group', id)"
        @delete-group="(id) => emit('delete-group', id)"
        @move-up="(id) => emit('move-group', id, -1)"
        @move-down="(id) => emit('move-group', id, 1)"
        @add-skill="(groupId) => emit('add-skill', groupId)"
      >
        <SkillsList
          :group="item as SkillGroupData"
          :editing_skill_id="editingSkillId(item as SkillGroupData)"
          :skill_draft="props.skill_draft"
          :new_skill_draft="props.new_skill_draft"
          :skill_errors="props.skill_errors"
          :saving_skill="props.saving_skill"
          :proficiency_options="props.proficiency_options"
          :test_id="props.test_id"
          @update="
            (id, field, value) =>
              emit('update-skill', (item as SkillGroupData).id, id, field, value)
          "
          @edit="(id) => emit('edit-skill', (item as SkillGroupData).id, id)"
          @save="(id) => emit('save-skill', (item as SkillGroupData).id, id)"
          @cancel="(id) => emit('cancel-skill', (item as SkillGroupData).id, id)"
          @delete="(id) => emit('delete-skill', id)"
          @move-up="(id) => emit('move-skill', (item as SkillGroupData).id, id, -1)"
          @move-down="(id) => emit('move-skill', (item as SkillGroupData).id, id, 1)"
          @update:skills="
            (skills) => emit('update-skills', (item as SkillGroupData).id, skills)
          "
          @drag-end="
            (skills, cross) =>
              emit('skills-drag-end', (item as SkillGroupData).id, skills, cross)
          "
          @new-update="(field, value) => emit('new-skill-update', field, value)"
          @new-save="emit('new-skill-save', (item as SkillGroupData).id)"
          @new-cancel="emit('new-skill-cancel', (item as SkillGroupData).id)"
        />
      </SkillGroupCard>
    </template>
  </SortableList>
</template>
