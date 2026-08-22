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

import TagListPage from '@blog/Admin/Pages/TagListPage.vue';

const TAGS = [
    { id: 1, slug: 'laravel', name: 'Laravel', articles_use_count: 12 },
    { id: 2, slug: 'vue', name: 'Vue', articles_use_count: 0 },
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
    return mount(TagListPage, {
        props: { tags: TAGS, meta: META, ...props },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('TagListPage', () => {
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

    it('renders within the admin layout with the title and the Add Tag CTA', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="tag-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="tag-list-page"] h1').text()).toBe('Tags');

        wrapper.get('[data-testid="tag-list-page-add"]').trigger('click');
        expect(visitMock).toHaveBeenCalledWith('/admin/tags/create');
    });

    it('renders one table row per tag with name, slug and article count', () => {
        const wrapper = mountPage();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Laravel');
        expect(rows[0].text()).toContain('laravel');
        expect(rows[0].text()).toContain('12');
    });

    it('opens the edit form on the row click (no Edit button)', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="tag-list-page-edit-1"]').exists()).toBe(false);

        await wrapper.findAll('tbody tr')[0].trigger('click');
        expect(visitMock).toHaveBeenCalledWith('/admin/tags/1/edit');
    });

    it('searches by name with a debounced q param', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage();

        await wrapper.get('[data-testid="tag-list-page-search"]').setValue('lar');
        vi.advanceTimersByTime(400);
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/tags',
            { q: 'lar' },
            expect.any(Object),
        );
    });

    it('warns about the article usage count in the delete confirmation', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="tag-list-page-delete-1"]').trigger('click');

        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        expect(dialog.props('title')).toContain('12 articles use this tag');
    });

    it('deletes a tag after the confirmation dialog', () => {
        const wrapper = mountPage();

        wrapper.get('[data-testid="tag-list-page-delete-1"]').trigger('click');
        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith(
            '/admin/tags/1',
            expect.objectContaining({
                onFinish: expect.any(Function),
                onError: expect.any(Function),
            }),
        );
    });

    it('navigates with the cursor params via PaginationControls', async () => {
        const wrapper = mountPage();
        const pagination = wrapper.findComponent({ name: 'PaginationControls' });

        pagination.vm.$emit('navigate', { cursor: { id: 2, sort_value: null } });
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/tags',
            { 'cursor[id]': 2, 'cursor[before]': 1 },
            expect.any(Object),
        );
    });

    it('shows the empty state with a create CTA when there are no tags', () => {
        const wrapper = mountPage({ tags: [] });

        const empty = wrapper.get('[data-testid="tag-list-page-table-empty"]');
        expect(empty.text()).toContain('No tags yet.');
    });

    it('shows the no-results state when a search yields nothing', async () => {
        history.replaceState({}, '', '/admin/tags?q=zzz');
        const wrapper = mountPage({ tags: [] });
        await nextTick();

        const empty = wrapper.get('[data-testid="tag-list-page-table-empty"]');
        expect(empty.text()).toContain('No tags match your filters.');
        history.replaceState({}, '', '/admin/tags');
    });
});
