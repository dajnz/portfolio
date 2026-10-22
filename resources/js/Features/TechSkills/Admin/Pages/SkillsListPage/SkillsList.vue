<script setup lang="ts">
import SortableList, {
    type CrossDragInfo,
} from '@shared-cross/Components/SortableList/SortableList.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { SkillGroupData } from '@techskills-admin/SkillGroupCard.vue';
import SkillItem, { type SkillRow } from '@techskills-admin/SkillItem.vue';
import { computed } from 'vue';

import NewSkillRow from './NewSkillRow.vue';
import type { NewSkillDraft, ProficiencyOption, SkillDraft } from './types';

const props = withDefaults(
    defineProps<{
        group: SkillGroupData;
        editing_skill_id: number | 'new' | null;
        skill_draft: SkillDraft;
        new_skill_draft: NewSkillDraft;
        skill_errors?: Record<string, string>;
        saving_skill?: boolean;
        proficiency_options?: ProficiencyOption[];
        test_id?: string;
    }>(),
    {
        skill_errors: () => ({}),
        saving_skill: false,
        proficiency_options: () => [],
        test_id: 'skills-list-page',
    },
);

const emit = defineEmits<{
    update: [id: number, field: string, value: unknown];
    edit: [id: number];
    save: [id: number];
    cancel: [id: number];
    delete: [id: number];
    'move-up': [id: number];
    'move-down': [id: number];
    'update:skills': [skills: Array<Record<string, unknown>>];
    'drag-end': [skills: Array<Record<string, unknown>>, cross: CrossDragInfo | null | undefined];
    'new-update': [field: string, value: unknown];
    'new-save': [];
    'new-cancel': [];
}>();

const { t } = useI18n();

const isNewSkillEditorOpen = computed(() => props.editing_skill_id === 'new');

function isEditingSkill(skillId: number): boolean {
    return props.editing_skill_id === skillId;
}
</script>

<template>
  <!-- Skills within the group — same cross_scope so skills can be dragged
       between groups (the payload carries each skill's new group). The
       whole row is the drag surface (`interactive_selector` keeps the
       row's controls out of drags); the container carries no padding
       (the SortableList's scoped `padding: 0` would override Tailwind's
       px-4) — the rows own their px-4. The min-height keeps an empty
       group's list a visible cross-group drop target. -->
  <SortableList
    :model-value="props.group.skills"
    item_key="id"
    interactive_selector=".skill-row-interactive"
    :list_id="`skills-${props.group.id}`"
    cross_scope="skills"
    :classes="{
      container: 'min-h-12 divide-y divide-slate-100 dark:divide-slate-700',
    }"
    :test_id="`${props.test_id}-skills-${props.group.id}`"
    @update:model-value="(skills) => emit('update:skills', skills)"
    @drag-end="(skills, cross) => emit('drag-end', skills, cross)"
  >
    <template #default="{ item: skill, index: skillIndex }">
      <SkillItem
        :skill="skill as SkillRow"
        :index="skillIndex"
        :editing="isEditingSkill(Number(skill.id))"
        :can_move_up="skillIndex > 0"
        :can_move_down="skillIndex < props.group.skills.length - 1"
        :draft_name="
          isEditingSkill(Number(skill.id)) ? props.skill_draft.name : null
        "
        :draft_icon="
          isEditingSkill(Number(skill.id)) ? props.skill_draft.icon : null
        "
        :draft_proficiency="
          isEditingSkill(Number(skill.id)) ? props.skill_draft.proficiency : null
        "
        :error_name="props.skill_errors.name ?? null"
        :is_saving="props.saving_skill"
        :test_id="props.test_id"
        @update="
          (payload) => emit('update', payload.id, payload.field, payload.value)
        "
        @edit="(id) => emit('edit', id)"
        @save="(id) => emit('save', id)"
        @cancel="(id) => emit('cancel', id)"
        @delete="(id) => emit('delete', id)"
        @move-up="(id) => emit('move-up', id)"
        @move-down="(id) => emit('move-down', id)"
      />
    </template>

    <!-- Empty-group hint INSIDE the drop target — it is part of the <ul>,
         so cross-group drops land on it, and no dead gap appears above
         it. -->
    <template #empty>
      <p
        v-if="!isNewSkillEditorOpen"
        class="px-4 py-3 text-center text-sm text-slate-400 dark:text-slate-500"
        :data-testid="`${props.test_id}-group-${props.group.id}-empty`"
      >
        {{ t('admin.skills.empty_group') }}
      </p>
    </template>
  </SortableList>

  <!-- Inline new-skill editor row at the BOTTOM of the group — the saved
       skill lands at the same place it was created (no jump). One editor
       per group, rendered AFTER the list, so it also appears for empty
       groups. -->
  <NewSkillRow
    v-if="isNewSkillEditorOpen"
    :group_id="props.group.id"
    :draft="props.new_skill_draft"
    :errors="props.skill_errors"
    :saving="props.saving_skill"
    :proficiency_options="props.proficiency_options"
    :test_id="props.test_id"
    @update="(field, value) => emit('new-update', field, value)"
    @save="emit('new-save')"
    @cancel="emit('new-cancel')"
  />
</template>
