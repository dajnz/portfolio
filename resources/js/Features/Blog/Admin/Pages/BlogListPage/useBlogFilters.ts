import { router } from '@inertiajs/vue3';
import {
    type Cursor,
    type CursorNavigationPayload,
    cursorNavigationParams,
} from '@shared-cross/Utils/cursorNavigationParams';
import { computed, ref } from 'vue';

import type { BlogPublishedFilter, BlogTagFilter, BlogViewMode } from './BlogFiltersBar.vue';

/** The paginator's keyset cursors (the `meta.cursors` prop shape). */
export interface BlogListCursors {
    first: Cursor;
    last: Cursor;
    page: number;
}

// BlogListPage — the URL-synced filter state (BlogListPage.vue is the
// orchestrator). Owns the filter refs, the `currentParams` builder and the
// apply/navigate round-trips; the rows/selection state lives in
// `useBlogRows`, the delete flows in `useBlogDeletes`. The unified tag
// filter splits into the backend's two CSV params (`article_tags` for
// `type: "tag"` options, `skill_tags` for `type: "skill"` — routes.md:
// 204-205, B2), the year filter is the dropdown string form of the
// backend's `?year=YYYY`.
export function useBlogFilters() {
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

    const filterTags = ref<BlogTagFilter[]>([]);
    const publishedFilter = ref<BlogPublishedFilter>(
        initialQueryParam('published') === '1'
            ? 'published'
            : initialQueryParam('published') === '0'
              ? 'unpublished'
              : 'all',
    );
    const yearFilter = ref(initialQueryParam('year'));
    const showTrashed = ref(initialQueryParam('trashed') === '1');
    const viewMode = ref<BlogViewMode>('table');

    const hasActiveFilters = computed(() => {
        return (
            searchTerm.value !== '' ||
            filterTags.value.length > 0 ||
            publishedFilter.value !== 'all' ||
            yearFilter.value !== ''
        );
    });

    function currentParams(
        extra: Record<string, string | number> = {},
    ): Record<string, string | number> {
        const params: Record<string, string | number> = {};

        if (searchTerm.value !== '') {
            params.q = searchTerm.value;
        }

        if (filterTags.value.length > 0) {
            params.article_tags = filterTags.value
                .filter((tag) => tag.type === 'tag')
                .map((tag) => tag.id)
                .join(',');
            params.skill_tags = filterTags.value
                .filter((tag) => tag.type === 'skill')
                .map((tag) => tag.id)
                .join(',');
        }

        if (publishedFilter.value === 'published') {
            params.published = '1';
        } else if (publishedFilter.value === 'unpublished') {
            params.published = '0';
        }

        if (yearFilter.value !== '') {
            params.year = yearFilter.value;
        }

        if (showTrashed.value) {
            params.trashed = '1';
        }

        return { ...params, ...extra };
    }

    function applyFilters(): void {
        router.get('/admin/blog', currentParams(), {
            only: ['articles', 'meta', 'tags', 'years'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    function navigate(
        payload: CursorNavigationPayload,
        cursors: BlogListCursors | undefined,
    ): void {
        router.get('/admin/blog', currentParams(cursorNavigationParams(payload, cursors)), {
            only: ['articles', 'meta', 'tags', 'years'],
            preserveState: true,
            preserveScroll: true,
        });
    }

    function onAutocompleteSubmit(query: string): void {
        searchInput.value = query;
        searchTerm.value = query;
        applyFilters();
    }

    function onFilterTagsChange(tags: BlogTagFilter[]): void {
        filterTags.value = tags;
        applyFilters();
    }

    function onPublishedFilterChange(value: BlogPublishedFilter): void {
        publishedFilter.value = value;
        applyFilters();
    }

    function onYearFilterChange(value: string): void {
        yearFilter.value = value;
        applyFilters();
    }

    function onTrashedChange(checked: boolean): void {
        showTrashed.value = checked;
        applyFilters();
    }

    function clearFilters(): void {
        searchInput.value = '';
        searchTerm.value = '';
        filterTags.value = [];
        publishedFilter.value = 'all';
        yearFilter.value = '';
        applyFilters();
    }

    return {
        searchInput,
        searchTerm,
        filterTags,
        publishedFilter,
        yearFilter,
        showTrashed,
        viewMode,
        hasActiveFilters,
        applyFilters,
        navigate,
        onAutocompleteSubmit,
        onFilterTagsChange,
        onPublishedFilterChange,
        onYearFilterChange,
        onTrashedChange,
        clearFilters,
    };
}
