import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const { getMock, visitMock, postMock, patchMock, delMock, deleteMock, pageProps } = vi.hoisted(
    () => ({
        getMock: vi.fn(),
        visitMock: vi.fn(),
        postMock: vi.fn(),
        patchMock: vi.fn(),
        delMock: vi.fn(),
        deleteMock: vi.fn(),
        pageProps: {
            auth: { user: { name: 'Admin', id: 1 } },
            locale: 'en',
            supported_locales: ['en', 'ru'],
        },
    }),
);

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: getMock,
        visit: visitMock,
        post: postMock,
        patch: patchMock,
        delete: deleteMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

vi.mock('@shared-cross/Composables/useJsonApi', () => ({
    useJsonApi: () => ({
        get: getMock,
        post: postMock,
        patch: patchMock,
        del: delMock,
    }),
}));

import BlogListPage from '@blog/Admin/Pages/BlogListPage/BlogListPage.vue';

const ARTICLES = [
    {
        id: 1,
        title: 'Building Reusable Vue Composables',
        slug: 'building-reusable-vue-composables',
        is_published: true,
        published_at: '2026-06-15T10:00:00+00:00',
        reading_time_minutes: 6,
        views: 1204,
        lead_image: 'blog/1/lead.png',
        related_count: 1,
        created_at: '2026-06-15T09:00:00+00:00',
        updated_at: '2026-06-15T10:00:00+00:00',
        tags: [
            { id: 1, name: 'Vue', type: 'tag' as const },
            { id: 2, name: 'TypeScript', type: 'skill' as const },
        ],
    },
    {
        id: 2,
        title: 'Cursor-Based Pagination in PostgreSQL',
        slug: 'cursor-based-pagination',
        is_published: false,
        published_at: null,
        reading_time_minutes: 4,
        views: 0,
        lead_image: null,
        related_count: 0,
        created_at: '2026-05-01T10:00:00+00:00',
        updated_at: '2026-05-01T10:00:00+00:00',
        tags: [{ id: 4, name: 'Backend', type: 'tag' as const }],
    },
];

