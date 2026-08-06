import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AdminPageHeader from '@shared-admin/AdminPageHeader.vue';

const { postMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: postMock },
    usePage: () => ({ props: undefined }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

describe('AdminPageHeader', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
    });

    it('renders the logo link with the admin section title', () => {
        const wrapper = mount(AdminPageHeader, {
            props: { logo_url: '/admin/dashboard' },
        });

        const logo = wrapper.get('[data-testid="admin-header-logo"]');

        expect(logo.text()).toBe('Admin Section');
        expect(logo.attributes('href')).toBe('/admin/dashboard');
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

    it('posts to the logout url when logout is clicked', async () => {
        const wrapper = mount(AdminPageHeader, {
            props: { logout_url: '/admin/logout' },
        });

        await wrapper.get('[data-testid="admin-header-logout"]').trigger('click');

        expect(postMock).toHaveBeenCalledExactlyOnceWith('/admin/logout');
    });

    it('does not post when no logout url is configured', async () => {
        const wrapper = mount(AdminPageHeader);

        await wrapper.get('[data-testid="admin-header-logout"]').trigger('click');

        expect(postMock).not.toHaveBeenCalled();
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
