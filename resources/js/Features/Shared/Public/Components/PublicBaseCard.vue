<script setup lang="ts">
import { computed } from 'vue';

type CardPadding = 'none' | 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        padding?: CardPadding;
        hoverable?: boolean;
        test_id?: string;
    }>(),
    {
        padding: 'md',
        hoverable: false,
        test_id: 'base-card',
    },
);

const cardClass = computed(() => ({
    'card--hoverable': props.hoverable,
    [`card--padding-${props.padding}`]: props.padding !== 'md',
}));
</script>

<template>
  <div
    class="card"
    :class="cardClass"
    :data-testid="props.test_id"
  >
    <div
      v-if="$slots.header"
      class="card__header"
    >
      <slot name="header" />
    </div>
    <div class="card__body">
      <slot />
    </div>
    <div
      v-if="$slots.footer"
      class="card__footer"
    >
      <slot name="footer" />
    </div>
  </div>
</template>

<style scoped>
.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    padding: var(--card-padding);
    box-shadow: var(--card-shadow);
    transition: var(--card-transition);
    border-top: 4px solid var(--color-accent-3);
}

.card--hoverable:hover {
    transform: translateY(-3px);
    box-shadow: var(--card-shadow-hover);
}

.card--padding-none {
    --card-padding: 0;
}

.card--padding-sm {
    --card-padding: var(--space-sm);
}

.card--padding-lg {
    --card-padding: var(--space-xl);
}

.card__header {
    margin-bottom: var(--space-md);
}

.card__footer {
    margin-top: var(--space-lg);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--color-border);
}
</style>