const META = {
    total: 2,
    per_page: 12,
    current_page: 1,
    sort_column: 'id',
    sort_direction: 'desc' as const,
    cursors: {
        first: { id: 2, sort_value: null },
        last: { id: 1, sort_value: null },
        page: 1,
    },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(BlogListPage, {
        props: { articles: ARTICLES, meta: META, years: [2026, 2025], ...props },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

function mountTrashedPage(props: Record<string, unknown> = {}) {
    history.replaceState({}, '', '/admin/blog?trashed=1');
    const wrapper = mountPage(props);
    history.replaceState({}, '', '/admin/blog');

    return wrapper;
}

describe('BlogListPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        visitMock.mockClear();
        postMock.mockClear();
        patchMock.mockClear();
        delMock.mockClear();
        deleteMock.mockClear();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders within the admin layout with the page title and the Add Article CTA', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="blog-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="blog-list-page"] h1').text()).toBe('Blog');

        wrapper.get('[data-testid="blog-list-page-add"]').trigger('click');
        expect(visitMock).toHaveBeenCalledWith('/admin/blog/create');
    });

    it('renders one table row per article with its title', () => {
        const wrapper = mountPage();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Building Reusable Vue Composables');
    });

    it('renders the unified tag chips (article tags + skills) and the views column', () => {
        const wrapper = mountPage();

        expect(
            wrapper.get('[data-testid="blog-list-page-tags-1-chip-0"]').text(),
        ).toBe('Vue');
        expect(
            wrapper.get('[data-testid="blog-list-page-tags-1-chip-1"]').text(),
        ).toBe('TypeScript');
        expect(wrapper.get('[data-testid="blog-list-page-views-1"]').text()).toBe('1,204');
    });

    it('collapses the tags column into 3 chips + a "+N" ellipsis badge', () => {
        const wrapper = mountPage({
            articles: [
                {
                    ...ARTICLES[0],
                    tags: [
                        { id: 1, name: 'Vue', type: 'tag' as const },
                        { id: 2, name: 'Laravel', type: 'tag' as const },
                        { id: 3, name: 'TypeScript', type: 'skill' as const },
                        { id: 4, name: 'PostgreSQL', type: 'skill' as const },
                    ],
                },
            ],
        });

        expect(
            wrapper.findAll('[data-testid^="blog-list-page-tags-1-chip-"]'),
        ).toHaveLength(3);
        expect(wrapper.get('[data-testid="blog-list-page-tags-1-ellipsis"]').text()).toBe('+1');
    });

    it('renders the static related count and the lead-image previews with placeholders', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="blog-list-page-related-1"]').text()).toBe('1');
        expect(wrapper.get('[data-testid="blog-list-page-related-2"]').text()).toBe('0');

        const preview = wrapper.get('[data-testid="blog-list-page-preview-1"]');
        expect(preview.attributes('src')).toBe('/assets/blog/1/lead.png');
        expect(
            wrapper.find('[data-testid="blog-list-page-preview-placeholder-2"]').exists(),
        ).toBe(true);
    });

    it('shows the publish/unpublish toggle button per row state', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="blog-list-page-publish-toggle-1"]').text()).toBe(
            'Unpublish',
        );
        expect(wrapper.get('[data-testid="blog-list-page-publish-toggle-2"]').text()).toBe(
            'Publish',
        );
    });

    it('opens the edit form on the row click (no Edit button)', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="blog-list-page-edit-1"]').exists()).toBe(false);

        await wrapper.findAll('tbody tr')[0].trigger('click');
        expect(visitMock).toHaveBeenCalledWith('/admin/blog/1/edit');
    });

    it('toggles publication via the published-column PATCH and updates its label', async () => {
        patchMock.mockResolvedValue({ data: { ...ARTICLES[1], is_published: true } });
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-list-page-publish-toggle-2"]').trigger('click');

        expect(patchMock).toHaveBeenCalledWith('/admin/blog/2/publish');

        await new Promise((resolve) => setTimeout(resolve, 0));
        await nextTick();
        expect(
            wrapper.get('[data-testid="blog-list-page-publish-toggle-2"]').text(),
        ).toBe('Unpublish');
    });

    it('reverts the optimistic publish toggle and toasts on server error', async () => {
        patchMock.mockRejectedValue(new Error('nope'));
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-list-page-publish-toggle-2"]').trigger('click');
        await nextTick();

        expect(
            wrapper.get('[data-testid="blog-list-page-publish-toggle-2"]').text(),
        ).toBe('Publish');
    });

    it('shows the bulk bar with the live-view actions and publishes the selection', async () => {
        postMock.mockResolvedValue({});
        const wrapper = mountPage();
        const table = wrapper.findComponent({ name: 'DataTable' });

        table.vm.$emit('update:selected_ids', [1, 2]);
        await nextTick();

        const bar = wrapper.get('[data-testid="blog-list-page-bulk-bar"]');
        expect(bar.text()).toContain('2 items selected');

        await wrapper.get('[data-testid="blog-list-page-bulk-bar-action-publish"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith('/admin/blog/bulk-publish', {
            json: { ids: [1, 2] },
        });
    });

    it('deletes a single article after the confirmation dialog', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-list-page-delete-1"]').trigger('click');
        expect(wrapper.find('[data-testid="blog-list-page-delete-dialog"]').exists()).toBe(true);

        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith(
            '/admin/blog/1',
            expect.objectContaining({
                onFinish: expect.any(Function),
                onError: expect.any(Function),
            }),
        );
    });

    it('renders the trashed view with trashed badges and restore/force actions', async () => {
        const wrapper = mountTrashedPage();

        expect(wrapper.get('[data-testid="blog-list-page-badge-trashed-1"]').text()).toBe(
            'Trashed',
        );
        expect(wrapper.find('[data-testid="blog-list-page-delete-1"]').exists()).toBe(false);

        await wrapper.get('[data-testid="blog-list-page-restore-1"]').trigger('click');
        expect(patchMock).toHaveBeenCalledWith(
            '/admin/blog/1/restore',
            undefined,
            expect.any(Object),
        );
    });

    it('force-deletes a trashed article after confirmation', async () => {
        const wrapper = mountTrashedPage();

        await wrapper.get('[data-testid="blog-list-page-force-delete-1"]').trigger('click');
        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith('/admin/blog/1/force', expect.any(Object));
    });

    it('searches via the autocomplete submit carrying the q param', async () => {
        const wrapper = mountPage();
        const search = wrapper.findComponent({ name: 'AdminSearchAutocomplete' });

        search.vm.$emit('submit', 'composables');
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/blog',
            { q: 'composables' },
            expect.any(Object),
        );
    });

    it('applies the unified tag AND filter split into article_tags and skill_tags', async () => {
        const wrapper = mountPage();
        const picker = wrapper.findComponent({ name: 'AdminBlogTagPicker' });

        picker.vm.$emit('update:modelValue', [
            { id: 1, name: 'Vue', type: 'tag' as const },
            { id: 2, name: 'TypeScript', type: 'skill' as const },
        ]);
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/blog',
            { article_tags: '1', skill_tags: '2' },
            expect.any(Object),
        );
    });

    it('applies the published status filter', async () => {
        const wrapper = mountPage();
        const dropdown = wrapper.findComponent({ name: 'AdminBaseDropdown' });

        dropdown.vm.$emit('update:modelValue', 'unpublished');
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/blog',
            { published: '0' },
            expect.any(Object),
        );
    });

    it('applies the year filter from the dropdown options', async () => {
        const wrapper = mountPage();
        const dropdowns = wrapper.findAllComponents({ name: 'AdminBaseDropdown' });

        dropdowns[1].vm.$emit('update:modelValue', '2025');
        await nextTick();

        expect(getMock).toHaveBeenCalledWith('/admin/blog', { year: '2025' }, expect.any(Object));
    });

    it('offers the explicit "All years" option and resets the year filter with it', async () => {
        const wrapper = mountPage();
        const dropdowns = wrapper.findAllComponents({ name: 'AdminBaseDropdown' });
        const yearDropdown = dropdowns[1];

        expect(
            yearDropdown.props('options').find((option: { value: string }) => option.value === ''),
        ).toBeDefined();

        yearDropdown.vm.$emit('update:modelValue', '');
        await nextTick();

        expect(getMock).toHaveBeenCalledWith('/admin/blog', {}, expect.any(Object));
    });

    it('toggles the trashed view via the Show trashed checkbox', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-list-page-trashed"]').setValue(true);

        expect(getMock).toHaveBeenCalledWith(
            '/admin/blog',
            { trashed: '1' },
            expect.any(Object),
        );
    });

    it('switches to the grid view with article cards (lead image + placeholder)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-list-page-grid-view"]').trigger('click');

        expect(wrapper.find('[data-testid="blog-list-page-grid"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="blog-list-page-grid-card-1"]').text()).toContain(
            'Building Reusable Vue Composables',
        );
        expect(
            wrapper.get('[data-testid="blog-list-page-grid-card-1-publish-toggle"]').text(),
        ).toBe('Unpublish');
        expect(
            wrapper.get('[data-testid="blog-list-page-grid-card-1-image"]').attributes('src'),
        ).toBe('/assets/blog/1/lead.png');
        expect(
            wrapper.find('[data-testid="blog-list-page-grid-card-2-image-placeholder"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-testid="blog-list-page-table"]').exists()).toBe(false);
    });

    it('caps the grid card tags at 3 chips + a "+N" ellipsis badge', async () => {
        const wrapper = mountPage({
            articles: [
                {
                    ...ARTICLES[0],
                    tags: [
                        { id: 1, name: 'Vue', type: 'tag' as const },
                        { id: 2, name: 'Laravel', type: 'tag' as const },
                        { id: 3, name: 'TypeScript', type: 'skill' as const },
                        { id: 4, name: 'PostgreSQL', type: 'skill' as const },
                        { id: 5, name: 'Redis', type: 'skill' as const },
                    ],
                },
            ],
        });

        await wrapper.get('[data-testid="blog-list-page-grid-view"]').trigger('click');

        expect(
            wrapper.findAll(
                '[data-testid^="blog-list-page-grid-card-1-tag-"]:not([data-testid="blog-list-page-grid-card-1-tag-ellipsis"])',
            ),
        ).toHaveLength(3);
        expect(
            wrapper.get('[data-testid="blog-list-page-grid-card-1-tag-ellipsis"]').text(),
        ).toBe('+2');
    });

    it('navigates with the cursor params merged with the active filters', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-list-page-trashed"]').setValue(true);

        const pagination = wrapper.findComponent({ name: 'PaginationControls' });
        pagination.vm.$emit('navigate', { cursor: { id: 2, sort_value: null } });
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(getMock).toHaveBeenLastCalledWith(
            '/admin/blog',
            { trashed: '1', 'cursor[id]': 2, 'cursor[before]': 1 },
            expect.any(Object),
        );
    });

    it('shows the empty state with a create CTA when there are no articles', () => {
        const wrapper = mountPage({ articles: [] });

        const empty = wrapper.get('[data-testid="blog-list-page-table-empty"]');
        expect(empty.text()).toContain('No articles yet.');
    });

    it('shows the no-results state with clear filters when a filter yields nothing', () => {
        history.replaceState({}, '', '/admin/blog?q=nothing');
        const wrapper = mountPage({ articles: [] });

        const empty = wrapper.get('[data-testid="blog-list-page-table-empty"]');
        expect(empty.text()).toContain('No articles match your filters.');
        history.replaceState({}, '', '/admin/blog');
    });
});
