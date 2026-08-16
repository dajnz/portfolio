<!-- ProjectCardCarousel (public-section-components.md:679-701, mockup projects.html) — the inline screenshot carousel inside ProjectCard. The whole card is one link, so the carousel stays non-interactive as far as navigation goes: arrows appear on hover only (opacity transition), dots are always visible for 2+ images, auto-advance every 5 s pauses while the card is hovered or focused (HorizontalCarousel's pointer/focus pause) — and every control click is stopped at the source so the card's link never receives it. A single screenshot renders as a static image (no arrows/dots/auto-advance); zero screenshots → the colored placeholder rect (same as ProjectCard.empty_image). -->
<script setup lang="ts">
import HorizontalCarousel from '@shared-public/HorizontalCarousel.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import { computed } from 'vue';

import type { ScreenshotView } from '../Composables/useProjectsIndexPage';

const props = withDefaults(
    defineProps<{
        screenshots?: ScreenshotView[];
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        screenshots: () => [],
        is_loading: false,
        test_id: 'project-card-carousel',
    },
);

const single = computed(() => props.screenshots.length === 1);
const activeScreenshot = computed(() => props.screenshots[0] ?? null);
</script>

<template>
  <div
    class="project-card-carousel"
    :data-testid="props.test_id"
  >
    <PublicBaseSkeleton
      v-if="props.is_loading"
      shape="rectangle"
      width="100%"
      height="180px"
    />
    <div
      v-else-if="props.screenshots.length > 0"
      class="project-card-carousel__image"
    >
      <img
        v-if="single"
        :src="activeScreenshot?.image_url"
        :alt="activeScreenshot?.alt_text ?? ''"
        width="800"
        height="450"
        loading="lazy"
      >
      <HorizontalCarousel
        v-else
        :items="props.screenshots"
        item_key="image_url"
        :show_arrows="true"
        :show_dots="true"
        :auto_advance_ms="5000"
        arrows_on_hover
        compact_dots
        :test_id="`${props.test_id}-carousel`"
      >
        <template #default="{ item }">
          <img
            :src="(item as ScreenshotView).image_url"
            :alt="(item as ScreenshotView).alt_text"
            width="800"
            height="450"
            loading="lazy"
          >
        </template>
      </HorizontalCarousel>
    </div>
    <div
      v-else
      class="project-card-carousel__image project-card-carousel__image--placeholder"
      aria-hidden="true"
    />
  </div>
</template>

<style scoped>
.project-card-carousel__image {
    border-radius: var(--radius-md);
    overflow: hidden;
    margin-bottom: var(--space-md);
    background: var(--color-bg-tertiary);
}

.project-card-carousel__image img {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
}

.project-card-carousel__image--placeholder {
    aspect-ratio: 16 / 9;
    background: var(--color-bg-tertiary);
}
</style>
