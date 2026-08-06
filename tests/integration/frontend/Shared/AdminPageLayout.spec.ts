import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: { post: vi.fn(), visit: vi.fn() },
    usePage: () => ({ props: undefined }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
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

function mountLayout(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
    return mount(AdminPageLayout, {
        props: { menu_groups: MENU_GROUPS, admin_name: 'John Doe', ...props },
        slots,
    });
}

describe('AdminPageLayout', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('renders the skip link pointing at the main content', () => {
        const wrapper = mountLayout();

        const skip = wrapper.get('[data-testid="admin-layout-skip-link"]');

        expect(skip.text()).toBe('Skip to main content');
        expect(skip.attributes('href')).toBe('#main-content');
    });

    it('composes header, sidebar, main and footer with their content', () => {
        const wrapper = mountLayout({}, { default: '<h1>Dashboard</h1>' });

        expect(wrapper.get('[data-testid="admin-header"]').text()).toContain('John Doe');
        expect(wrapper.find('[data-testid="admin-sidebar"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="admin-sidebar-group-content"]').exists()).toBe(true);

        const main = wrapper.get('#main-content');

        expect(main.element.tagName).toBe('MAIN');
        expect(main.text()).toContain('Dashboard');
        expect(wrapper.find('[data-testid="admin-footer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="toast-container"]').exists()).toBe(true);
    });

    it('forwards the controls slot to the header', () => {
        const wrapper = mountLayout({}, {
            controls: '<button data-testid="layout-controls">EN</button>',
        });

        const header = wrapper.get('[data-testid="admin-header"]');

        expect(header.find('[data-testid="layout-controls"]').exists()).toBe(true);
    });

    it('forwards the footer slot to the footer', () => {
        const wrapper = mountLayout({}, {
            footer: '<a data-testid="layout-footer-link">GitHub</a>',
        });

        expect(wrapper.find('[data-testid="layout-footer-link"]').exists()).toBe(true);
    });

    it('collapses the sidebar when the header hamburger is clicked', async () => {
        const wrapper = mountLayout();

        await wrapper.get('[data-testid="admin-header-hamburger"]').trigger('click');

        expect(wrapper.get('[data-testid="admin-sidebar"]').classes()).toContain('w-16');
        expect(JSON.parse(localStorage.getItem('admin_sidebar_collapsed') ?? 'null')).toBe(true);
    });

    it('persists expanded groups through the sidebar store', async () => {
        const wrapper = mountLayout();

        await wrapper.get('[data-testid="admin-sidebar-group-content"]').trigger('click');

        expect(JSON.parse(localStorage.getItem('admin_sidebar_collapsed_groups') ?? 'null')).toEqual(
            ['content'],
        );
    });
});
