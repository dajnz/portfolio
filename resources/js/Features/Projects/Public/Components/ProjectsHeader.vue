<!-- ProjectsHeader (public-section-components.md:1110-1132, mockup projects.html) — the projects index toolbar: the dual-response search autocomplete (Enter with no highlighted result submits the term as a full `?q=` load — public-section-ui.md:487), the PublicTagFilter over skills (AND/intersect, autocomplete via /api/skills/search), the sort dropdown and the grid/list toggle. Per the 2026-08-16 user decision the dropdown offers the two wire-supported sorts ("Newest first" / "Newest last" — the complexity strategy was removed from the backend + OpenAPI contract; `sort_direction` travels as `?direction=asc|desc`), and its "Sort by" label is visually hidden so the trigger aligns with the tag filter's input. Filter changes emit `filter-changed` (batched in one tick) — the page resets the scroll cursor and reloads; view-mode changes only emit `update:view_mode` (the cookie persists them client-side, architecture.md:204). -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseDropdown from '@shared-public/PublicBaseDropdown.vue';
import PublicSearchAutocomplete from '@shared-public/PublicSearchAutocomplete.vue';
import PublicTagFilter from '@shared-public/PublicTagFilter.vue';
import { computed, nextTick, ref } from 'vue';

import type {
    SelectedTagView,
    SortColumn,
    SortDirection,
    ViewMode,
} from '../Composables/useProjectsIndexPage';

export type HeaderSortColumn = SortColumn;
export type HeaderSortDirection = SortDirection;
export type HeaderViewMode = ViewMode;
export type HeaderSelectedTag = SelectedTagView;

const props = withDefaults(
    defineProps<{
        search_query?: string;
        search_fetch_url?: string;
        selected_tags?: HeaderSelectedTag[];
        tags_fetch_url?: string;
        sort_column?: HeaderSortColumn;
        sort_direction?: HeaderSortDirection;
        view_mode?: HeaderViewMode;
        test_id?: string;
    }>(),
    {
        search_query: '',
        search_fetch_url: '/projects',
        selected_tags: () => [],
        tags_fetch_url: '/api/skills/search',
        sort_column: 'date',
        sort_direction: 'desc',
        view_mode: 'grid',
        test_id: 'projects-header',
    },
);

const emit = defineEmits<{
    'update:search_query': [query: string];
    'update:selected_tags': [tags: HeaderSelectedTag[]];
    'update:sort_column': [column: HeaderSortColumn];
    'update:sort_direction': [direction: HeaderSortDirection];
    'update:view_mode': [mode: HeaderViewMode];
    'filter-changed': [];
}>();

const { t } = useI18n();

// The dropdown needs distinct option values — the same `date` column
// with the two `?direction=` variants.
const SORT_OPTIONS = [
    { value: 'date:desc', label: t('projects.newest_first') },
    { value: 'date:asc', label: t('projects.newest_last') },
];

const selectedSort = computed(() => `${props.sort_column}:${props.sort_direction}`);

// The mobile filter-collapse state: on small screens the toolbar shows
// only the search row and a toggle button; expanding reveals the tag
// filter and the sort dropdown (each on its own full-width row). The
// state is purely visual — desktop always shows all rows (user request
// 2026-08-16).
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

function onSortChange(value: string | string[]): void {
    const [column, direction] = String(value).split(':');
    emit('update:sort_column', column === 'complexity' ? 'complexity' : 'date');
    emit('update:sort_direction', direction === 'asc' ? 'asc' : 'desc');
    emitFilterChanged();
}

function onViewModeChange(mode: HeaderViewMode): void {
    emit('update:view_mode', mode);
}
</script>

