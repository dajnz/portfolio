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
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

vi.mock('@shared-cross/Composables/useJsonApi', () => ({
    useJsonApi: () => ({
        get: getMock,
        post: postMock,
        patch: patchMock,
        del: delMock,
    }),
}));

import ProjectsListPage from '@projects/Admin/Pages/ProjectsListPage/ProjectsListPage.vue';

const PROJECTS = [
    {
        id: 1,
        title: 'Portfolio Platform',
        slug: 'portfolio-platform',
        preview_image: 'projects/preview.png',
        is_published: true,
        is_featured: true,
        featured_sort_order: 1,
        tech_tags_count: 2,
        tech_tags: [
            { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
            { id: 2, name: 'PostgreSQL', icon: 'devicon:postgresql' },
        ],
        created_at: '2026-06-15T10:00:00+00:00',
        updated_at: '2026-06-15T10:00:00+00:00',
    },
    {
        id: 2,
        title: 'REST API with Rust',
        slug: 'rest-api-rust',
        preview_image: null,
        is_published: false,
        is_featured: false,
        featured_sort_order: null,
        tech_tags_count: 1,
        tech_tags: [{ id: 3, name: 'Rust', icon: 'devicon:rust' }],
        created_at: '2026-05-01T10:00:00+00:00',
        updated_at: '2026-05-01T10:00:00+00:00',
    },
];

const FEATURED = [PROJECTS[0]];

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
    return mount(ProjectsListPage, {
        props: { projects: PROJECTS, featured_projects: FEATURED, meta: META, ...props },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

function mountTrashedPage(props: Record<string, unknown> = {}) {
    history.replaceState({}, '', '/admin/projects?trashed=1');
    const wrapper = mountPage(props);
    history.replaceState({}, '', '/admin/projects');

    return wrapper;
}

describe('ProjectsListPage', () => {
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

    it('renders within the admin layout with the page title and the Add Project CTA', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="projects-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="projects-list-page"] h1').text()).toBe('Projects');

        wrapper.get('[data-testid="projects-list-page-add"]').trigger('click');
        expect(visitMock).toHaveBeenCalledWith('/admin/projects/create');
    });

    it('renders the preview image and the placeholder for image-less rows', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="projects-list-page-preview-1"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-testid="projects-list-page-preview-placeholder-2"]').exists(),
        ).toBe(true);
    });

    it('renders one table row per project with its title', () => {
        const wrapper = mountPage();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Portfolio Platform');
    });

    it('renders the static tag chips without an ellipsis when they fit', () => {
        const wrapper = mountPage();

        expect(
            wrapper.get('[data-testid="projects-list-page-tags-1-chip-1"]').text(),
        ).toBe('Laravel');
        expect(
            wrapper.get('[data-testid="projects-list-page-tags-1-chip-2"]').text(),
        ).toBe('PostgreSQL');
        // No ellipsis chip when the tag count fits the 3-tag default.
        expect(wrapper.find('[data-testid="projects-list-page-tags-1-ellipsis"]').exists()).toBe(
            false,
        );
    });

    it('renders the publish/unpublish toggle per row state', () => {
        const wrapper = mountPage();

        // The published column is an active toggle button — the label is
        // the action, the look depends on the state.
        const published = wrapper.get('[data-testid="projects-list-page-publish-toggle-1"]');
        expect(published.text()).toBe('Unpublish');
        expect(published.classes()).toContain('bg-success-600!');

        const draft = wrapper.get('[data-testid="projects-list-page-publish-toggle-2"]');
        expect(draft.text()).toBe('Publish');
        expect(draft.classes()).toContain('border-primary-600!');
    });

    it('does not render per-row edit buttons (the row click navigates)', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="projects-list-page-edit-1"]').exists()).toBe(false);
    });

    it('shows at most 3 tag chips per row and collapses the rest into +N', () => {
        const wrapper = mountPage({
            projects: [
                {
                    ...PROJECTS[0],
                    tech_tags: [
                        { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
                        { id: 2, name: 'PHP', icon: 'devicon:php' },
                        { id: 3, name: 'Docker', icon: 'devicon:docker' },
                        { id: 4, name: 'Redis', icon: 'devicon:redis' },
                        { id: 5, name: 'Nginx', icon: 'devicon:nginx' },
                    ],
                },
            ],
            featured_projects: [],
        });

        expect(wrapper.get('[data-testid="projects-list-page-tags-1-chip-1"]').text()).toBe(
            'Laravel',
        );
        expect(wrapper.get('[data-testid="projects-list-page-tags-1-chip-3"]').text()).toBe(
            'Docker',
        );
        expect(wrapper.find('[data-testid="projects-list-page-tags-1-chip-4"]').exists()).toBe(
            false,
        );
        expect(wrapper.get('[data-testid="projects-list-page-tags-1-ellipsis"]').text()).toBe('+2');
    });

    it('navigates to the edit form on row click but not from an action button', async () => {
        const wrapper = mountPage();

        await wrapper.findAll('tbody tr')[0].trigger('click');
        expect(visitMock).toHaveBeenCalledWith('/admin/projects/1/edit');
        visitMock.mockClear();

        await wrapper.get('[data-testid="projects-list-page-delete-1"]').trigger('click');
        expect(visitMock).not.toHaveBeenCalled();
    });

    it('renders the sticky controls bar with the filters and the Add Project button', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="projects-list-page-filters-bar"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-testid="projects-list-page-filters-bar"] [data-testid="projects-list-page-search-input"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="projects-list-page-filters-bar"] [data-testid="projects-list-page-add"]').exists(),
        ).toBe(true);
    });

    it('toggles the feature star optimistically via PATCH feature/unfeature', async () => {
        patchMock.mockResolvedValue({
            data: { ...PROJECTS[1], is_featured: true, featured_sort_order: 2 },
        });
        const wrapper = mountPage();

        // Project 2 is not featured — clicking the star features it and
        // it joins the featured reorder section.
        await wrapper.get('[data-testid="projects-list-page-feature-2"]').trigger('click');

        expect(patchMock).toHaveBeenCalledWith('/admin/projects/2/feature');

        await nextTick();
        const item = wrapper.get('[data-testid="projects-list-page-featured-item-2"]');
        expect(item.text()).toContain('REST API with Rust');
    });

    it('reverts the optimistic feature toggle and toasts on server error', async () => {
        patchMock.mockRejectedValue(new Error('nope'));
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-feature-2"]').trigger('click');
        await nextTick();

        // Rolled back to the props truth — the star is not filled.
        expect(wrapper.get('[data-testid="projects-list-page-feature-2"]').attributes('aria-pressed')).toBe(
            'false',
        );
    });

    it('toggles publication via the published-column button and updates its label', async () => {
        patchMock.mockResolvedValue({ data: { ...PROJECTS[1], is_published: true } });
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-publish-toggle-2"]').trigger('click');

        expect(patchMock).toHaveBeenCalledWith('/admin/projects/2/publish');

        await new Promise((resolve) => setTimeout(resolve, 0));
        await nextTick();
        const toggle = wrapper.get('[data-testid="projects-list-page-publish-toggle-2"]');
        expect(toggle.text()).toBe('Unpublish');
        expect(toggle.attributes('aria-pressed')).toBe('true');
    });

    it('shows the bulk bar with the live-view actions and publishes the selection', async () => {
        postMock.mockResolvedValue({});
        const wrapper = mountPage();
        const table = wrapper.findComponent({ name: 'DataTable' });

        table.vm.$emit('update:selected_ids', [1, 2]);
        await nextTick();

        const bar = wrapper.get('[data-testid="projects-list-page-bulk-bar"]');
        expect(bar.text()).toContain('2 items selected');

        await wrapper.get('[data-testid="projects-list-page-bulk-bar-action-publish"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith('/admin/projects/bulk-publish', {
            json: { ids: [1, 2] },
        });
    });

    it('bulk-deletes the selection through the confirmation dialog', async () => {
        postMock.mockResolvedValue({});
        const wrapper = mountPage();
        const table = wrapper.findComponent({ name: 'DataTable' });

        table.vm.$emit('update:selected_ids', [1, 2]);
        await nextTick();
        await wrapper.get('[data-testid="projects-list-page-bulk-bar-action-delete"]').trigger('click');

        expect(wrapper.find('[data-testid="projects-list-page-delete-dialog"]').exists()).toBe(true);

        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });
        await nextTick();

        expect(postMock).toHaveBeenCalledWith('/admin/projects/bulk-delete', {
            json: { ids: [1, 2] },
        });
    });

    it('deletes a single project after the confirmation dialog', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-delete-1"]').trigger('click');
        expect(wrapper.find('[data-testid="projects-list-page-delete-dialog"]').exists()).toBe(true);

        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith(
            '/admin/projects/1',
            expect.objectContaining({
                onFinish: expect.any(Function),
                onError: expect.any(Function),
            }),
        );
    });

    it('renders the trashed view with trashed badges and restore/force actions', async () => {
        const wrapper = mountTrashedPage();

        expect(wrapper.get('[data-testid="projects-list-page-badge-trashed-1"]').text()).toBe(
            'Trashed',
        );
        expect(wrapper.find('[data-testid="projects-list-page-delete-1"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="projects-list-page-featured-reorder"]').exists()).toBe(
            false,
        );

        await wrapper.get('[data-testid="projects-list-page-restore-1"]').trigger('click');
        expect(patchMock).toHaveBeenCalledWith(
            '/admin/projects/1/restore',
            undefined,
            expect.any(Object),
        );
    });

    it('force-deletes a trashed project after confirmation', async () => {
        const wrapper = mountTrashedPage();

        await wrapper.get('[data-testid="projects-list-page-force-delete-1"]').trigger('click');
        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith('/admin/projects/1/force', expect.any(Object));
    });

    it('posts the featured reorder ids after a SortableList drag', async () => {
        postMock.mockResolvedValue({});
        const wrapper = mountPage();
        const list = wrapper.findComponent({ name: 'SortableList' });

        list.vm.$emit('drag-end', [{ ...PROJECTS[0], id: 1 }], null);
        await nextTick();

        expect(postMock).toHaveBeenCalledWith('/admin/projects/reorder-featured', {
            json: { ids: [1] },
        });
    });

    it('searches via the autocomplete submit carrying the q param', async () => {
        const wrapper = mountPage();
        const search = wrapper.findComponent({ name: 'AdminSearchAutocomplete' });

        search.vm.$emit('submit', 'port');
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/projects',
            { q: 'port' },
            expect.any(Object),
        );
    });

    it('applies the featured-only checkbox filter', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-featured"]').setValue(true);

        expect(getMock).toHaveBeenCalledWith(
            '/admin/projects',
            { featured: '1' },
            expect.any(Object),
        );
    });

    it('applies the tech-tag AND filter', async () => {
        const wrapper = mountPage();
        const picker = wrapper.findComponent({ name: 'AdminTechSkillPicker' });

        picker.vm.$emit('update:modelValue', [
            { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
            { id: 2, name: 'PostgreSQL', icon: 'devicon:postgresql' },
        ]);
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/projects',
            { tags: '1,2' },
            expect.any(Object),
        );
    });

    it('applies the published status filter', async () => {
        const wrapper = mountPage();
        const dropdown = wrapper.findComponent({ name: 'AdminBaseDropdown' });

        dropdown.vm.$emit('update:modelValue', 'unpublished');
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/projects',
            { published: '0' },
            expect.any(Object),
        );
    });

    it('toggles the trashed view via the Show trashed checkbox', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-trashed"]').setValue(true);

        expect(getMock).toHaveBeenCalledWith(
            '/admin/projects',
            { trashed: '1' },
            expect.any(Object),
        );
    });

    it('switches to the grid view with project cards', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-grid-view"]').trigger('click');

        expect(wrapper.find('[data-testid="projects-list-page-grid"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="projects-list-page-grid-card-1"]').text()).toContain(
            'Portfolio Platform',
        );
        // The card shows the publish/unpublish toggle instead of the
        // state badge; no edit button (the card click navigates).
        expect(
            wrapper.get('[data-testid="projects-list-page-grid-card-1-publish-toggle"]').text(),
        ).toBe('Unpublish');
        expect(wrapper.get('[data-testid="projects-list-page-grid-card-2-publish-toggle"]').text()).toBe(
            'Publish',
        );
        expect(
            wrapper.find('[data-testid="projects-list-page-grid-card-1-edit"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-testid="projects-list-page-table"]').exists()).toBe(false);
    });

    it('navigates with the cursor params merged with the active filters', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="projects-list-page-trashed"]').setValue(true);

        const pagination = wrapper.findComponent({ name: 'PaginationControls' });
        pagination.vm.$emit('navigate', { cursor: { id: 2, sort_value: null } });
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(getMock).toHaveBeenLastCalledWith(
            '/admin/projects',
            { trashed: '1', 'cursor[id]': 2, 'cursor[before]': 1 },
            expect.any(Object),
        );
    });

    it('shows the empty state with a create CTA when there are no projects', () => {
        const wrapper = mountPage({ projects: [], featured_projects: [] });

        const empty = wrapper.get('[data-testid="projects-list-page-table-empty"]');
        expect(empty.text()).toContain('No projects yet.');
    });

    it('shows the no-results state with clear filters when a filter yields nothing', () => {
        history.replaceState({}, '', '/admin/projects?q=nothing');
        const wrapper = mountPage({ projects: [], featured_projects: [] });

        const empty = wrapper.get('[data-testid="projects-list-page-table-empty"]');
        expect(empty.text()).toContain('No projects match your filters.');
        history.replaceState({}, '', '/admin/projects');
    });
});
