<!-- The compact featured-project cards grid and the conditional "View all
     projects" link. The section hides entirely when no published projects
     exist. The cards are the portfolio-owned `FeaturedProjectCard` (the
     shared Projects `ProjectCard` is for the projects list/detail surfaces,
     not the landing). NOTE: no hover prefetch here —
     `usePrefetch().onHover` calls `router.visit`, which NAVIGATES on a
     100 ms hover linger, so the wiring stays out until a real prefetch
     mechanism exists. Tech-tag chips link to `/projects?tags={id}` only
     while more published projects exist than featured (otherwise they'd
     lead to a stale, tag-filtered list). -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';

import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicCenteredContainer from '@shared-public/PublicCenteredContainer.vue';

import type { FeaturedProjectView } from '../Composables/useLandingPage';
import FeaturedProjectCard from './FeaturedProjectCard.vue';

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
        <FeaturedProjectCard
          v-for="project in props.projects"
          :key="project.id"
          :project="project"
          :tags_linked="props.has_more"
          :test_id="`${props.test_id}-card-${project.id}`"
        />
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
    /* Halved top padding — keeps the section heading from floating
   4rem below the previous block. */
    padding: var(--space-xl) 0 var(--space-3xl);
}

.section-title {
    font-family: var(--font-display);
    font-size: var(--font-size-2xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-lg);
}

/* 3 cards per row on large content widths, 2 on medium, 1 on mobile.
   Container queries measure the content container (PublicCenteredContainer
   hosts the query) — the breakpoints follow --layout-content, so changing
   it later keeps the 3/2/1 behavior. */
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

.project-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}
</style>
