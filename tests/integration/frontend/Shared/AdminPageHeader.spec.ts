import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AdminPageHeader from '@shared-admin/AdminPageHeader.vue';

const { postMock, pageProps } = vi.hoisted(() => ({
    postMock: vi.fn(),
    pageProps: {} as Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: postMock },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

describe('AdminPageHeader', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
        pageProps.auth = undefined;
    });

    it('renders the logo link with the theme-variant logo image', () => {
        const wrapper = mount(AdminPageHeader, {
            props: { logo_url: '/admin/dashboard' },
        });

        const logo = wrapper.get('[data-testid="admin-header-logo"]');

        expect(logo.attributes('href')).toBe('/admin/dashboard');
        const image = wrapper.get('[data-testid="admin-header-logo-image"]');
        // themeStore defaults to light without hydration.
        expect(image.attributes('src')).toBe('/images/logo-full-light.svg');
        expect(image.attributes('alt')).toBe('Admin Section');
    });

    it('renders the admin identity link with the avatar initial', () => {
        const wrapper = mount(AdminPageHeader, {
            props: { admin_name: 'John Doe', profile_url: '/admin/profile/john' },
        });

        const link = wrapper.get('a[href="/admin/profile/john"]');

        expect(link.attributes('aria-label')).toBe('John Doe');
        expect(link.text()).toContain('J');
        expect(link.text()).toContain('John Doe');
    });

    it('falls back to the shared auth user name for the avatar', () => {
        pageProps.auth = { user: { name: 'Jane Admin' } };

        const wrapper = mount(AdminPageHeader);

        const link = wrapper.get('a[href="/admin/profile"]');
        expect(link.attributes('aria-label')).toBe('Jane Admin');
        expect(link.text()).toContain('J');
        expect(link.text()).toContain('Jane Admin');
    });

    it('posts to the logout url when logout is clicked', async () => {
        const wrapper = mount(AdminPageHeader, {
            props: { logout_url: '/admin/logout' },
        });

        await wrapper.get('[data-testid="admin-header-logout"]').trigger('click');

        expect(postMock).toHaveBeenCalledExactlyOnceWith('/admin/logout');
    });

    it('posts to the default /admin/logout when no url is configured', async () => {
        const wrapper = mount(AdminPageHeader);

        await wrapper.get('[data-testid="admin-header-logout"]').trigger('click');

        expect(postMock).toHaveBeenCalledExactlyOnceWith('/admin/logout');
    });

    it('emits toggle-sidebar from the hamburger button', async () => {
        const wrapper = mount(AdminPageHeader);

        await wrapper.get('[data-testid="admin-header-hamburger"]').trigger('click');

        expect(wrapper.emitted('toggle-sidebar')).toHaveLength(1);
    });

    it('renders the controls slot', () => {
        const wrapper = mount(AdminPageHeader, {
            slots: { controls: '<button data-testid="header-control">Theme</button>' },
        });

        expect(wrapper.find('[data-testid="header-control"]').exists()).toBe(true);
    });
});
