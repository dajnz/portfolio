<script setup lang="ts">
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import PublicImagePlaceholder from '@shared-public/PublicImagePlaceholder.vue';

import type { FeaturedProjectView } from '../Composables/useLandingPage';
import { usePublicIconsReady } from '../iconRegistration';

const props = withDefaults(
    defineProps<{
        project?: FeaturedProjectView | null;
        tags_linked?: boolean;
        test_id?: string;
    }>(),
    {
        project: null,
        tags_linked: false,
        test_id: 'featured-project-card',
    },
);

const iconsReady = usePublicIconsReady();
</script>

<template>
  <div
    v-if="props.project !== null"
    class="card card--hoverable project-card"
    :data-testid="props.test_id"
  >
    <!-- The stretched overlay link keeps the whole card clickable while
         staying a DOM sibling of the card's own filter-chip links
         (nested anchors would be invalid HTML). -->
    <a
      class="project-card__link"
      :href="`/projects/${props.project.slug}`"
      :aria-label="props.project.title"
    />
    <img
      v-if="props.project.preview_image"
      class="card__image project-card__image"
      :src="props.project.preview_image"
      :alt="props.project.title"
      width="800"
      height="450"
      loading="lazy"
    >
    <div
      v-else
      class="card__image project-card__image"
    >
      <PublicImagePlaceholder :test_id="`${props.test_id}-placeholder`" />
    </div>
    <div class="card__content project-card__content">
      <h3 class="card__title project-card__title">
        {{ props.project.title }}
      </h3>
      <p class="card__description project-card__description">
        {{ props.project.brief_description }}
      </p>
      <div
        v-if="iconsReady && props.project.tech_tags.length > 0"
        class="card__tags project-card__tags"
      >
        <TechSkillTag
          v-for="tag in props.project.tech_tags"
          :key="tag.id"
          :skill="tag"
          :linked="props.tags_linked"
          :href="props.tags_linked ? `/projects?tags=${tag.id}` : undefined"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.project-card {
    display: block;
    position: relative;
    color: inherit;
    text-decoration: none;
}

/* The stretched whole-card link overlays the container; the card's own
   links (tech-tag chips) sit above it on their own layer. */
.project-card__link {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.project-card__tags {
    position: relative;
    z-index: 2;
}

/* The image IS the `.card__image` slot: the global class supplies the
   16/9 frame, this crops non-16:9 uploads. */
.project-card__image {
    object-fit: cover;
}
</style>
