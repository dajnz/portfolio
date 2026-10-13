<script setup lang="ts">
import { Icon } from '@iconify/vue';

type ImagePlaceholderVariant = 'patterned' | 'plain';

const props = withDefaults(
    defineProps<{
        variant?: ImagePlaceholderVariant;
        test_id?: string;
    }>(),
    {
        variant: 'patterned',
        test_id: 'image-placeholder',
    },
);
</script>

<template>
  <div
    class="image-placeholder"
    :class="`image-placeholder--${props.variant}`"
    aria-hidden="true"
    :data-testid="props.test_id"
  >
    <Icon
      v-if="props.variant === 'patterned'"
      class="image-placeholder__icon"
      icon="heroicons:photo"
      aria-hidden="true"
    />
  </div>
</template>

<style scoped>
.image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;

    /* Adopt the framing element's corner radius so the placeholder never
       paints square corners over a rounded image slot (which would leak
       when the slot does not clip its children). */
    border-radius: inherit;
}

.image-placeholder--patterned {
    background:
        linear-gradient(135deg, var(--color-bg-tertiary) 25%, transparent 25%) 0 0 / 16px 16px,
        var(--color-bg-secondary);
    border: 1px dashed var(--color-border-strong);
}

.image-placeholder--plain {
    background: var(--color-bg-tertiary);
}

.image-placeholder__icon {
    width: 5.25em;
    height: 5.25em;
    color: var(--color-text-tertiary);
    opacity: 0.5;
}
</style>
