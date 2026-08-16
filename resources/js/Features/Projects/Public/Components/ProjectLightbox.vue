<!-- ProjectLightbox (public-section-components.md:734-797, mockup project-detail.html) — full-screen screenshot lightbox for the project detail page, teleported to <body>. Scrim click / close button / Escape dismiss it; prev/next cycle without wrapping (first/last disable their buttons), Home/End jump to the bounds, Tab is trapped, and a horizontal swipe on the image area changes slides (same 30 px threshold as HorizontalCarousel). The current image renders lazily under a skeleton until `load` (a broken-image placeholder with the alt text replaces it on `error`); the caption below shows the localized title + description. The active thumbnail is scrolled into view on change; on close, focus returns to the element that opened the lightbox (the gallery's clicked slide). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import type { DetailScreenshotView } from '../Composables/useProjectDetailPage';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        images?: DetailScreenshotView[];
        start_index?: number;
        return_focus_el?: HTMLElement | null;
        test_id?: string;
    }>(),
    {
        images: () => [],
        start_index: 0,
        return_focus_el: null,
        test_id: 'project-lightbox',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    navigate: [index: number];
}>();

const { t } = useI18n();

const SWIPE_THRESHOLD_PX = 30;

const activeIndex = ref(props.start_index);
const imageLoading = ref(false);
const imageError = ref(false);
const slideDirection = ref<'forward' | 'backward'>('forward');
const panelEl = ref<HTMLElement | null>(null);
const thumbEls = ref<HTMLElement[]>([]);

let triggerElement: HTMLElement | null = null;
let pointerStartX = 0;
let pointerStartY = 0;

const total = computed(() => props.images.length);
const current = computed(() => props.images[activeIndex.value] ?? null);
const atFirst = computed(() => activeIndex.value <= 0);
const atLast = computed(() => activeIndex.value >= total.value - 1);

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

function getFocusableElements(): HTMLElement[] {
    if (panelEl.value === null) {
        return [];
    }

    return Array.from(panelEl.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
}

function goTo(index: number): void {
    const target = Math.min(total.value - 1, Math.max(0, index));

    if (target !== activeIndex.value) {
        setIndex(target);
    }
}

function setIndex(index: number): void {
    // The transition slides in the direction of travel (the
    // `lightbox-slide-{direction}` Transition name is read from this).
    slideDirection.value = index > activeIndex.value ? 'forward' : 'backward';
    activeIndex.value = index;
    imageLoading.value = true;
    imageError.value = false;
    emit('navigate', index);
}

function prev(): void {
    if (!atFirst.value) {
        setIndex(activeIndex.value - 1);
    }
}

function next(): void {
    if (!atLast.value) {
        setIndex(activeIndex.value + 1);
    }
}

function close(): void {
    if (props.modelValue) {
        emit('update:modelValue', false);
    }
}

function onPanelKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            close();
            break;
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
            goTo(total.value - 1);
            break;
        case 'Tab': {
            const focusables = getFocusableElements();

            if (focusables.length === 0) {
                event.preventDefault();
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && (active === first || !panelEl.value?.contains(active))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (active === last || !panelEl.value?.contains(active))) {
                event.preventDefault();
                first.focus();
            }
            break;
        }
    }
}

function onStagePointerDown(event: PointerEvent): void {
    pointerStartX = event.clientX;
    pointerStartY = event.clientY;
}

function onStagePointerUp(event: PointerEvent): void {
    const dx = event.clientX - pointerStartX;
    const dy = event.clientY - pointerStartY;

    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > SWIPE_THRESHOLD_PX) {
        if (dx < 0) {
            next();
        } else {
            prev();
        }
    }
}

function onImageLoad(): void {
    imageLoading.value = false;
}

function onImageError(): void {
    imageLoading.value = false;
    imageError.value = true;
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            const active = document.activeElement;
            triggerElement =
                props.return_focus_el instanceof HTMLElement
                    ? props.return_focus_el
                    : active instanceof HTMLElement
                      ? active
                      : null;
            activeIndex.value = Math.min(
                Math.max(0, props.start_index),
                Math.max(0, total.value - 1),
            );
            imageLoading.value = true;
            imageError.value = false;

            nextTick(() => {
                const [first] = getFocusableElements();
                first?.focus();
            });
        } else {
            if (triggerElement instanceof HTMLElement) {
                triggerElement.focus();
            }
        }
    },
    { immediate: true },
);

