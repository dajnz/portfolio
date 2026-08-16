<!-- ProjectsIndexPage (public-section-ui.md:142-175, public-section-components.md:1094-1132, mockup projects.html) — the public projects list: the ProjectsHeader toolbar (dual-response search autocomplete, skill tag filter with AND/intersect, the date/complexity sort dropdown, the grid/list toggle persisted in the `projects_view_mode` cookie), the ProjectCard grid/list, the infinite-scroll Load More (cursor reload per B158 replace semantics) and the three empty/error states via PublicEmptyState. The nav follows A10 — the Projects link is conditional on any published project existing (the same signal the landing uses). -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import InfiniteScrollLoader from '@shared-public/InfiniteScrollLoader.vue';
import PublicEmptyState from '@shared-public/PublicEmptyState.vue';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import ProjectCard from '../Components/ProjectCard.vue';
import ProjectsHeader from '../Components/ProjectsHeader.vue';
import { useProjectsIndexPage } from '../Composables/useProjectsIndexPage';
import { ensureProjectsIcons } from '../iconRegistration';

type ProjectsProps = NonNullable<components['schemas']['ProjectsIndexPageProps']['props']>;

// Per-property types (not the whole `ProjectsProps` alias): the SFC
// compiler's runtime prop resolution cannot expand the indexed-access
// `NonNullable<...['props']>` type (the 9.2 page used the same form).
const props = withDefaults(
    defineProps<{
        projects?: ProjectsProps['projects'];
        skill_tags?: ProjectsProps['skill_tags'];
        has_more?: ProjectsProps['has_more'];
        next_cursor?: ProjectsProps['next_cursor'];
        seo?: ProjectsProps['seo'];
        test_id?: string;
    }>(),
    {
        projects: () => [],
        skill_tags: () => [],
        has_more: undefined,
        next_cursor: undefined,
        seo: undefined,
        test_id: 'projects-page',
    },
);

const { t } = useI18n();

ensureProjectsIcons();
useSeoUpdater();

const {
    navItems,
    activeRoute,
    projects,
    isLoading,
    loadError,
    loadMore,
    retry,
    searchQuery,
    selectedTags,
    sortColumn,
    sortDirection,
    viewMode,
    hasActiveFilters,
    applyFilters,
    clearFilters,
    setViewMode,
} = useProjectsIndexPage(props);

function onFilterChanged(): void {
    applyFilters();
}

function onViewModeChange(mode: 'grid' | 'list'): void {
    setViewMode(mode);
}
</script>

<template>
  <PublicPageLayout
    :nav_items="navItems"
    :active_route="activeRoute"
    :test_id="props.test_id"
  >
    <h1
      class="page-title"
      :data-testid="`${props.test_id}-title`"
    >
      {{ t('nav.projects') }}
    </h1>

    <div class="projects-shell">
      <ProjectsHeader
        :search_query="searchQuery"
        :selected_tags="selectedTags"
        :sort_column="sortColumn"
        :sort_direction="sortDirection"
        :view_mode="viewMode"
        :test_id="`${props.test_id}-header`"
        @update:search_query="searchQuery = $event"
        @update:selected_tags="selectedTags = $event"
        @update:sort_column="sortColumn = $event"
        @update:sort_direction="sortDirection = $event"
        @update:view_mode="onViewModeChange"
        @filter-changed="onFilterChanged"
      />

      <PublicEmptyState
        v-if="loadError"
        mode="error"
        :message="t('projects.could_not_load')"
        :action_label="t('common.retry')"
        :test_id="`${props.test_id}-error`"
        @action="retry"
      />

      <PublicEmptyState
        v-else-if="projects.length === 0 && hasActiveFilters"
        mode="no-results"
        :message="t('projects.no_projects_match')"
        :action_label="t('common.clear_filters')"
        :test_id="`${props.test_id}-no-results`"
        @action="clearFilters"
      />

      <PublicEmptyState
        v-else-if="projects.length === 0"
        mode="empty"
        :message="t('projects.no_projects')"
        :test_id="`${props.test_id}-empty`"
      />

      <template v-else>
        <div
          class="projects-grid"
          :class="`projects-grid--${viewMode}`"
          :data-testid="`${props.test_id}-grid`"
        >
          <ProjectCard
            v-for="project in projects"
            :key="project.id"
            :project="project"
            variant="default"
            :tags_limit="viewMode === 'grid' ? 3 : 5"
            :test_id="`${props.test_id}-card-${project.id}`"
          />
        </div>
        <InfiniteScrollLoader
          :has_more="props.has_more ?? false"
          :is_loading="isLoading"
          :test_id="`${props.test_id}-load-more`"
          @load-more="loadMore"
        />
      </template>
    </div>
  </PublicPageLayout>
</template>

<style scoped>
/* The title spans the same content width as the list below it
   (`--layout-content`, the header/footer width), centered inside the
   layout's xl main container (the 9.2 convention). */
.page-title {
    font-family: var(--font-display);
    font-size: var(--font-size-4xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    max-width: var(--layout-content);
    margin: var(--space-xl) auto var(--space-lg);
}

/* The whole page content spans `--layout-content` (1000px — the
   header/footer width; user decision 2026-08-15), centered inside the
   layout's xl main container; the bottom margin clears the footer
   (the 9.2 Timeline shell convention). The shell also hosts the grid's
   container query (its inline size = `--layout-content`), so the
   3/2/1-cards-per-row behavior follows the token — changing
   `--layout-content` later keeps the breakpoints (the 9.1 featured
   grid pattern, user rework 2026-08-16). */
.projects-shell {
    max-width: var(--layout-content);
    margin: 0 auto var(--space-2xl);
    container-type: inline-size;
}

/* Grid mode: 1 card per row by default, 2 from 640px and 3 from 980px
   of content width (3 × ~312px cards at the 1000px token); list mode:
   single column with the image beside the content. */
.projects-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-xl);
}

@container (min-width: 640px) {
    .projects-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@container (min-width: 980px) {
    .projects-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.projects-grid--list {
    grid-template-columns: 1fr;
}
</style>

<!-- The list-mode card layout crosses the component boundary (the
     card internals belong to ProjectCard) — the BEM classes are public
     and the selectors mirror the mockup's `.projects-grid--list`
     rules, so a plain (non-scoped) block is used instead of `:deep()`
     (the TextEditor precedent — stylelint rejects `:deep`). -->
<style>
.projects-grid--list .project-card {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: var(--space-lg);
    align-items: start;
}

.projects-grid--list .project-card__image,
.projects-grid--list .project-card-carousel {
    grid-column: 1;
}

.projects-grid--list .card__content {
    grid-column: 2;
}

/* Small screens (user request 2026-08-16): the grid/list switch is
   hidden there, but the `projects_view_mode` cookie may still hold
   `list` — the card reverts to the stacked grid look so the single
   visible layout is always the grid. */
@media (width < 768px) {
    .projects-grid--list .project-card {
        display: block;
    }
}
</style>
