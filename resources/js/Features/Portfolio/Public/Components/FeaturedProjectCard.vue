<!-- FeaturedProjectCard (Portfolio feature — NOT the shared Projects ProjectCard) — the landing's compact featured-project card (mockup index.html:587-623). Always renders a STATIC preview image or the same no-image placeholder as the projects index cards (large faint photo icon on the tinted surface — user request 2026-08-16) when the project has no preview (no carousel, no action buttons — the landing wire carries neither screenshots nor URLs). The card look comes from the global `.card` pattern (resources/css/app.css — the mockup's pages.css); tech-tag chips link to `/projects?tags={id}` only when `tags_linked` (more published projects than featured — 9.1 decision) and render after the feature's lazy icon registration. Root is a plain anchor (the 9.1 behavior; the projects public pages existed only after 9.3). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';

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
  <a
    v-if="props.project !== null"
    class="card card--hoverable project-card"
    :href="`/projects/${props.project.slug}`"
    :data-testid="props.test_id"
  >
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
      class="card__image project-card__image project-card__image--placeholder"
      aria-hidden="true"
      :data-testid="`${props.test_id}-placeholder`"
    >
      <!-- The projects index placeholder (user request 2026-08-16):
           the large faint photo icon, no text label. -->
      <Icon
        class="project-card__placeholder-icon"
        icon="heroicons:photo"
        aria-hidden="true"
      />
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
  </a>
</template>

<style scoped>
.project-card {
    display: block;
    color: inherit;
    text-decoration: none;
}

/* The image IS the `.card__image` slot (9.1 markup): the global class
   supplies the 16/9 frame, this crops non-16:9 uploads. */
.project-card__image {
    object-fit: cover;
}

/* The placeholder image slot: the global `.card__image` supplies the
   aspect ratio, radius and background — this swaps the fill for the
   same look as the projects index placeholder (tinted surface with a
   dashed inner frame + the large faint photo icon; user request
   2026-08-16). */
.project-card__image--placeholder {
    background:
        linear-gradient(135deg, var(--color-bg-tertiary) 25%, transparent 25%) 0 0 / 16px 16px,
        var(--color-bg-secondary);
    border: 1px dashed var(--color-border-strong);
}

.project-card__placeholder-icon {
    width: 5.25em;
    height: 5.25em;
    color: var(--color-text-tertiary);
    opacity: 0.5;
}
</style>
