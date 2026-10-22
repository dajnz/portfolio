<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

import type { SectionRow } from './types';

const props = defineProps<{
    row: SectionRow;
    label: string;
    can_move_up: boolean;
    can_move_down: boolean;
    test_id: string;
}>();

const emit = defineEmits<{
    toggle: [visible: boolean];
    'move-up': [];
    'move-down': [];
}>();

const { t } = useI18n();
</script>

<template>
  <div
    class="flex items-center gap-3 bg-white px-4 py-3 dark:bg-surface-dark-secondary"
    :class="props.row.visible ? '' : 'opacity-50'"
    :data-testid="`${props.test_id}-row-${props.row.id}`"
  >
    <span
      class="hidden cursor-grab text-sm text-slate-300 sm:block dark:text-slate-600"
      aria-hidden="true"
    >⠿</span>
    <AdminBaseCheckbox
      :model-value="props.row.visible"
      :label="props.label"
      class="flex-1 select-none"
      :test_id="`${props.test_id}-visible-${props.row.id}`"
      @update:model-value="emit('toggle', $event)"
    />
    <div class="flex shrink-0 items-center gap-1">
      <AdminBaseButton
        variant="icon-only"
        icon="heroicons:arrow-up"
        :disabled="!props.can_move_up"
        :aria-label="t('common.move_item_up', { item_name: props.label })"
        :test_id="`${props.test_id}-up-${props.row.id}`"
        @click="emit('move-up')"
      />
      <AdminBaseButton
        variant="icon-only"
        icon="heroicons:arrow-down"
        :disabled="!props.can_move_down"
        :aria-label="t('common.move_item_down', { item_name: props.label })"
        :test_id="`${props.test_id}-down-${props.row.id}`"
        @click="emit('move-down')"
      />
    </div>
  </div>
</template>
