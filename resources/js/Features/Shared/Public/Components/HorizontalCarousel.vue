<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReducedMotion } from '@shared-cross/Composables/useReducedMotion';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        items: unknown[];
        item_key?: string;
        show_arrows?: boolean;
        show_dots?: boolean;
        auto_advance_ms?: number | null;
        gap?: string;
        slides_per_view?: number;
        arrows_on_hover?: boolean;
        compact_dots?: boolean;
        test_id?: string;
    }>(),
    {
        item_key: 'id',
        show_arrows: true,
        show_dots: false,
        auto_advance_ms: null,
        gap: '16px',
        slides_per_view: 1,
        arrows_on_hover: false,
        compact_dots: false,
        test_id: 'carousel',
    },
);

const emit = defineEmits<{ change: [index: number] }>();

const { t } = useI18n();
const { prefersReduced } = useReducedMotion();

const SWIPE_THRESHOLD_PX = 30;
const FLICK_THRESHOLD_PX = 8;
const FLICK_MAX_DURATION_MS = 300;

const trackEl = ref<HTMLElement | null>(null);
const rootEl = ref<HTMLElement | null>(null);
const activeIndex = ref(0);
const paused = ref(false);

let scrollFrame: number | null = null;
let autoAdvanceInterval: ReturnType<typeof setInterval> | null = null;
let pointerStartX = 0;
let pointerStartY = 0;
let pointerStartTime = 0;

const totalSlides = computed(() => props.items.length);
const viewportStyle = computed(() => ({
    '--carousel-gap': props.gap,
    '--carousel-slides': props.slides_per_view,
}));
const rootClass = computed(() => ({
    'carousel--hover-arrows': props.arrows_on_hover,
    'carousel--compact-dots': props.compact_dots,
}));

function gapPixels(): number {
    if (trackEl.value === null) {
        return 0;
    }

    const raw = getComputedStyle(trackEl.value).getPropertyValue('--carousel-gap').trim();

    return raw === '' ? 0 : Number.parseFloat(raw);
}

function itemKey(item: unknown): string {
    const value = (item as Record<string, unknown>)[props.item_key];

    return String(value ?? '');
}

function stepPixels(): number {
    if (trackEl.value === null) {
        return 1;
    }

    return (trackEl.value.clientWidth + gapPixels()) / props.slides_per_view;
}

function updateActiveFromScroll(): void {
    if (trackEl.value === null || totalSlides.value === 0) {
        return;
    }

    const next = Math.min(
        totalSlides.value - 1,
        Math.max(0, Math.round(trackEl.value.scrollLeft / stepPixels())),
    );

    activeIndex.value = next;
}

function onScroll(): void {
    if (scrollFrame !== null) {
        return;
    }

    scrollFrame = requestAnimationFrame(() => {
        scrollFrame = null;
        updateActiveFromScroll();
    });
}

function scrollToSlide(index: number): void {
    if (trackEl.value === null) {
        return;
    }

    const target = Math.min(totalSlides.value - 1, Math.max(0, index));

    trackEl.value.scrollTo({
        left: target * stepPixels(),
        behavior: prefersReduced.value ? 'auto' : 'smooth',
    });
}

function goTo(index: number): void {
    if (totalSlides.value === 0) {
        return;
    }

    scrollToSlide(index);
    activeIndex.value = Math.min(totalSlides.value - 1, Math.max(0, index));
}

function prev(): void {
    goTo(activeIndex.value - 1);
}

function next(): void {
    goTo(activeIndex.value + 1);
}

function onKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowLeft':
            event.preventDefault();
            prev();
            break;
        case 'ArrowRight':
            event.preventDefault();
            next();
            break;
        case 'Home':
            event.preventDefault();
            goTo(0);
            break;
        case 'End':
            event.preventDefault();
            goTo(totalSlides.value - 1);
            break;
    }
}

function onPointerDown(event: PointerEvent): void {
    pointerStartX = event.clientX;
    pointerStartY = event.clientY;
    pointerStartTime = performance.now();
    paused.value = true;
}

