<!-- BlogIndexPage (public-section-ui.md:209-238, public-section-components.md:1209-1239, mockup blog.html) — the public blog index: the BlogHeader toolbar (dual-response search autocomplete, the unified tag filter with the article/skill `type` discriminator, the conditional year filter, the grid/list toggle persisted in the `blog_view_mode` cookie), the ArticleCard grid/list, the infinite-scroll Load More (cursor reload per B158 replace semantics) and the three empty/error states via PublicEmptyState. The nav follows A10 — the Projects link is conditional on `has_projects` (B268). -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { usePageTracking } from '@shared-cross/Composables/usePageTracking';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import InfiniteScrollLoader from '@shared-public/InfiniteScrollLoader.vue';
import PublicEmptyState from '@shared-public/PublicEmptyState.vue';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import ArticleCard from '../Components/ArticleCard.vue';
import BlogHeader from '../Components/BlogHeader.vue';
import { useBlogIndexPage } from '../Composables/useBlogIndexPage';
import { ensureBlogIcons } from '../iconRegistration';

type BlogProps = NonNullable<components['schemas']['BlogIndexPageProps']['props']>;

// Per-property types (not the whole `BlogProps` alias): the SFC
// compiler's runtime prop resolution cannot expand the indexed-access
// `NonNullable<...['props']>` type (the 9.2 page used the same form).
const props = withDefaults(
    defineProps<{
        articles?: BlogProps['articles'];
        tags?: BlogProps['tags'];
        years?: BlogProps['years'];
        has_more?: BlogProps['has_more'];
        next_cursor?: BlogProps['next_cursor'];
        has_projects?: BlogProps['has_projects'];
        seo?: BlogProps['seo'];
        test_id?: string;
    }>(),
    {
        articles: () => [],
        tags: () => [],
        years: () => [],
        has_more: undefined,
        next_cursor: undefined,
        has_projects: false,
        seo: undefined,
        test_id: 'blog-page',
    },
);

const { t } = useI18n();

usePageTracking('blog_index');
ensureBlogIcons();
useSeoUpdater();

const {
    navItems,
    activeRoute,
    articles,
    availableYears,
    hasActiveFilters,
    isLoading,
    loadError,
    loadMore,
    retry,
    searchQuery,
    selectedTags,
    activeYear,
    viewMode,
    applyFilters,
    clearFilters,
    setViewMode,
} = useBlogIndexPage(props);

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
      {{ t('nav.blog') }}
    </h1>

    <div class="blog-shell">
      <BlogHeader
        :search_query="searchQuery"
        :selected_tags="selectedTags"
        :available_years="availableYears"
        :active_year="activeYear"
        :view_mode="viewMode"
        :test_id="`${props.test_id}-header`"
        @update:search_query="searchQuery = $event"
        @update:selected_tags="selectedTags = $event"
        @update:active_year="activeYear = $event"
        @update:view_mode="onViewModeChange"
        @filter-changed="onFilterChanged"
      />

      <PublicEmptyState
        v-if="loadError"
        mode="error"
        :message="t('blog.could_not_load')"
        :action_label="t('common.retry')"
        :test_id="`${props.test_id}-error`"
        @action="retry"
      />

      <PublicEmptyState
        v-else-if="articles.length === 0 && hasActiveFilters"
        mode="no-results"
        :message="t('blog.no_articles_match')"
        :action_label="t('common.clear_filters')"
        :test_id="`${props.test_id}-no-results`"
        @action="clearFilters"
      />

      <PublicEmptyState
        v-else-if="articles.length === 0"
        mode="empty"
        :message="t('blog.no_articles')"
        :test_id="`${props.test_id}-empty`"
      />

      <template v-else>
        <div
          class="blog-grid"
          :class="`blog-grid--${viewMode}`"
          :data-testid="`${props.test_id}-grid`"
        >
          <ArticleCard
            v-for="article in articles"
            :key="article.id"
            :article="article"
            :tags_limit="viewMode === 'grid' ? 3 : 5"
            :test_id="`${props.test_id}-card-${article.id}`"
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
   (the 9.2 Timeline shell convention). */
.blog-shell {
    max-width: var(--layout-content);
    margin: 0 auto var(--space-2xl);
}

/* The grid per the mockup (blog.html): `auto-fill` cards of at least
   300px; the list mode is a single column. */
.blog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-xl);
}

.blog-grid--list {
    grid-template-columns: 1fr;
}
</style>

<!-- The list-mode card layout crosses the component boundary (the card
     internals belong to ArticleCard) — the BEM classes are public and
     the selectors mirror the mockup's list layout, so a plain
     (non-scoped) block is used instead of `:deep()` (the
     projects-grid--list precedent). List mode: 2 columns — the
     preview image on the left, the content (title / description /
     meta / tags rows) on the right (user rework 2026-08-16). -->
<style>
.blog-grid--list .article-card {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: var(--space-lg);
    align-items: start;
}

.blog-grid--list .article-card__image {
    grid-column: 1;
    margin-bottom: 0;
}

.blog-grid--list .card__content {
    grid-column: 2;
}

/* Small screens: the card reverts to the stacked grid look — the
   `blog_view_mode` cookie may still hold `list`, and the grid and list
   render identically below 768px (the B267 projects precedent). */
@media (width < 768px) {
    .blog-grid--list .article-card {
        display: block;
    }

    .blog-grid--list .article-card__image {
        margin-bottom: var(--space-md);
    }
}
</style>
