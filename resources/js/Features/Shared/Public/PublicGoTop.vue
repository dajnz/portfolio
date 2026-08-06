<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReducedMotion } from '@shared-cross/Composables/useReducedMotion';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        threshold?: number;
        label?: string;
        smooth?: boolean;
        test_id?: string;
    }>(),
    {
        threshold: 300,
        label: undefined,
        smooth: true,
        test_id: 'go-top',
    },
);

const { t } = useI18n();
const { prefersReduced } = useReducedMotion();

const isVisible = ref(false);

function onScroll(): void {
    isVisible.value = window.scrollY > props.threshold;
}

function scrollToTop(): void {
    window.scrollTo({
        top: 0,
        behavior: props.smooth && !prefersReduced.value ? 'smooth' : 'auto',
    });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
  <button
    type="button"
    class="public-go-top"
    :class="{ 'is-hidden': !isVisible }"
    :aria-label="props.label ?? t('common.scroll_to_top')"
    :data-testid="props.test_id"
    @click="scrollToTop"
  >
    <Icon
      class="public-go-top__icon"
      icon="heroicons:arrow-up"
      aria-hidden="true"
    />
  </button>
</template>

<style scoped>
.public-go-top {
    position: fixed;
    bottom: var(--space-xl);
    right: var(--space-xl);
    z-index: var(--z-sticky);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    cursor: pointer;
    color: var(--color-text-secondary);
    font-size: var(--font-size-lg);
    box-shadow: var(--shadow-md);
    transition: all var(--transition-fast);
}

.public-go-top:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.public-go-top.is-hidden {
    opacity: 0;
    visibility: hidden;
}

.public-go-top__icon {
    width: 1.1em;
    height: 1.1em;
}
</style>
