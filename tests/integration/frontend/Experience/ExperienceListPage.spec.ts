import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const { getMock, visitMock, deleteMock, patchMock, pageProps } = vi.hoisted(() => ({
    getMock: vi.fn(),
    visitMock: vi.fn(),
    deleteMock: vi.fn(),
    patchMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
        supported_locales: ['en', 'ru'],
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: getMock,
        visit: visitMock,
        delete: deleteMock,
        patch: patchMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import ExperienceListPage from '@experience/Admin/Pages/ExperienceListPage.vue';

const ITEMS = [
    {
        id: 1,
        position: 'Senior Backend Engineer',
        company_name: 'Acme Corp',
        started_at: '2023-01-15',
        ended_at: '2026-02-28',
        is_published: true,
        include_in_resume: true,
        created_at: '2026-07-01T10:00:00+00:00',
    },
    {
        id: 2,
        position: 'Full-Stack Developer',
        company_name: 'Freelance',
        started_at: '2020-06-01',
        ended_at: null,
        is_published: false,
        include_in_resume: false,
        created_at: '2026-06-01T10:00:00+00:00',
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
    return mount(ExperienceListPage, {
        props: { items: ITEMS, meta: META, ...props },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

// The trashed view is driven by the `trashed=1` query param — the page
// reads it from the URL on mount.
function mountTrashedPage(props: Record<string, unknown> = {}) {
    history.replaceState({}, '', '/admin/experience?trashed=1');
    const wrapper = mountPage(props);
    history.replaceState({}, '', '/admin/experience');

    return wrapper;
}

describe('ExperienceListPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        visitMock.mockClear();
        deleteMock.mockClear();
        patchMock.mockClear();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders within the admin layout with the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="experience-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="experience-list-page"] h1').text()).toBe('Experience');
    });

    it('renders the rows with position, company, dates and badges', () => {
        const wrapper = mountPage();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Senior Backend Engineer');
        expect(rows[0].text()).toContain('Acme Corp');
        expect(wrapper.get('[data-testid="experience-list-page-dates-1"]').text()).toBe(
            '15.01.2023 — 28.02.2026',
        );

        const published = wrapper.get('[data-testid="experience-list-page-badge-1"]');
        expect(published.text()).toBe('Published');
        expect(published.classes()).toContain('bg-success-50');

        const draft = wrapper.get('[data-testid="experience-list-page-badge-2"]');
        expect(draft.text()).toBe('Draft');
        expect(draft.classes()).toContain('bg-warning-50');
    });

    it('shows "Present" for current roles and the resume badge only when included', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="experience-list-page-dates-2"]').text()).toBe(
            '01.06.2020 — Present',
        );
        expect(wrapper.find('[data-testid="experience-list-page-resume-1"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="experience-list-page-resume-2"]').exists()).toBe(false);
    });

    it('shows a translated default badge for items not included in the resume', () => {
        const wrapper = mountPage();

        const badge = wrapper.get('[data-testid="experience-list-page-not-resume-2"]');
        expect(badge.text()).toBe('Not in resume');
        expect(badge.classes()).toContain('bg-slate-100');
    });

    it('navigates to the create form via the Add Experience button', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-list-page-add"]').trigger('click');

        expect(visitMock).toHaveBeenCalledWith('/admin/experience/create');
    });

    it('switches to the edit form on row click (no Edit button)', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="experience-list-page-edit-1"]').exists()).toBe(false);

        await wrapper.findAll('tbody tr')[0].trigger('click');

        expect(visitMock).toHaveBeenCalledWith('/admin/experience/1/edit');
    });

    it('does not navigate when a row action button is clicked', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-list-page-delete-1"]').trigger('click');

        expect(visitMock).not.toHaveBeenCalled();
    });

    it('searches with a debounced request carrying the q param', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-list-page-search"]').setValue('acme');
        vi.advanceTimersByTime(400);
        await nextTick();

        expect(getMock).toHaveBeenCalledWith(
            '/admin/experience',
            { q: 'acme' },
            expect.any(Object),
        );
    });

    it('toggles the trashed view via the Show trashed checkbox', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-list-page-trashed"]').setValue(true);

        expect(getMock).toHaveBeenCalledWith(
            '/admin/experience',
            { trashed: '1' },
            expect.any(Object),
        );
    });

    it('sorts by date with a single merged navigation (column + direction events)', async () => {
        const wrapper = mountPage();
        const table = wrapper.findComponent({ name: 'DataTable' });

        table.vm.$emit('update:sort_column', 'started_at');
        table.vm.$emit('update:sort_direction', 'asc');
        await nextTick();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/admin/experience',
            { sort_column: 'started_at', sort_direction: 'asc' },
            expect.any(Object),
        );
    });

    it('sorts by clicking the Dates column header (DataTable emit → page listener)', async () => {
        const wrapper = mountPage();

        // The Dates header button — the DataTable's real click path emits
        // the snake_case `update:sort_column`/`update:sort_direction`
        // events; the page must listen with the exact same names (Vue
        // camelizes only hyphens, so kebab listeners never fire).
        const datesHeader = wrapper
            .findAll('thead button')
            .find((button) => button.text().includes('Dates'));
        expect(datesHeader).toBeDefined();
        await datesHeader!.trigger('click');
        await nextTick();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/admin/experience',
            { sort_column: 'started_at', sort_direction: 'asc' },
            expect.any(Object),
        );
    });

    it('navigates with the cursor params merged with the active filters', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-list-page-trashed"]').setValue(true);

        const pagination = wrapper.findComponent({ name: 'PaginationControls' });
        pagination.vm.$emit('navigate', { cursor: { id: 2, sort_value: null } });
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(getMock).toHaveBeenLastCalledWith(
            '/admin/experience',
            { trashed: '1', 'cursor[id]': 2, 'cursor[before]': 1 },
            expect.any(Object),
        );
    });

    it('deletes an item after the confirmation dialog', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-list-page-delete-1"]').trigger('click');
        expect(wrapper.find('[data-testid="experience-list-page-delete-dialog"]').exists()).toBe(true);

        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith(
            '/admin/experience/1',
            expect.objectContaining({
                onSuccess: expect.any(Function),
                onError: expect.any(Function),
            }),
        );
    });

    it('renders the trashed view: trashed badge, no delete button, no edit navigation', async () => {
        const wrapper = mountTrashedPage();

        expect(wrapper.get('[data-testid="experience-list-page-badge-trashed-1"]').text()).toBe(
            'Trashed',
        );
        expect(wrapper.find('[data-testid="experience-list-page-delete-1"]').exists()).toBe(false);

        // Trashed rows are not editable (the backend's edit lookup
        // excludes soft-deleted items) — a row click must not navigate.
        await wrapper.findAll('tbody tr')[0].trigger('click');
        expect(visitMock).not.toHaveBeenCalled();
    });

    it('restores an item from the trashed view', async () => {
        const wrapper = mountTrashedPage();

        await wrapper.get('[data-testid="experience-list-page-restore-1"]').trigger('click');

        expect(patchMock).toHaveBeenCalledWith(
            '/admin/experience/1/restore',
            undefined,
            expect.any(Object),
        );
    });

    it('force-deletes an item from the trashed view after confirmation', async () => {
        const wrapper = mountTrashedPage();

        await wrapper.get('[data-testid="experience-list-page-force-delete-1"]').trigger('click');
        const dialog = wrapper.findComponent({ name: 'ConfirmationDialog' });
        dialog.vm.$emit('confirm', { password: null });

        expect(deleteMock).toHaveBeenCalledWith('/admin/experience/1/force', expect.any(Object));
    });

    it('shows the empty state with a create CTA when there are no items', () => {
        const wrapper = mountPage({ items: [] });

        const empty = wrapper.get('[data-testid="experience-list-page-table-empty"]');
        expect(empty.text()).toContain('No experience items yet.');
    });

    it('shows the no-results state with clear filters when a filter yields nothing', () => {
        history.replaceState({}, '', '/admin/experience?q=nothing');
        const wrapper = mountPage({ items: [] });

        const empty = wrapper.get('[data-testid="experience-list-page-table-empty"]');
        expect(empty.text()).toContain('No experience items match your filters.');
        history.replaceState({}, '', '/admin/experience');
    });
});
