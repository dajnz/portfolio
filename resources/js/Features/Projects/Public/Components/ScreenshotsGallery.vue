<!-- The lightbox is code-split via a manual dynamic import + `<component :is>` (rather than `defineAsyncComponent`) so the gallery can hold the chunk-loading state and show the skeleton overlay. -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import HorizontalCarousel from '@shared-public/HorizontalCarousel.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import { type Component, ref, shallowRef } from 'vue';

import type { DetailScreenshotView } from '../Composables/useProjectDetailPage';

const props = withDefaults(
    defineProps<{
        screenshots?: DetailScreenshotView[];
        test_id?: string;
    }>(),
    {
        screenshots: () => [],
        test_id: 'screenshots-gallery',
    },
);

const { t } = useI18n();

const lightboxOpen = ref(false);
const lightboxChunkLoading = ref(false);
const startIndex = ref(0);
const lightboxComponent = shallowRef<Component | null>(null);
let triggerElement: HTMLElement | null = null;

async function openLightbox(index: number, trigger: HTMLElement): Promise<void> {
    let component = lightboxComponent.value;

    if (component === null) {
        lightboxChunkLoading.value = true;
        // The lightbox chunk is fetched only on the first open.
        const module = await import('./ProjectLightbox.vue');
        component = module.default;
        lightboxChunkLoading.value = false;
    }

    lightboxComponent.value = component;
    startIndex.value = index;
    triggerElement = trigger;
    lightboxOpen.value = true;
}

function onSlideClick(event: MouseEvent, index: number): void {
    const figure = (event.currentTarget as HTMLElement) ?? null;
    if (figure !== null) {
        figure.focus({ preventScroll: true });
    }
    void openLightbox(index, figure);
}

function onNavigate(index: number): void {
    startIndex.value = index;
}
</script>

<template>
  <section
    v-if="props.screenshots.length > 0"
    class="gallery"
    :aria-label="t('projects.screenshots')"
    aria-roledescription="carousel"
    :data-testid="props.test_id"
  >
    <HorizontalCarousel
      :items="props.screenshots"
      item_key="id"
      :show_arrows="true"
      :show_dots="true"
      :test_id="`${props.test_id}-carousel`"
      @change="onNavigate"
    >
      <template #default="{ item, index }">
        <figure
          class="gallery__slide"
          tabindex="-1"
          :data-testid="`${props.test_id}-slide-${index}`"
          @click="onSlideClick($event, index)"
        >
          <img
            :src="(item as DetailScreenshotView).image_url"
            :alt="(item as DetailScreenshotView).alt_text"
            width="800"
            height="450"
            loading="lazy"
          >
          <figcaption class="gallery__caption">
            <h3 class="gallery__caption-title">
              {{ (item as DetailScreenshotView).title }}
            </h3>
            <p class="gallery__caption-desc">
              {{ (item as DetailScreenshotView).description }}
            </p>
          </figcaption>
        </figure>
      </template>
    </HorizontalCarousel>

    <!-- Skeleton overlay on the clicked slide while the lightbox chunk
         loads (state `lightbox_opening`). -->
    <div
      v-if="lightboxChunkLoading"
      class="gallery__chunk-overlay"
      aria-hidden="true"
      :data-testid="`${props.test_id}-chunk-loading`"
    >
      <PublicBaseSkeleton
        shape="rectangle"
        width="100%"
        height="100%"
      />
    </div>

    <component
      :is="lightboxComponent"
      v-if="lightboxComponent !== null"
      v-model="lightboxOpen"
      :images="props.screenshots"
      :start_index="startIndex"
      :return_focus_el="triggerElement"
      :test_id="`${props.test_id}-lightbox`"
    />
  </section>
</template>

<style scoped>
.gallery {
    margin-bottom: var(--space-2xl);
    position: relative;
}

.gallery__slide {
    position: relative;
    margin: 0;
    cursor: zoom-in;
    outline: none;
}

.gallery__slide img {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
}

.gallery__caption {
    padding: var(--space-md) var(--space-lg);
    background: var(--color-surface);
}

.gallery__caption-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-xs);
}

.gallery__caption-desc {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    line-height: var(--line-height-normal);
    margin: 0;
}

.gallery__chunk-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    border-radius: var(--radius-lg);
    overflow: hidden;
    pointer-events: none;
}
</style>

<!-- The framed carousel viewport lives inside the shared
     HorizontalCarousel — styled from a plain (non-scoped) block instead
     of `:deep()` (stylelint rejects it; the TextEditor precedent). -->
<style>
.gallery .carousel__viewport {
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    box-shadow: var(--card-shadow);
}

.gallery .carousel__track {
    border-radius: var(--radius-lg);
}
</style>