watch(activeIndex, () => {
    nextTick(() => {
        const thumb = thumbEls.value[activeIndex.value];
        thumb?.scrollIntoView({ block: 'nearest', inline: 'center' });
    });
});

onBeforeUnmount(() => {
    if (props.modelValue && triggerElement instanceof HTMLElement) {
        triggerElement.focus();
    }
});
</script>

<template>
  <Teleport to="body">
    <Transition name="lightbox">
      <div
        v-if="props.modelValue"
        ref="panelEl"
        class="lightbox"
        role="dialog"
        aria-modal="true"
        :aria-label="t('projects.lightbox_label')"
        :data-testid="props.test_id"
        @keydown="onPanelKeydown"
        @click.self="close"
      >
        <button
          type="button"
          class="lightbox__close"
          :aria-label="t('projects.close_lightbox')"
          :data-testid="`${props.test_id}-close`"
          @click="close"
        >
          <Icon
            class="lightbox__close-icon"
            icon="heroicons:x-mark"
            aria-hidden="true"
          />
        </button>

        <div
          v-if="current !== null"
          class="lightbox__stage"
          :data-testid="`${props.test_id}-stage`"
          @pointerdown="onStagePointerDown"
          @pointerup="onStagePointerUp"
        >
          <!-- The slide content is keyed by the active index and
               transitions with a directional fade+slide (out-in — the
               old slide leaves first, then the new one enters). The
               skeleton is part of the slide so the swap is never a
               sharp cut (user rework 2026-08-16). -->
          <Transition
            :name="`lightbox-slide-${slideDirection}`"
            mode="out-in"
          >
            <div
              :key="activeIndex"
              class="lightbox__slide"
            >
              <!-- The image renders underneath while loading (the
                   skeleton overlays it) — the `load` event is what
                   clears the loading state. -->
              <img
                v-if="!imageError"
                class="lightbox__image"
                :class="{ 'is-loading': imageLoading }"
                :src="current.image_url"
                :alt="current.alt_text"
                width="1280"
                height="720"
                loading="lazy"
                :data-testid="`${props.test_id}-image`"
                @load="onImageLoad"
                @error="onImageError"
              >
              <div
                v-else
                class="lightbox__broken"
                :data-testid="`${props.test_id}-broken`"
              >
                {{ current.alt_text }}
              </div>
              <PublicBaseSkeleton
                v-if="imageLoading"
                class="lightbox__skeleton"
                shape="rectangle"
                width="100%"
                height="100%"
              />
            </div>
          </Transition>

          <button
            type="button"
            class="lightbox__nav lightbox__nav--prev"
            :disabled="atFirst"
            :aria-label="t('projects.previous_image')"
            :data-testid="`${props.test_id}-prev`"
            @click="prev"
          >
            <Icon
              class="lightbox__nav-icon"
              icon="heroicons:chevron-left"
              aria-hidden="true"
            />
          </button>
          <button
            type="button"
            class="lightbox__nav lightbox__nav--next"
            :disabled="atLast"
            :aria-label="t('projects.next_image')"
            :data-testid="`${props.test_id}-next`"
            @click="next"
          >
            <Icon
              class="lightbox__nav-icon"
              icon="heroicons:chevron-right"
              aria-hidden="true"
            />
          </button>
        </div>

        <div
          v-if="props.images.length > 1"
          class="lightbox__thumbs"
          :data-testid="`${props.test_id}-thumbs`"
        >
          <button
            v-for="(image, index) in props.images"
            :key="image.image_url"
            ref="thumbEls"
            type="button"
            class="lightbox__thumb"
            :class="{ 'is-active': index === activeIndex }"
            :aria-label="t('projects.view_image', { number: index + 1, title: image.title })"
            :aria-current="index === activeIndex ? 'true' : undefined"
            :data-testid="`${props.test_id}-thumb-${index}`"
            @click="goTo(index)"
          >
            <img
              :src="image.image_url"
              alt=""
              width="160"
              height="90"
              loading="lazy"
            >
          </button>
        </div>

        <div
          v-if="current !== null"
          class="lightbox__caption"
          :data-testid="`${props.test_id}-caption`"
        >
          <h3 class="lightbox__caption-title">
            {{ current.title }}
          </h3>
          <p class="lightbox__caption-desc">
            {{ current.description }}
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.lightbox {
    position: fixed;
    inset: 0;
    z-index: var(--z-modal);
    display: flex;
    flex-direction: column;
    background: rgb(0 0 0 / 88%);
    padding: var(--space-lg);
    overflow: hidden;
}

