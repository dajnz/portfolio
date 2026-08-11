<!-- AdminStickyActionBar (admin-section-ui.md, B174) — the sticky header action bar for admin pages: primary controls (create buttons, filters, locale tabs, save/cancel) stay visible while scrolling, pinned a few px below the fixed admin header (h-16). The bar's bottom border + edge fades appear only while stuck (rAF-throttled scroll listener, same behavior as the AboutEditPage action bar it was extracted from). Children are spread with `justify-between` — the page composes its own groups (e.g. back link + locale tabs on the left, save/cancel on the right). -->
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
        ? 'border-b border-slate-200/70 dark:border-slate-700/70 before:pointer-events-none before:absolute before:inset-y-0 before:left-0 before:w-5 before:bg-gradient-to-r before:from-transparent before:to-slate-50 dark:before:to-surface-dark after:pointer-events-none after:absolute after:inset-y-0 after:right-0 after:w-5 after:bg-gradient-to-l after:from-transparent after:to-slate-50 dark:after:to-surface-dark'
        : '',
    ]"
    :data-testid="props.test_id"
  >
    <slot />
  </div>
</template>
