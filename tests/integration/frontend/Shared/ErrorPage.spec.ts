import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { page } = vi.hoisted(() => ({
    page: { props: { auth: { user: null as unknown } } },
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    return { ...actual, usePage: () => page };
});

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ErrorPage from '@shared-cross/Pages/ErrorPage.vue';

const ERROR_PROPS = {
    status: 404,
    message: 'Not found.',
    request_id: 'abc-123',
};

describe('ErrorPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        page.props.auth.user = null;
        window.localStorage.clear();
    });

    it('renders within the public layout with the error details', () => {
        const wrapper = mount(ErrorPage, { props: ERROR_PROPS });

        expect(wrapper.find('[data-testid="error-page-layout-public"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="error-page-code"]').text()).toBe('404');
        expect(wrapper.get('[data-testid="error-page-message"]').text()).toBe('Not found.');
        expect(wrapper.get('[data-testid="error-page-request-id"]').text()).toBe(
            'Reference ID: abc-123',
        );
        expect(wrapper.get('[data-testid="error-page-home"]').attributes('href')).toBe('/');
        expect(wrapper.get('[data-testid="error-page-home"]').text()).toBe('Back to home');
    });

    it('renders within the admin layout when an admin is authenticated', () => {
        page.props.auth.user = { id: 1, name: 'Admin' };
        const wrapper = mount(ErrorPage, { props: ERROR_PROPS });

        expect(wrapper.find('[data-testid="error-page-layout-admin"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="error-page-code"]').text()).toBe('404');
    });
});