function onPointerUp(event: PointerEvent): void {
    const dx = event.clientX - pointerStartX;
    const dy = event.clientY - pointerStartY;
    const duration = performance.now() - pointerStartTime;

    // Horizontal drag beyond the threshold, or a fast flick gesture.
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > SWIPE_THRESHOLD_PX) {
        if (dx < 0) {
            next();
        } else {
            prev();
        }
    } else if (duration < FLICK_MAX_DURATION_MS && Math.abs(dx) > FLICK_THRESHOLD_PX) {
        if (dx < 0) {
            next();
        } else {
            prev();
        }
    }

    paused.value = false;
}

function startAutoAdvance(): void {
    if (props.auto_advance_ms === null || props.auto_advance_ms <= 0 || prefersReduced.value) {
        return;
    }

    autoAdvanceInterval = setInterval(() => {
        if (paused.value || totalSlides.value === 0) {
            return;
        }

        goTo((activeIndex.value + 1) % totalSlides.value);
    }, props.auto_advance_ms);
}

function stopAutoAdvance(): void {
    if (autoAdvanceInterval !== null) {
        clearInterval(autoAdvanceInterval);
        autoAdvanceInterval = null;
    }
}

/**
 * Native guard for the arrow/dot buttons: the Vue `@click.stop`
 * handlers are unreliable in the built app — clicking "next"/"prev"
 * on a project card navigated to the project detail page instead of
 * changing the slide (the event reached the card's Inertia Link;
 * reproduced on the served build). The guard is a bubble-phase native
 * listener on the carousel root: it fires ONLY when the click was not
 * already stopped at the button (so the slide change runs exactly
 * once — the Vue handler wins when it works), and it stops the event
 * before it reaches any link ancestor. Clicks on the slide content
 * (the card navigation itself) are untouched.
 */
function onRootClick(event: MouseEvent): void {
    if (rootEl.value === null) {
        return;
    }

    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const arrow = target.closest('.carousel__arrow');
    const dot = target.closest('.carousel__dot');

    if (arrow === null && dot === null) {
        return;
    }

    event.stopPropagation();

    if (arrow !== null) {
        if (arrow.classList.contains('carousel__arrow--prev')) {
            prev();
        } else {
            next();
        }
    } else if (dot !== null) {
        const dots = Array.from(rootEl.value.querySelectorAll('.carousel__dot'));
        goTo(dots.indexOf(dot));
    }
}

function onResize(): void {
    if (trackEl.value !== null) {
        updateActiveFromScroll();
    }
}

watch(activeIndex, (index) => {
    emit('change', index);
});

watch(
    () => props.items.length,
    () => {
        activeIndex.value = 0;
        if (trackEl.value !== null) {
            trackEl.value.scrollLeft = 0;
        }
    },
);

onMounted(() => {
    if (trackEl.value !== null) {
        trackEl.value.addEventListener('scroll', onScroll, { passive: true });
    }

    rootEl.value?.addEventListener('click', onRootClick);
    window.addEventListener('resize', onResize);
    startAutoAdvance();
});

onBeforeUnmount(() => {
    trackEl.value?.removeEventListener('scroll', onScroll);
    rootEl.value?.removeEventListener('click', onRootClick);

    if (scrollFrame !== null) {
        cancelAnimationFrame(scrollFrame);
    }

    window.removeEventListener('resize', onResize);
    stopAutoAdvance();
});
</script>

