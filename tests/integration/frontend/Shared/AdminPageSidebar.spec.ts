import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AdminPageSidebar from '@shared-admin/AdminPageSidebar.vue';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href" :data-inertia-stub="href"><slot /></a>',
    },
    router: { visit: vi.fn(), post: postMock },
}));

const MENU_GROUPS = [
    {
        id: 'content',
        label: 'Content',
        icon: 'heroicons:document-text',
        items: [
            { label: 'Posts', route: '/admin/posts', icon: 'heroicons:document-text' },
            { label: 'Horizon', route: '/horizon', icon: 'heroicons:bolt' },
        ],
    },
    {
        id: 'system',
        label: 'System',
        icon: 'heroicons:cog-6-tooth',
        items: [{ label: 'Settings', route: '/admin/settings', icon: 'heroicons:cog-6-tooth' }],
    },
];

const DASHBOARD = { label: 'Dashboard', route: '/admin', icon: 'heroicons:squares-2x2' };

function mountSidebar(props: Record<string, unknown> = {}) {
    return mount(AdminPageSidebar, {
        props: { menu_groups: MENU_GROUPS, ...props },
    });
}

describe('AdminPageSidebar', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
    });

    it('labels the navigation landmark and carries the mobile-menu target id', () => {
        const wrapper = mountSidebar();

        const aside = wrapper.get('aside');
        expect(aside.attributes('aria-label')).toBe('Admin sidebar');
        expect(aside.attributes('id')).toBe('admin-mobile-menu');
    });

    it('hides the element below lg unless mobile_open is set', async () => {
        const wrapper = mountSidebar();

        expect(wrapper.get('[data-testid="admin-sidebar"]').classes()).toContain('hidden');

        await wrapper.setProps({ mobile_open: true });

        const aside = wrapper.get('[data-testid="admin-sidebar"]');
        expect(aside.classes()).toContain('flex');
        expect(aside.classes()).not.toContain('hidden');
        expect(aside.classes()).toContain('fixed');
    });

    it('renders the dashboard item when a dashboard prop is given', () => {
        const wrapper = mountSidebar({ dashboard: DASHBOARD });

        const dashboard = wrapper.get('[data-testid="admin-sidebar-dashboard"]');

        expect(dashboard.text()).toContain('Dashboard');
        expect(dashboard.attributes('href')).toBe('/admin');
    });

    it('highlights the dashboard item only on the exact /admin route (ADR-0024)', () => {
        const active = mountSidebar({ dashboard: DASHBOARD, active_route: '/admin' });
        expect(active.get('[data-testid="admin-sidebar-dashboard"]').classes()).toContain(
            'bg-sidebar-active',
        );

        const elsewhere = mountSidebar({ dashboard: DASHBOARD, active_route: '/admin/profile' });
        expect(elsewhere.get('[data-testid="admin-sidebar-dashboard"]').classes()).not.toContain(
            'bg-sidebar-active',
        );
    });

    it('renders group headers with their labels', () => {
        const wrapper = mountSidebar();

        expect(wrapper.get('[data-testid="admin-sidebar-group-content"]').text()).toContain(
            'Content',
        );
        expect(wrapper.get('[data-testid="admin-sidebar-group-system"]').text()).toContain(
            'System',
        );
    });

    it('renders nav items with icons inside an expanded group', () => {
        const wrapper = mountSidebar({ expanded_groups: ['content'] });

        const posts = wrapper.get('[data-testid="admin-sidebar-group-content-item-/admin/posts"]');

        expect(posts.text()).toContain('Posts');
        expect(posts.find('svg').exists()).toBe(true);
    });

    it('emits update:expanded_groups when a group header is toggled', async () => {
        const wrapper = mountSidebar();

        await wrapper.get('[data-testid="admin-sidebar-group-content"]').trigger('click');
        expect(wrapper.emitted('update:expanded_groups')).toEqual([[['content']]]);

        await wrapper.setProps({ expanded_groups: ['content'] });
        await wrapper.get('[data-testid="admin-sidebar-group-content"]').trigger('click');
        expect(wrapper.emitted('update:expanded_groups')).toEqual([[['content']], [[]]]);
    });

    it('rotates the chevron on an expanded group', () => {
        const wrapper = mountSidebar({ expanded_groups: ['content'] });

        const chevron = wrapper.get('[data-testid="admin-sidebar-group-content"]').find('svg');

        expect(chevron.classes()).toContain('rotate-180');
    });

    it('marks the active route with the active background class', () => {
        const wrapper = mountSidebar({ active_route: '/admin/posts', expanded_groups: ['content'] });

        const posts = wrapper.get('[data-testid="admin-sidebar-group-content-item-/admin/posts"]');

        expect(posts.classes()).toContain('bg-sidebar-active');

        const horizon = wrapper.get('[data-testid="admin-sidebar-group-content-item-/horizon"]');
        expect(horizon.classes()).not.toContain('bg-sidebar-active');
    });

    it('highlights the section on sub-routes (create/edit forms)', () => {
        const wrapper = mountSidebar({ active_route: '/admin/posts/5/edit' });

        const posts = wrapper.get('[data-testid="admin-sidebar-group-content-item-/admin/posts"]');
        expect(posts.classes()).toContain('bg-sidebar-active');
    });

    it('auto-expands the group containing the active route', () => {
        const wrapper = mountSidebar({ active_route: '/admin/posts' });

        expect(
            wrapper.find('[data-testid="admin-sidebar-group-content-item-/admin/posts"]').exists(),
        ).toBe(true);
        expect(wrapper.get('[data-testid="admin-sidebar-group-content"]').attributes(
            'aria-expanded',
        )).toBe('true');
    });

    it('renders the /horizon item as a plain anchor, not an Inertia Link', () => {
        const wrapper = mountSidebar({ expanded_groups: ['content'] });

        const horizon = wrapper.get('[data-testid="admin-sidebar-group-content-item-/horizon"]');

        expect(horizon.element.tagName).toBe('A');
        expect(horizon.attributes('href')).toBe('/horizon');
        expect(horizon.attributes('data-inertia-stub')).toBeUndefined();

        const posts = wrapper.get('[data-testid="admin-sidebar-group-content-item-/admin/posts"]');
        expect(posts.attributes('data-inertia-stub')).toBe('/admin/posts');
    });

    it('emits close-mobile when a nav item or the dashboard link is activated', async () => {
        const wrapper = mountSidebar({
            dashboard: DASHBOARD,
            expanded_groups: ['content'],
        });

        await wrapper.get('[data-testid="admin-sidebar-group-content-item-/admin/posts"]').trigger('click');
        await wrapper.get('[data-testid="admin-sidebar-dashboard"]').trigger('click');
        await wrapper.get('[data-testid="admin-sidebar-group-content-item-/horizon"]').trigger('click');

        expect(wrapper.emitted('close-mobile')).toHaveLength(3);
    });

    it('renders the mobile Logout row (lg:hidden) and posts to logout_url on click', async () => {
        const wrapper = mountSidebar({ logout_url: '/admin/sign-out' });

        const logout = wrapper.get('[data-testid="admin-sidebar-logout-mobile"]');

        expect(logout.element.parentElement?.className).toContain('lg:hidden');
        await logout.trigger('click');

        expect(postMock).toHaveBeenCalledExactlyOnceWith('/admin/sign-out');
        expect(wrapper.emitted('close-mobile')).toHaveLength(1);
    });
});
