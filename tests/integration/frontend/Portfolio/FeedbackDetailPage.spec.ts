import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { deleteMock, pageProps } = vi.hoisted(() => ({
    deleteMock: vi.fn(),
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
    router: { delete: deleteMock },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import FeedbackDetailPage from '@portfolio/Admin/Pages/FeedbackDetailPage.vue';

const ITEM = {
    id: 7,
    sender_name: 'Jane Doe',
    sender_email: 'jane@example.com',
    message: 'Love the new blog layout! The code blocks get cut off on mobile.',
    is_read: false,
    is_forwarded: false,
    created_at: '2026-07-14T09:32:00+00:00',
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(FeedbackDetailPage, {
        props: { item: ITEM, ...props },
        // The ConfirmationDialog teleports to body — stub Teleport so the
        // dialog markup renders inline for assertions.
        global: { stubs: { Teleport: true } },
    });
}

describe('FeedbackDetailPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        deleteMock.mockClear();
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
        document.cookie = 'XSRF-TOKEN=abc123%2Bdef';
    });

    it('renders the message, meta and badges', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="feedback-detail-page-message"]').text()).toContain(
            'Love the new blog layout!',
        );
        expect(wrapper.get('[data-testid="feedback-detail-page"]').text()).toContain('Jane Doe');
        expect(wrapper.get('[data-testid="feedback-detail-page"]').text()).toContain(
            'jane@example.com',
        );
        expect(wrapper.get('[data-testid="feedback-detail-page-read-badge"]').text()).toBe('Unread');
    });

    it('fires the mark-as-read JSON PATCH automatically on mount', () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) });
        vi.mocked(fetch).mockImplementation(fetchMock);

        mountPage();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/feedback/7/read',
            expect.objectContaining({ method: 'PATCH' }),
        );
    });

    it('opens a mailto link and fires the forwarded PATCH on forward', async () => {
        const locationMock = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { href: '' },
            writable: true,
        });
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({}) });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountPage();
        await wrapper.get('[data-testid="feedback-detail-page-forward-btn"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(window.location.href).toContain('mailto:jane%40example.com');
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/feedback/7/forwarded',
            expect.objectContaining({ method: 'PATCH' }),
        );
        expect(wrapper.find('[data-testid="feedback-detail-page-answered-badge"]').exists()).toBe(
            true,
        );
        locationMock();
    });

    it('deletes the feedback after confirmation', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="feedback-detail-page-delete-btn"]').trigger('click');
        await wrapper.get('[data-testid="feedback-detail-page-delete-dialog-confirm"]').trigger('click');

        expect(deleteMock).toHaveBeenCalledWith('/admin/feedback/7', expect.any(Object));
    });

    it('links back to the feedback list', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="feedback-detail-page-back-btn"]').attributes('href')).toBe(
            '/admin/feedback',
        );
    });
});