<template>
  <div
    ref="rootEl"
    class="carousel"
    :class="rootClass"
    :data-testid="props.test_id"
    @keydown="onKeydown"
  >
    <div
      class="carousel__viewport"
      :style="viewportStyle"
    >
      <div
        ref="trackEl"
        class="carousel__track"
        role="group"
        aria-roledescription="carousel"
        :data-testid="`${props.test_id}-track`"
        @pointerdown="onPointerDown"
        @pointerup="onPointerUp"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        @focusin="paused = true"
        @focusout="paused = false"
      >
        <div
          v-for="(item, index) in props.items"
          :key="itemKey(item)"
          class="carousel__slide"
          :class="{ 'is-active': index === activeIndex }"
          role="group"
          :aria-label="t('common.slide_of', { current: index + 1, total: totalSlides })"
          :data-testid="`${props.test_id}-slide-${index}`"
        >
          <slot
            :item="item"
            :index="index"
            :is_active="index === activeIndex"
          />
        </div>
      </div>
      <button
        v-if="props.show_arrows"
        type="button"
        class="carousel__arrow carousel__arrow--prev"
        :disabled="activeIndex === 0"
        :aria-label="t('common.previous')"
        :data-testid="`${props.test_id}-prev`"
        @click.stop="prev"
        @mousedown.stop
        @pointerdown.stop
      >
        <Icon
          class="carousel__arrow-icon"
          icon="heroicons:chevron-left"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="props.show_arrows"
        type="button"
        class="carousel__arrow carousel__arrow--next"
        :disabled="activeIndex === totalSlides - 1"
        :aria-label="t('common.next')"
        :data-testid="`${props.test_id}-next`"
        @click.stop="next"
        @mousedown.stop
        @pointerdown.stop
      >
        <Icon
          class="carousel__arrow-icon"
          icon="heroicons:chevron-right"
          aria-hidden="true"
        />
      </button>
    </div>
    <div
      v-if="props.show_dots && totalSlides > 1"
      class="carousel__dots"
    >
      <button
        v-for="(item, index) in props.items"
        :key="`dot-${itemKey(item)}`"
        type="button"
        class="carousel__dot"
        :class="{ 'is-active': index === activeIndex }"
        :aria-label="t('common.go_to_slide', { number: index + 1 })"
        :aria-current="index === activeIndex ? 'true' : undefined"
        :data-testid="`${props.test_id}-dot-${index}`"
        @click.stop="goTo(index)"
        @mousedown.stop
        @pointerdown.stop
      />
    </div>
  </div>
</template>

<style scoped>
.carousel__viewport {
    position: relative;
    overflow: hidden;
}

.carousel__track {
    display: flex;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    border-radius: var(--radius-md);
}

.carousel__track::-webkit-scrollbar {
    display: none;
}

.carousel__slide {
    flex: 0 0 calc((100% - var(--carousel-gap) * (var(--carousel-slides) - 1)) / var(--carousel-slides));
    scroll-snap-align: start;
    margin-right: var(--carousel-gap);
}

.carousel__slide:last-child {
    margin-right: 0;
}

.carousel__arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    border-radius: var(--radius-full);
    background: rgb(22 33 27 / 55%);
    color: #fff;
    cursor: pointer;
    transition: opacity var(--transition-fast);
}

.carousel__arrow:hover {
    background: rgb(22 33 27 / 75%);
}

.carousel__arrow:disabled {
    opacity: 0.35;
    cursor: default;
}

/* Arrows appear on hover/focus only (the project card carousel —
   public-section-components.md:696: "Arrows appear only on card hover
   (opacity transition)"). */
.carousel--hover-arrows .carousel__arrow {
    opacity: 0;
}

.carousel--hover-arrows:hover .carousel__arrow,
.carousel--hover-arrows:focus-within .carousel__arrow {
    opacity: 1;
}

.carousel__arrow--prev {
    left: var(--space-sm);
}

.carousel__arrow--next {
    right: var(--space-sm);
}

.carousel__arrow-icon {
    width: 1.25em;
    height: 1.25em;
}

/* The dots are 1.5× the original size (12px, was 8px) with a clear
   gap above the carousel (user rework 2026-08-16 — they used to sit
   4px from the viewport edge). */
.carousel__dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: var(--space-md) 0 var(--space-xs);
}

.carousel__dot {
    width: 12px;
    height: 12px;
    border: none;
    border-radius: var(--radius-full);
    background: var(--color-border-strong);
    cursor: pointer;
    padding: 0;
    transition: background var(--transition-fast);
}

.carousel__dot.is-active,
.carousel__dot[aria-current='true'] {
    background: var(--color-accent);
}

/* Compact dots for the project cards (user rework 2026-08-16): 9px
   circles with an equal 8px gap above and below — the card previews
   are small, so the detail-gallery sizing (12px, 16px top gap) reads
   heavy there. */
.carousel--compact-dots .carousel__dots {
    gap: 8px;
    padding: 8px 0;
}

.carousel--compact-dots .carousel__dot {
    width: 9px;
    height: 9px;
}

@media (prefers-reduced-motion: no-preference) {
    .carousel__track {
        scroll-behavior: smooth;
    }
}
</style>