<template>
  <div
    class="projects-toolbar"
    :class="{ 'projects-toolbar--filters-hidden': !filtersExpanded }"
    :data-testid="props.test_id"
  >
    <!-- Row 1: the search box spanning the full width + the mobile-only
         filters toggle + the grid/list toggle. Row 2: the tag filter
         and the sort dropdown — side by side on desktop, stacked on
         their own full-width rows on small screens (user rework
         2026-08-16). The toolbar is sticky below the page header so
         the filters stay reachable while scrolling a long project
         list. -->
    <div class="projects-toolbar__row">
      <PublicSearchAutocomplete
        class="projects-toolbar__search"
        :fetch_url="props.search_fetch_url"
        :placeholder="t('projects.search_placeholder')"
        result_label_key="title"
        result_url_prefix="/projects/"
        :test_id="`${props.test_id}-search`"
        @submit="onSearchSubmit"
      />
      <PublicBaseButton
        class="projects-toolbar__toggle"
        variant="icon-only"
        :icon="filtersExpanded ? 'heroicons:chevron-up' : 'heroicons:funnel'"
        :aria-label="filtersExpanded ? t('projects.hide_filters') : t('projects.show_filters')"
        :aria-expanded="filtersExpanded ? 'true' : 'false'"
        aria-controls="projects-toolbar-filters"
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
          :aria-label="t('projects.grid_view')"
          :class="{ 'is-active': props.view_mode === 'grid' }"
          :test_id="`${props.test_id}-view-grid`"
          @click="onViewModeChange('grid')"
        />
        <PublicBaseButton
          variant="icon-only"
          icon="heroicons:list-bullet"
          :aria-label="t('projects.list_view')"
          :class="{ 'is-active': props.view_mode === 'list' }"
          :test_id="`${props.test_id}-view-list`"
          @click="onViewModeChange('list')"
        />
      </div>
    </div>
    <div
      id="projects-toolbar-filters"
      class="projects-toolbar__row"
    >
      <PublicTagFilter
        :model-value="props.selected_tags"
        class="projects-toolbar__tags"
        :fetch_url="props.tags_fetch_url"
        :placeholder="t('projects.filter_by_tags')"
        :test_id="`${props.test_id}-tags`"
        @update:model-value="onTagsChange"
      />
      <PublicBaseDropdown
        class="projects-toolbar__sort"
        :model-value="selectedSort"
        :options="SORT_OPTIONS"
        :label="t('projects.sort_by')"
        label_visually_hidden
        :test_id="`${props.test_id}-sort`"
        @update:model-value="onSortChange"
      />
    </div>
  </div>
</template>
<style scoped>
/* The toolbar sticks to the top of the viewport below the sticky page
   header; the opaque page background keeps the cards from showing
   through while scrolling. By default it spans the shell width exactly.
   On wide screens it widens by `--space-xl` (32px) per side so the
   cards' hover shadows never peek past its edges, while the equal
   inline padding keeps the filters' content width exactly at
   `--layout-content` (user rework 2026-08-16). The widening is gated
   behind `min-width: 1064px` — the shell caps at `--layout-content`
   (1000px) and the layout's own padding is 24px per side, so the
   widened box (1000 + 2×32 = 1064px) fits the viewport EXACTLY from
   1064px up and never overflows; below that the negative margins would
   stick out of the viewport and cause a horizontal scrollbar (bugfix
   2026-08-16: the previous unconditional widening overflowed between
   ~768px and ~1063px). The media query measures the layout viewport
   (clientWidth), so scrollbar widths never matter. */
.projects-toolbar {
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

@media (width >= 1064px) {
    .projects-toolbar {
        margin: 0 calc(-1 * var(--space-xl)) var(--space-2xl);
        padding: var(--space-md) var(--space-xl);
    }
}

.projects-toolbar__row {
    display: flex;
    align-items: center;
    gap: var(--space-md);
}

/* The search and tag-filter boxes take all available width; the sort
   dropdown and the view toggle keep their natural widths. */
.projects-toolbar__search {
    flex: 1 1 auto;
    max-width: none;
}

.projects-toolbar__tags {
    flex: 1 1 auto;
    max-width: none;
}

.projects-toolbar__sort {
    width: 220px;
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
.projects-toolbar__toggle {
    display: none;
}

/* Small screens (user request 2026-08-16): the grid/list switch is
   hidden (grid is the only sensible mode), the filters toggle appears
   at the right of the search box, and the tag filter + sort dropdown
   stack on their own full-width rows. The collapsed state hides the
   filters row entirely, leaving only the search. */
@media (width < 768px) {
    .projects-toolbar__toggle {
        display: inline-flex;
        flex-shrink: 0;
    }

    .view-toggle {
        display: none;
    }

    .projects-toolbar__row:last-child {
        flex-direction: column;
        align-items: stretch;
        gap: var(--space-md);
    }

    .projects-toolbar__tags,
    .projects-toolbar__sort {
        width: 100%;
        flex: none;
    }

    .projects-toolbar--filters-hidden .projects-toolbar__row:last-child {
        display: none;
    }
}
</style>
