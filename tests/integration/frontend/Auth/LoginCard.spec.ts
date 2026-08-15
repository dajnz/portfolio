import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import LoginCard from '@auth-admin/LoginCard.vue';

describe('LoginCard', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the title, the theme-variant logo and the form slot', () => {
        const wrapper = mount(LoginCard, {
            props: { title: 'Two-Factor Authentication' },
            slots: { default: '<input data-testid="inner-input" />' },
        });

        expect(wrapper.get('[data-testid="login-card-title"]').text()).toBe(
            'Two-Factor Authentication',
        );
        const logo = wrapper.get('[data-testid="login-card-logo"]');
        expect(logo.element.tagName).toBe('IMG');
        // themeStore defaults to light without hydration.
        expect(logo.attributes('src')).toBe('/images/logo-iconic-light.svg');
        expect(wrapper.find('[data-testid="inner-input"]').exists()).toBe(true);
    });

    it('renders a logo image when logo_url is provided', () => {
        const wrapper = mount(LoginCard, {
            props: { logo_url: '/logo.svg' },
        });

        expect(wrapper.get('[data-testid="login-card-logo"]').attributes('src')).toBe('/logo.svg');
    });

    it('switches the logo variant with the theme store', async () => {
        const { useThemeStore } = await import('@shared-cross/Stores/themeStore');
        const themeStore = useThemeStore();

        const wrapper = mount(LoginCard, { props: { title: 'Login' } });
        expect(wrapper.get('[data-testid="login-card-logo"]').attributes('src')).toBe(
            '/images/logo-iconic-light.svg',
        );

        themeStore.setTheme('dark');
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-testid="login-card-logo"]').attributes('src')).toBe(
            '/images/logo-iconic-dark.svg',
        );
    });

    it('renders the footer slot content', () => {
        const wrapper = mount(LoginCard, {
            slots: { footer: '<span data-testid="footer-controls">EN</span>' },
        });

        expect(wrapper.find('[data-testid="footer-controls"]').exists()).toBe(true);
    });

    it('hides the GitHub button when GitHub OAuth is disabled', () => {
        const wrapper = mount(LoginCard, {
            props: { is_github_enabled: false },
        });

        expect(wrapper.find('[data-testid="login-card-github"]').exists()).toBe(false);
    });

    it('emits github-login when the GitHub button is clicked', async () => {
        const wrapper = mount(LoginCard, {
            props: { is_github_enabled: true },
        });

        await wrapper.get('[data-testid="login-card-github"]').trigger('click');

        expect(wrapper.emitted('github-login')).toHaveLength(1);
    });
});
