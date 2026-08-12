import { router } from '@inertiajs/vue3';
import {
    type Cursor,
    type CursorNavigationPayload,
    cursorNavigationParams,
} from '@shared-cross/Utils/cursorNavigationParams';
import { computed, ref } from 'vue';

import type { PickerTag, ProjectPublishedFilter, ProjectViewMode } from './ProjectFiltersBar.vue';

/** The paginator's keyset cursors (the `meta.cursors` prop shape). */
export interface ProjectListCursors {
    first: Cursor;
    last: Cursor;
    page: number;
}

// ProjectsListPage — the URL-synced filter state (ProjectsListPage.vue is
// the orchestrator). Owns the filter refs, the `currentParams` builder and
// the apply/navigate round-trips; the rows/selection state lives in
// `useProjectRows`, the delete flows in `useProjectDeletes`.
export function useProjectFilters() {
    // Filter state — initialized from the URL query (SSR-safe; the admin
    // list filters travel as query params) and kept alive across Inertia
    // visits via preserveState.
    function initialQueryParam(name: string): string {
        if (typeof window === 'undefined') {
            return '';
        }

        return new URLSearchParams(window.location.search).get(name) ?? '';
    }

    const searchInput = ref(initialQueryParam('q'));
    const searchTerm = ref(searchInput.value);

    const featuredOnly = ref(initialQueryParam('featured') === '1');
    const filterTags = ref<PickerTag[]>([]);
    const publishedFilter = ref<ProjectPublishedFilter>(
        initialQueryParam('published') === '1'
            ? 'published'
            : initialQueryParam('published') === '0'
              ? 'unpublished'
              : 'all',
    );
    const showTrashed = ref(initialQueryParam('trashed') === '1');
    const viewMode = ref<ProjectViewMode>('table');

    const hasActiveFilters = computed(() => {
        return (
            searchTerm.value !== '' ||
            featuredOnly.value ||
            filterTags.value.length > 0 ||
            publishedFilter.value !== 'all'
        );
    });

    function currentParams(
        extra: Record<string, string | number> = {},
    ): Record<string, string | number> {
        const params: Record<string, string | number> = {};

        if (searchTerm.value !== '') {
            params.q = searchTerm.value;
        }

        if (featuredOnly.value) {
            params.featured = '1';
        }

        if (filterTags.value.length > 0) {
            params.tags = filterTags.value.map((tag) => tag.id).join(',');
        }

        if (publishedFilter.value === 'published') {
            params.published = '1';
        } else if (publishedFilter.value === 'unpublished') {
            params.published = '0';
        }

        if (showTrashed.value) {
            params.trashed = '1';
        }

        return { ...params, ...extra };
    }

    function applyFilters(): void {
        router.get('/admin/projects', currentParams(), {
            only: ['projects', 'featured_projects', 'meta'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    function navigate(
        payload: CursorNavigationPayload,
        cursors: ProjectListCursors | undefined,
    ): void {
        router.get('/admin/projects', currentParams(cursorNavigationParams(payload, cursors)), {
            only: ['projects', 'featured_projects', 'meta'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    function onAutocompleteSubmit(query: string): void {
        searchInput.value = query;
        searchTerm.value = query;
        applyFilters();
    }

    function onFeaturedOnlyChange(checked: boolean): void {
        featuredOnly.value = checked;
        applyFilters();
    }

    function onFilterTagsChange(tags: PickerTag[]): void {
        filterTags.value = tags;
        applyFilters();
    }

    function onPublishedFilterChange(value: ProjectPublishedFilter): void {
        publishedFilter.value = value;
        applyFilters();
    }

    function onTrashedChange(checked: boolean): void {
        showTrashed.value = checked;
        applyFilters();
    }

    function clearFilters(): void {
        searchInput.value = '';
        searchTerm.value = '';
        featuredOnly.value = false;
        filterTags.value = [];
        publishedFilter.value = 'all';
        applyFilters();
    }

    return {
        searchInput,
        searchTerm,
        featuredOnly,
        filterTags,
        publishedFilter,
        showTrashed,
        viewMode,
        hasActiveFilters,
        applyFilters,
        navigate,
        onAutocompleteSubmit,
        onFeaturedOnlyChange,
        onFilterTagsChange,
        onPublishedFilterChange,
        onTrashedChange,
        clearFilters,
    };
}
