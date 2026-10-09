<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

import AdminBaseButton from './AdminBaseButton.vue';

type EmptyStateMode = 'empty' | 'no-results' | 'error';

const props = withDefaults(
    defineProps<{
        mode?: EmptyStateMode;
        message?: string;
        icon?: string;
        action_label?: string | null;
        action_url?: string | null;
        test_id?: string;
    }>(),
    {
        mode: 'empty',
        message: undefined,
        icon: undefined,
        action_label: null,
        action_url: null,
        test_id: 'empty-state',
    },
);

const emit = defineEmits<{ action: [] }>();

const DEFAULT_ICONS: Record<EmptyStateMode, string> = {
    empty: 'heroicons:folder-open',
    'no-results': 'heroicons:magnifying-glass',
    error: 'heroicons:exclamation-triangle',
};

const resolvedIcon = computed(() => props.icon ?? DEFAULT_ICONS[props.mode]);
const hasAction = computed(() => props.action_label !== null);
const actionIsLink = computed(() => props.action_url !== null);

function onAction(): void {
    emit('action');
}
</script>

<template>
  <div
    class="flex flex-col items-center justify-center py-16 px-4 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/30"
    role="status"
    aria-live="polite"
    :data-testid="props.test_id"
  >
    <span
      class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-300 dark:text-slate-600 mb-4"
      aria-hidden="true"
    >
      <Icon
        class="w-8 h-8"
        :icon="resolvedIcon"
      />
    </span>
    <p class="text-base font-semibold text-slate-500 dark:text-slate-400 mb-4 text-center">
      {{ props.message }}
    </p>
    <div
      v-if="hasAction"
      class="mt-4"
    >
      <AdminBaseButton
        v-if="actionIsLink"
        :tag="'a'"
        :href="props.action_url ?? undefined"
        variant="primary"
        :test_id="`${props.test_id}-action`"
      >
        {{ props.action_label }}
      </AdminBaseButton>
      <AdminBaseButton
        v-else
        variant="primary"
        :test_id="`${props.test_id}-action`"
        @click="onAction"
      >
        {{ props.action_label }}
      </AdminBaseButton>
    </div>
    <div class="text-sm text-slate-400 dark:text-slate-500 mb-4">
      <slot />
    </div>
  </div>
</template>
