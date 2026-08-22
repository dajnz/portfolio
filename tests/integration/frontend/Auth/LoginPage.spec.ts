import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { postMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: postMock },
    usePage: () => ({ props: undefined }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import LoginPage from '@auth/Admin/Pages/LoginPage.vue';

function mountPage(props: Record<string, unknown> = {}) {
    return mount(LoginPage, {
        props: { github_login_available: false, loginPage: true, ...props },
    });
}

describe('LoginPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
        window.localStorage.clear();
    });

    it('renders the login form fields and submit button', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="login-page-login-input"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="login-page-password-input"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="login-page-remember-checkbox"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="login-page-submit-btn"]').text()).toBe('Login');
    });

    it('posts the credentials to /admin/login on submit', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="login-page-login-input"]').setValue('admin');
        await wrapper.get('[data-testid="login-page-password-input"]').setValue('secret123');
        await wrapper.get('[data-testid="login-page-remember-checkbox"]').setValue(true);
        await wrapper.get('[data-testid="login-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/login',
            { login: 'admin', password: 'secret123', remember: true },
            expect.any(Object),
        );
    });

    it('shows the server error message when the credentials are invalid', async () => {
        postMock.mockImplementationOnce(
            (_url: string, _data: unknown, options: { onError?: (errors: Record<string, string>) => void }) => {
                options.onError?.({ login: 'These credentials do not match our records.' });
            },
        );

        const wrapper = mountPage();

        await wrapper.get('[data-testid="login-page-form"]').trigger('submit');

        expect(wrapper.get('[data-testid="login-page-error"]').text()).toBe(
            'These credentials do not match our records.',
        );
    });

    it('does not render the GitHub button when no linked admin makes it available', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="login-page-github"]').exists()).toBe(false);
    });

    it('renders the GitHub button when the flag is on and an admin is linked', () => {
        const wrapper = mountPage({ github_login_available: true });

        expect(wrapper.find('[data-testid="login-page-github"]').exists()).toBe(true);
    });

    it('redirects to the GitHub OAuth route when the GitHub button is clicked', async () => {
        const locationMock = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { href: '' },
            writable: true,
        });

        const wrapper = mountPage({ github_login_available: true });
        await wrapper.get('[data-testid="login-page-github"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(window.location.href).toBe('/admin/login/github');
        locationMock();
    });

    it('keeps the GitHub button in the loading state while the OAuth redirect is in flight', async () => {
        Object.defineProperty(window, 'location', {
            value: { href: '' },
            writable: true,
        });

        const wrapper = mountPage({ github_login_available: true });
        const button = wrapper.get('[data-testid="login-page-github"]');

        expect(button.attributes('disabled')).toBeUndefined();

        await button.trigger('click');

        expect(button.attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid="login-page-submit-btn"]').attributes('disabled')).toBeDefined();
    });

    it('renders the theme and language controls in the footer', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="login-page-theme"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="login-page-language"]').exists()).toBe(true);
    });
});
