<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';

import AdminBaseButton from './AdminBaseButton.vue';

type BulkActionVariant = 'primary' | 'secondary' | 'ghost' | 'icon-only' | 'outline' | 'danger';

type BulkAction = { label: string; key: string; variant?: BulkActionVariant };

const props = withDefaults(
    defineProps<{
        selected_count: number;
        actions?: Array<BulkAction>;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        actions: () => [],
        disabled: false,
        test_id: 'bulk-action-bar',
    },
);

const emit = defineEmits<{ action: [key: string]; clear: [] }>();

const { t } = useI18n();

function onAction(key: string): void {
    emit('action', key);
}

function onClear(): void {
    emit('clear');
}
</script>

<template>
  <div
    v-if="props.selected_count > 0"
    class="flex items-center justify-between gap-3 px-4 py-2.5 bg-primary-50 dark:bg-primary-950 border border-primary-100 dark:border-primary-900 rounded-lg mb-3"
    :class="{ 'opacity-60': props.disabled }"
    :data-testid="props.test_id"
  >
    <span class="sr-only text-sm font-semibold text-primary-700 dark:text-primary-300 sm:not-sr-only">
      {{ t('admin.lists.items_selected', { count: props.selected_count }) }}
    </span>
    <div
      class="thin-scrollbar flex min-w-0 items-center gap-2 overflow-x-auto sm:overflow-visible"
    >
      <AdminBaseButton
        variant="ghost"
        size="sm"
        :disabled="props.disabled"
        :test_id="`${props.test_id}-clear`"
        @click="onClear"
      >
        {{ t('common.clear_selection') }}
      </AdminBaseButton>
      <AdminBaseButton
        v-for="action in props.actions"
        :key="action.key"
        size="sm"
        :variant="action.variant ?? 'secondary'"
        :disabled="props.disabled"
        :test_id="`${props.test_id}-action-${action.key}`"
        @click="onAction(action.key)"
      >
        {{ action.label }}
      </AdminBaseButton>
    </div>
  </div>
</template>
