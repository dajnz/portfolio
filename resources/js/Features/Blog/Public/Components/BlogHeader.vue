<!-- BlogHeader (public-section-components.md:1209-1239, mockup blog.html) — the blog index toolbar: the dual-response search autocomplete (Enter with no highlighted result submits the term as a full `?q=` load — public-section-ui.md:487), the unified PublicTagFilter over `/api/blog-tags/search` (article tags + skills with the `type` discriminator — the pills split into `?article_tags=`/`?skill_tags=`, content-management.md:198-200), the conditional year filter (a PublicBaseDropdown rendered only when more than one year of articles exists; "All" = null, the tree's first option) and the grid/list toggle. Filter changes emit `filter-changed` (batched in one tick) — the page resets the scroll cursor and reloads; view-mode changes only emit `update:view_mode` (the cookie persists them client-side, architecture.md:204). -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseDropdown from '@shared-public/PublicBaseDropdown.vue';
import PublicSearchAutocomplete from '@shared-public/PublicSearchAutocomplete.vue';
import PublicTagFilter from '@shared-public/PublicTagFilter.vue';
import { computed, nextTick, ref } from 'vue';

import type { SelectedTagView, ViewMode } from '../Composables/useBlogIndexPage';

export type HeaderSelectedTag = SelectedTagView;
export type HeaderViewMode = ViewMode;

const props = withDefaults(
    defineProps<{
        search_query?: string;
        search_fetch_url?: string;
        selected_tags?: HeaderSelectedTag[];
        tags_fetch_url?: string;
        available_years?: string[];
        active_year?: string | null;
        view_mode?: HeaderViewMode;
        test_id?: string;
    }>(),
    {
        search_query: '',
        search_fetch_url: '/blog',
        selected_tags: () => [],
        tags_fetch_url: '/api/blog-tags/search',
        available_years: () => [],
        active_year: null,
        view_mode: 'grid',
        test_id: 'blog-header',
    },
);

const emit = defineEmits<{
    'update:search_query': [query: string];
    'update:selected_tags': [tags: HeaderSelectedTag[]];
    'update:active_year': [year: string | null];
    'update:view_mode': [mode: HeaderViewMode];
    'filter-changed': [];
}>();

const { t } = useI18n();

// The year dropdown's "All" option — the component's modelValue is
// string-typed, so the null state travels as the '' sentinel (ADR-0025).
const YEAR_ALL = '';

const yearOptions = computed(() => [
    { value: YEAR_ALL, label: t('blog.filter_all') },
    ...props.available_years.map((year) => ({ value: year, label: year })),
]);

const yearModel = computed(() => props.active_year ?? YEAR_ALL);

// The year filter renders only when more than one distinct year of
// articles exists (the plan's "conditional year filter" + the tree
// note — a single year adds no filtering value).
const showYearFilter = computed(() => props.available_years.length > 1);

// The mobile filter-collapse state: on small screens the toolbar shows
// only the search row and a toggle button; expanding reveals the tag
// filter and the year dropdown (each on its own full-width row). The
// state is purely visual — desktop always shows all rows (the
// projects ADR-0025 pattern, user request 2026-08-16).
const filtersExpanded = ref(true);

function toggleFilters(): void {
    filtersExpanded.value = !filtersExpanded.value;
}

function emitFilterChanged(): void {
    // "Batched" — several control changes within the same tick coalesce
    // into a single reload.
    void nextTick(() => emit('filter-changed'));
}

function onSearchSubmit(query: string): void {
    emit('update:search_query', query);
    emitFilterChanged();
}

function onTagsChange(tags: HeaderSelectedTag[]): void {
    emit('update:selected_tags', tags);
    emitFilterChanged();
}

function onYearChange(value: string | string[]): void {
    const next = String(value) === YEAR_ALL ? null : String(value);
    emit('update:active_year', next);
    emitFilterChanged();
}

function onViewModeChange(mode: HeaderViewMode): void {
    emit('update:view_mode', mode);
}
</script>

<template>
  <div
    class="blog-toolbar"
    :class="{ 'blog-toolbar--filters-hidden': !filtersExpanded }"
    :data-testid="props.test_id"
  >
    <!-- Row 1: the search box spanning the full width + the mobile-only
         filters toggle + the grid/list toggle. Row 2: the tag filter
         and the year dropdown — side by side on desktop, stacked on
         their own full-width rows on small screens (user rework
         2026-08-16, the projects toolbar pattern). The toolbar is
         sticky below the page header so the filters stay reachable
         while scrolling a long article list. -->
    <div class="blog-toolbar__row">
      <PublicSearchAutocomplete
        class="blog-toolbar__search"
        :fetch_url="props.search_fetch_url"
        :placeholder="t('blog.search_placeholder')"
        result_label_key="title"
        result_url_prefix="/blog/"
        :test_id="`${props.test_id}-search`"
        @submit="onSearchSubmit"
      />
      <PublicBaseButton
        class="blog-toolbar__toggle"
        variant="icon-only"
        :icon="filtersExpanded ? 'heroicons:chevron-up' : 'heroicons:funnel'"
        :aria-label="filtersExpanded ? t('blog.hide_filters') : t('blog.show_filters')"
        :aria-expanded="filtersExpanded ? 'true' : 'false'"
        aria-controls="blog-toolbar-filters"
        :test_id="`${props.test_id}-filters-toggle`"
        @click="toggleFilters"
      />
      <div
        class="view-toggle"
        role="group"
        :aria-label="t('common.view')"
        :data-testid="`${props.test_id}-view`"
      >
        <PublicBaseButton
          variant="icon-only"
          icon="heroicons:squares-2x2"
          :aria-label="t('blog.grid_view')"
          :class="{ 'is-active': props.view_mode === 'grid' }"
          :test_id="`${props.test_id}-view-grid`"
          @click="onViewModeChange('grid')"
        />
        <PublicBaseButton
          variant="icon-only"
          icon="heroicons:list-bullet"
          :aria-label="t('blog.list_view')"
          :class="{ 'is-active': props.view_mode === 'list' }"
          :test_id="`${props.test_id}-view-list`"
          @click="onViewModeChange('list')"
        />
      </div>
    </div>
    <div
      id="blog-toolbar-filters"
      class="blog-toolbar__row"
    >
      <PublicTagFilter
        :model-value="props.selected_tags"
        class="blog-toolbar__tags"
        :fetch_url="props.tags_fetch_url"
        :placeholder="t('blog.filter_by_tags')"
        :test_id="`${props.test_id}-tags`"
        @update:model-value="onTagsChange"
      />
      <PublicBaseDropdown
        v-if="showYearFilter"
        class="blog-toolbar__year"
        :model-value="yearModel"
        :options="yearOptions"
        :label="t('blog.filter_year')"
        :test_id="`${props.test_id}-year`"
        @update:model-value="onYearChange"
      />
    </div>
  </div>
</template>
<style scoped>
/* The toolbar sticks to the top of the viewport below the sticky page
   header; the opaque page background keeps the cards from showing
   through while scrolling. Two rows: the search + view toggle on the
   first, the tag filter + year dropdown on the second (the projects
   toolbar pattern, user rework 2026-08-16). */
.blog-toolbar {
    position: sticky;
    top: var(--header-height);
    z-index: var(--z-sticky);
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
    margin: 0 0 var(--space-2xl);
    padding: var(--space-md) 0;
    background: var(--color-bg-primary);
    border-bottom: 1px solid var(--color-border);
}

.blog-toolbar__row {
    display: flex;
    align-items: center;
    gap: var(--space-md);
}

.blog-toolbar__search {
    flex: 1 1 auto;
    max-width: none;
}

.blog-toolbar__tags {
    flex: 1 1 auto;
    max-width: none;
}

.blog-toolbar__year {
    width: 200px;
    margin-bottom: 0;
    flex-shrink: 0;
}

.view-toggle {
    display: flex;
    flex-shrink: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    overflow: hidden;
}

/* PublicBaseButton's root is the `.btn` element — the child component
   root carries the parent scope attribute, so plain scoped selectors
   reach it (no `:deep` — stylelint rejects it). */
.view-toggle .btn {
    border: none;
    border-radius: 0;
    color: var(--color-text-tertiary);
    background: var(--color-surface);
    height: 40px;
    width: 40px;
}

.view-toggle .btn:hover {
    color: var(--color-text-primary);
    background: var(--color-bg-secondary);
}

.view-toggle .btn.is-active {
    background: var(--color-accent);
    color: #fff;
}

/* The filters toggle is mobile-only: hidden on desktop, where the
   filters row is always fully visible. */
.blog-toolbar__toggle {
    display: none;
}

/* Small screens (the ADR-0025 projects precedent): the grid/list
   switch is hidden (grid and list render identically on mobile), the
   filters toggle appears at the right of the search box, and the tag
   filter + year dropdown stack on their own full-width rows. The
   collapsed state hides the filters row entirely. */
@media (width < 768px) {
    .blog-toolbar__toggle {
        display: inline-flex;
        flex-shrink: 0;
    }

    .view-toggle {
        display: none;
    }

    .blog-toolbar__row:last-child {
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-md);
    }

    .blog-toolbar__tags,
    .blog-toolbar__year {
        width: 100%;
        flex: none;
    }

    .blog-toolbar--filters-hidden .blog-toolbar__row:last-child {
        display: none;
    }
}
</style>
