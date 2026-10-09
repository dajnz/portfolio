<script setup lang="ts">
import { Icon } from '@iconify/vue';

export interface EngineeringDetailData {
    id: number;
    icon: string | null;
    title: string;
    description: string;
}

const props = withDefaults(
    defineProps<{
        detail?: EngineeringDetailData | null;
        test_id?: string;
    }>(),
    {
        detail: null,
        test_id: 'engineering-detail-item',
    },
);
</script>

<template>
  <li
    class="detail-item"
    :class="{ 'detail-item--no-icon': props.detail?.icon === null }"
    :data-testid="props.test_id"
  >
    <span
      v-if="props.detail?.icon"
      class="detail-item__icon"
      :data-icon="props.detail.icon"
    >
      <Icon
        class="detail-item__icon-image"
        :icon="props.detail.icon"
        aria-hidden="true"
      />
    </span>
    <div
      v-if="props.detail !== null"
      class="detail-item__body"
    >
      <h3 class="detail-item__title">
        {{ props.detail.title }}
      </h3>
      <p class="detail-item__description">
        {{ props.detail.description }}
      </p>
    </div>
  </li>
</template>

<style scoped>
.detail-item {
    display: flex;
    gap: var(--space-md);
    align-items: flex-start;
}

.detail-item__icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: var(--radius-md);
    background: var(--color-accent-muted);
    color: var(--color-accent);
}

.detail-item__icon-image {
    width: 1.2em;
    height: 1.2em;
}

.detail-item__title {
    font-size: var(--font-size-base);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-xs);
}

.detail-item__description {
    font-size: var(--font-size-sm);
    line-height: var(--line-height-normal);
    color: var(--color-text-secondary);
    margin: 0;
}
</style>
