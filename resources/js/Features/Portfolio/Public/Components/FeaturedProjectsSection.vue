<!-- FeaturedProjectsSection (public-section-components.md:981-992) — the compact featured-project cards grid and the conditional "View all projects" link. The section hides entirely when no published projects exist. NOTE: the ProjectCard hover-prefetch (usePrefetch) is NOT wired here yet — the Projects public pages ship in 9.3, and prefetching their URLs now makes the resolver log "Page not found" for every hover (B247); 9.3 re-adds the prefetch with the real pages. -->
<script setup lang="ts">
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicCenteredContainer from '@shared-public/PublicCenteredContainer.vue';
import type { FeaturedProjectView } from '../Composables/useLandingPage';
import { usePublicIconsReady } from '../iconRegistration';

const props = withDefaults(
    defineProps<{
        projects?: FeaturedProjectView[];
        has_more?: boolean;
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        projects: () => [],
        has_more: false,
        is_loading: false,
        test_id: 'landing-featured-section',
    },
);

const { t } = useI18n();
const iconsReady = usePublicIconsReady();

const skeletonCards = [0, 1, 2];
</script>

<template>
  <section
    v-if="props.projects.length > 0 || props.is_loading"
    class="featured-projects"
    :data-testid="props.test_id"
  >
    <PublicCenteredContainer size="content">
      <h2 class="section-title">
        {{ t('landing.featured_projects') }}
      </h2>

      <div
        v-if="props.is_loading"
        class="featured-projects__grid"
        :data-testid="`${props.test_id}-skeleton`"
      >
        <div
          v-for="card in skeletonCards"
          :key="card"
          class="card project-card project-card--skeleton"
        >
          <PublicBaseSkeleton
            shape="rectangle"
            width="100%"
            height="180px"
          />
          <PublicBaseSkeleton
            shape="text"
            :repeat="2"
          />
          <PublicBaseSkeleton
            shape="pill"
            :repeat="3"
          />
        </div>
      </div>

      <div
        v-else
        class="featured-projects__grid"
      >
        <a
          v-for="project in props.projects"
          :key="project.id"
          class="card card--hoverable project-card"
          :href="`/projects/${project.slug}`"
          :data-testid="`${props.test_id}-card-${project.id}`"
        >
          <img
            v-if="project.preview_image"
            class="card__image project-card__image"
            :src="project.preview_image"
            :alt="project.title"
            width="800"
            height="450"
            loading="lazy"
          >
          <div
            v-else
            class="card__image project-card__image project-card__image--placeholder"
            aria-hidden="true"
          />
          <div class="card__content project-card__content">
            <h3 class="card__title project-card__title">
              {{ project.title }}
            </h3>
            <p class="card__description project-card__description">
              {{ project.brief_description }}
            </p>
            <div
              v-if="iconsReady && project.tech_tags.length > 0"
              class="card__tags project-card__tags"
            >
              <TechSkillTag
                v-for="tag in project.tech_tags"
                :key="tag.id"
                :skill="tag"
                :linked="props.has_more"
                :href="props.has_more ? `/projects?tags=${tag.id}` : undefined"
              />
            </div>
          </div>
        </a>
      </div>

      <div
        v-if="props.has_more"
        class="featured-projects__more"
      >
        <PublicBaseButton
          tag="a"
          href="/projects"
          variant="outline"
          icon="heroicons:arrow-right"
          icon_position="right"
          :test_id="`${props.test_id}-view-all`"
        >
          {{ t('landing.view_all_projects') }}
        </PublicBaseButton>
      </div>
    </PublicCenteredContainer>
  </section>
</template>

<style scoped>
.featured-projects {
    /* Top padding halved (user rework 2026-08-14) — the section
   heading no longer floats 4rem below the previous block. */
    padding: var(--space-xl) 0 var(--space-3xl);
}

.section-title {
    font-family: var(--font-display);
    font-size: var(--font-size-2xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-md);
}

/* 3 cards per row on large content widths, 2 on medium, 1 on mobile.
   Container queries measure the content container (PublicCenteredContainer
   hosts the query) — the breakpoints follow --layout-content, so changing
   it later keeps the 3/2/1 behavior (user rework 2026-08-14). */
.featured-projects__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-xl);
    margin-bottom: var(--space-xl);
}

@container (min-width: 520px) {
    .featured-projects__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@container (min-width: 820px) {
    .featured-projects__grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.featured-projects__more {
    text-align: center;
}

.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    padding: var(--card-padding);
    box-shadow: var(--card-shadow);
    transition: var(--card-transition);
    border-top: 4px solid var(--color-accent-3);
}

.card--hoverable:hover {
    transform: translateY(-3px);
    box-shadow: var(--card-shadow-hover);
}

.project-card {
    display: block;
    color: inherit;
    text-decoration: none;
}

.card__image {
    width: 100%;
    aspect-ratio: 16 / 9;
    background: var(--color-bg-tertiary);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-tertiary);
    font-size: var(--font-size-sm);
}

.project-card__image {
    object-fit: cover;
}

.project-card__image--placeholder {
    background: var(--color-bg-tertiary);
}

.card__title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-xs);
}

.card__description {
    font-size: var(--font-size-base);
    color: var(--color-text-secondary);
    line-height: var(--line-height-normal);
    margin: 0;
}

.card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-xs);
    margin-top: var(--space-md);
}

.project-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}
</style>
