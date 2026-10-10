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
    class="fixed bottom-2 right-2 z-sticky w-10 h-10 inline-flex items-center justify-center rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow-card transition-all cursor-pointer"
    :class="{ 'opacity-0 pointer-events-none translate-y-2': !isVisible }"
    :aria-label="props.label ?? t('common.scroll_to_top')"
    :data-testid="props.test_id"
    @click="scrollToTop"
  >
    <Icon
      class="w-5 h-5"
      icon="heroicons:arrow-up"
      aria-hidden="true"
    />
  </button>
</template>
