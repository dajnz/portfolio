<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

import PublicBaseButton from './PublicBaseButton.vue';

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
    class="empty-state"
    :class="`empty-state--${props.mode}`"
    role="status"
    aria-live="polite"
    :data-testid="props.test_id"
  >
    <span
      class="empty-state__icon"
      aria-hidden="true"
    >
      <Icon
        class="empty-state__icon-image"
        :icon="resolvedIcon"
      />
    </span>
    <p class="empty-state__message">
      {{ props.message }}
    </p>
    <div
      v-if="hasAction"
      class="empty-state__action"
    >
      <PublicBaseButton
        v-if="actionIsLink"
        :tag="'a'"
        :href="props.action_url ?? undefined"
        variant="outline"
        :test_id="`${props.test_id}-action`"
      >
        {{ props.action_label }}
      </PublicBaseButton>
      <PublicBaseButton
        v-else
        variant="outline"
        :test_id="`${props.test_id}-action`"
        @click="onAction"
      >
        {{ props.action_label }}
      </PublicBaseButton>
    </div>
    <slot />
  </div>
</template>

<style scoped>
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--space-3xl) var(--space-lg);
    text-align: center;
}

.empty-state__icon {
    width: 64px;
    height: 64px;
    border-radius: var(--radius-full);
    background: var(--color-bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-2xl);
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-lg);
}

.empty-state__icon-image {
    width: 1.5em;
    height: 1.5em;
}

.empty-state__message {
    font-size: var(--font-size-lg);
    color: var(--color-text-secondary);
    font-weight: var(--font-medium);
    max-width: 360px;
    line-height: var(--line-height-relaxed);
}

.empty-state--error .empty-state__message {
    color: var(--color-error);
}

.empty-state__action {
    margin-top: var(--space-lg);
}
</style>
