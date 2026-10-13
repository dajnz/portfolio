<!-- Sticky page action bar pinned below the admin header; its bottom border
     and edge fades appear only while stuck (rAF-throttled scroll listener). -->
<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        test_id?: string;
    }>(),
    {
        test_id: 'admin-sticky-action-bar',
    },
);

const barEl = ref<HTMLElement | null>(null);
const isStuck = ref(false);
let scrollTicking = false;

function updateStuckState(): void {
    scrollTicking = false;
    isStuck.value = (barEl.value?.getBoundingClientRect().top ?? 0) <= 65;
}

function onScroll(): void {
    if (scrollTicking) {
        return;
    }

    scrollTicking = true;
    requestAnimationFrame(updateStuckState);
}

onMounted(() => {
    updateStuckState();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
  <div
    ref="barEl"
    class="sticky top-[65px] z-sticky -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-4 py-3 dark:bg-surface-dark sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
    :class="[
      isStuck
        ? 'border-b border-slate-200/70 dark:border-slate-700/70 before:pointer-events-none before:absolute before:inset-y-0 before:left-0 before:w-4 before:bg-gradient-to-r before:from-transparent before:to-slate-50 sm:before:w-6 lg:before:w-8 dark:before:to-surface-dark after:pointer-events-none after:absolute after:inset-y-0 after:right-0 after:w-4 sm:after:w-6 lg:after:w-8 after:bg-gradient-to-l after:from-transparent after:to-slate-50 dark:after:to-surface-dark'
        : '',
    ]"
    :data-testid="props.test_id"
  >
    <slot />
  </div>
</template>