.lightbox__close {
    position: absolute;
    top: var(--space-md);
    right: var(--space-md);
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: none;
    border-radius: var(--radius-full);
    background: rgb(255 255 255 / 12%);
    color: #fff;
    cursor: pointer;
    transition: background var(--transition-fast);
}

.lightbox__close:hover {
    background: rgb(255 255 255 / 24%);
}

.lightbox__close-icon {
    width: 1.3em;
    height: 1.3em;
}

.lightbox__stage {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
    overflow: hidden;
    touch-action: pan-y;
}

/* The keyed slide wrapper: fills the stage, centers its content (the
   image is `max-width/max-height: 100%` — same result as the stage's
   own centering before the transition wrap). */
.lightbox__slide {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox__skeleton {
    position: absolute;
    inset: 0;
    max-width: none;
    max-height: none;
    border-radius: var(--radius-md);
}

.lightbox__image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xl);
}

.lightbox__image.is-loading {
    visibility: hidden;
}

.lightbox__broken {
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: 80%;
    padding: var(--space-xl);
    border: 1px dashed rgb(255 255 255 / 35%);
    border-radius: var(--radius-md);
    color: rgb(255 255 255 / 70%);
    text-align: center;
}

.lightbox__nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border: none;
    border-radius: var(--radius-full);
    background: rgb(255 255 255 / 12%);
    color: #fff;
    cursor: pointer;
    transition: background var(--transition-fast);
}

.lightbox__nav:hover:not(:disabled) {
    background: rgb(255 255 255 / 24%);
}

.lightbox__nav:disabled {
    opacity: 0.3;
    cursor: default;
}

.lightbox__nav--prev {
    left: var(--space-md);
}

.lightbox__nav--next {
    right: var(--space-md);
}

.lightbox__nav-icon {
    width: 1.4em;
    height: 1.4em;
}

.lightbox__thumbs {
    display: flex;
    justify-content: center;
    gap: var(--space-sm);
    padding: var(--space-md) 0 var(--space-sm);
    overflow-x: auto;
}

.lightbox__thumb {
    flex-shrink: 0;
    width: 88px;
    height: 50px;
    padding: 0;
    border: 2px solid transparent;
    border-radius: var(--radius-sm);
    overflow: hidden;
    cursor: pointer;
    background: transparent;
    transition: border-color var(--transition-fast);
}

.lightbox__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.lightbox__thumb.is-active,
.lightbox__thumb[aria-current='true'] {
    border-color: var(--color-accent);
}

.lightbox__caption {
    text-align: center;
    color: #fff;
    max-width: 560px;
    margin: 0 auto;
    padding-bottom: var(--space-md);
}

.lightbox__caption-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-bold);
    margin: 0 0 var(--space-xs);
}

.lightbox__caption-desc {
    font-size: var(--font-size-sm);
    color: rgb(255 255 255 / 75%);
    line-height: var(--line-height-normal);
    margin: 0;
}

.lightbox-enter-active,
.lightbox-leave-active {
    transition: opacity var(--transition-normal) var(--ease-out);
}

.lightbox-enter-from,
.lightbox-leave-to {
    opacity: 0;
}

/* The slide change transition (user rework 2026-08-16): a directional
   fade + short horizontal slide, so prev/next never cut abruptly. The
   leave runs first (out-in), then the new slide enters from the
   opposite side. */
.lightbox-slide-forward-enter-active,
.lightbox-slide-forward-leave-active,
.lightbox-slide-backward-enter-active,
.lightbox-slide-backward-leave-active {
    transition:
        opacity var(--transition-normal) var(--ease-out),
        transform var(--transition-normal) var(--ease-out);
}

.lightbox-slide-forward-enter-from {
    opacity: 0;
    transform: translateX(5%);
}

.lightbox-slide-forward-leave-to {
    opacity: 0;
    transform: translateX(-5%);
}

.lightbox-slide-backward-enter-from {
    opacity: 0;
    transform: translateX(-5%);
}

.lightbox-slide-backward-leave-to {
    opacity: 0;
    transform: translateX(5%);
}
</style>
