<!-- AchievementList (admin-section-components.md:1102-1109, experience-form.html) — the dynamic achievements editor: SortableList of AchievementItem rows (drag reorder + keyboard move up/down alternatives per accessibility.md:37-44) with an "Add Achievement" button. Emits the reordered/modified list via `update:modelValue`; `add`/`remove` per the contract. The description is per-locale (the active LocaleTabs locale is passed down). -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import SortableList from '@shared-cross/Components/SortableList.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { AchievementRow } from '../Types/achievement';

import AchievementItem from './AchievementItem.vue';

const props = withDefaults(
    defineProps<{
        modelValue: AchievementRow[];
        locale: string;
        error?: string | null;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        error: null,
        disabled: false,
        test_id: 'achievement-list',
    },
);

const emit = defineEmits<{
    'update:modelValue': [rows: AchievementRow[]];
    add: [];
    remove: [index: number];
}>();

const { t } = useI18n();

function setDescription(index: number, value: string): void {
    const next = [...props.modelValue];
    next[index] = {
        ...next[index],
        description: { ...next[index].description, [props.locale]: value },
    };
    emit('update:modelValue', next);
}

function setProjectLink(index: number, value: string): void {
    const next = [...props.modelValue];
    next[index] = { ...next[index], project_link: value === '' ? null : value };
    emit('update:modelValue', next);
}

function removeAt(index: number): void {
    emit('remove', index);
}

function move(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.modelValue.length) {
        return;
    }

    const next = [...props.modelValue];
    const [moved] = next.splice(index, 1);

    if (moved === undefined) {
        return;
    }

    next.splice(target, 0, moved);
    emit('update:modelValue', next);
}
</script>

<template>
  <div
    :data-component="'achievement-list'"
    :data-testid="props.test_id"
  >
    <span class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
      {{ t('admin.experience.achievements') }}
    </span>
    <SortableList
      :model-value="props.modelValue"
      item_key="uid"
      handle=".achievement-handle"
      :disabled="props.disabled"
      :classes="{
        container: 'space-y-3',
      }"
      :test_id="`${props.test_id}-sortable`"
      @update:model-value="(rows) => emit('update:modelValue', rows as AchievementRow[])"
    >
      <template #default="{ item, index }">
        <AchievementItem
          :model-value="item as AchievementRow"
          :locale="props.locale"
          :index="index"
          :can_move_up="index > 0"
          :can_move_down="index < props.modelValue.length - 1"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-item-${index}`"
          @update:description="(value) => setDescription(index, value)"
          @update:project-link="(value) => setProjectLink(index, value)"
          @remove="removeAt(index)"
          @move-up="move(index, -1)"
          @move-down="move(index, 1)"
        />
      </template>
    </SortableList>
    <AdminBaseButton
      type="button"
      variant="secondary"
      size="sm"
      icon="heroicons:plus"
      icon_position="left"
      :disabled="props.disabled"
      :test_id="`${props.test_id}-add`"
      class="mt-3"
      @click="emit('add')"
    >
      {{ t('admin.experience.add_achievement') }}
    </AdminBaseButton>
    <p
      v-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
  </div>
</template>
