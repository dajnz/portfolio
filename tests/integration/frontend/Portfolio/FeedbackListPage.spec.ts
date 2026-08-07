import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { getMock, visitMock, pageProps } = vi.hoisted(() => ({
    getMock: vi.fn(),
    visitMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: { get: getMock, visit: visitMock },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import FeedbackListPage from '@portfolio/Admin/Pages/FeedbackListPage.vue';

const LONG_MESSAGE = 'x'.repeat(200);

const FEEDBACK = [
    {
        id: 1,
        sender_name: 'Jane Doe',
        sender_email: 'jane@example.com',
        message: LONG_MESSAGE,
        is_read: false,
        is_forwarded: true,
        created_at: '2026-07-14T09:32:00+00:00',
    },
    {
        id: 2,
        sender_name: 'Mark',
        sender_email: 'mark@example.org',
        message: 'Short message.',
        is_read: true,
        is_forwarded: false,
        created_at: '2026-07-12T17:05:00+00:00',
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
    return mount(FeedbackListPage, {
        props: { feedback: FEEDBACK, meta: META, ...props },
    });
}

describe('FeedbackListPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        visitMock.mockClear();
        window.localStorage.clear();
    });

    it('renders within the admin layout with the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="feedback-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="feedback-list-page"] h1').text()).toBe('Feedback');
    });

    it('renders the feedback rows with date, sender email and excerpt', () => {
        const wrapper = mountPage();

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('2026-07-14 09:32');
        expect(rows[0].text()).toContain('jane@example.com');
    });

    it('truncates long messages to 120 characters with an ellipsis', () => {
        const wrapper = mountPage();

        const firstRow = wrapper.findAll('tbody tr')[0];
        expect(firstRow.text()).toContain(`${'x'.repeat(120)}…`);
        expect(firstRow.text()).not.toContain('x'.repeat(121));
    });

    it('marks unread rows with the unread badge and forwarded rows with the forwarded badge', () => {
        const wrapper = mountPage();

        const unread = wrapper.get('[data-testid="feedback-list-page-read-badge-1"]');
        expect(unread.text()).toBe('Unread');
        expect(unread.classes()).toContain('bg-danger-50');

        expect(wrapper.find('[data-testid="feedback-list-page-answered-badge-1"]').exists()).toBe(true);

        const read = wrapper.get('[data-testid="feedback-list-page-read-badge-2"]');
        expect(read.text()).toBe('Read');
        expect(wrapper.find('[data-testid="feedback-list-page-answered-badge-2"]').exists()).toBe(false);
    });

    it('navigates to the detail page on row click', async () => {
        const wrapper = mountPage();

        await wrapper.findAll('tbody tr')[0].trigger('click');

        expect(visitMock).toHaveBeenCalledWith('/admin/feedback/1');
    });

    it('navigates with the cursor params', async () => {
        const wrapper = mountPage();

        const pagination = wrapper.findComponent({ name: 'PaginationControls' });
        pagination.vm.$emit('navigate', { cursor: { id: 2, sort_value: null } });

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(getMock).toHaveBeenCalledWith(
            '/admin/feedback',
            { 'cursor[id]': 2, 'cursor[before]': 1 },
            expect.any(Object),
        );
    });

    it('shows the empty state when there is no feedback', () => {
        const wrapper = mountPage({ feedback: [] });

        expect(wrapper.find('[data-testid="feedback-list-page-table-empty"]').exists()).toBe(true);
    });
});
