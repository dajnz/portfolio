import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: { post: vi.fn(), visit: vi.fn(), on: vi.fn(() => vi.fn()) },
    usePage: () => ({ props: undefined }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
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

function mountLayoutAttached(props: Record<string, unknown> = {}) {
    return mount(AdminPageLayout, {
        props: { menu_groups: MENU_GROUPS, admin_name: 'John Doe', ...props },
        attachTo: document.body,
    });
}

function sidebarClasses(wrapper: ReturnType<typeof mountLayout>): string[] {
    return wrapper.get('[data-testid="admin-sidebar"]').classes();
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

    it('opens and closes the mobile menu from the header hamburger (not persisted)', async () => {
        const wrapper = mountLayout();

        const hamburger = wrapper.get('[data-testid="admin-header-hamburger"]');

        expect(sidebarClasses(wrapper)).toContain('hidden');

        await hamburger.trigger('click');

        expect(sidebarClasses(wrapper)).toContain('flex');
        expect(sidebarClasses(wrapper)).not.toContain('hidden');
        expect(wrapper.find('[data-testid="admin-sidebar-logout-mobile"]').exists()).toBe(true);

        await hamburger.trigger('click');

        expect(sidebarClasses(wrapper)).toContain('hidden');
        expect(localStorage.getItem('admin_sidebar_collapsed')).toBeNull();
    });

    it('closes the mobile menu on Escape and returns focus to the hamburger', async () => {
        const wrapper = mountLayoutAttached();
        const hamburger = wrapper.get('[data-testid="admin-header-hamburger"]');

        await hamburger.trigger('click');
        expect(sidebarClasses(wrapper)).toContain('flex');

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(sidebarClasses(wrapper)).toContain('hidden');
        expect(document.activeElement?.getAttribute('data-testid')).toBe(
            'admin-header-hamburger',
        );

        wrapper.unmount();
    });

    it('closes the mobile menu when a nav item is activated', async () => {
        const wrapper = mountLayout();

        await wrapper.get('[data-testid="admin-header-hamburger"]').trigger('click');
        await wrapper.get('[data-testid="admin-sidebar-group-content-item-/admin/posts"]').trigger('click');

        expect(sidebarClasses(wrapper)).toContain('hidden');
    });

    it('closes the mobile menu on a pointer press outside the panel (language selector)', async () => {
        const wrapper = mountLayoutAttached();

        await wrapper.get('[data-testid="admin-header-hamburger"]').trigger('click');
        expect(sidebarClasses(wrapper)).toContain('flex');

        const languageButton = wrapper.get('[data-testid="language-selector-button"]');
        languageButton.element.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(sidebarClasses(wrapper)).toContain('hidden');

        wrapper.unmount();
    });

    it('keeps the mobile menu open on a pointer press inside the panel', async () => {
        const wrapper = mountLayoutAttached();

        await wrapper.get('[data-testid="admin-header-hamburger"]').trigger('click');

        const groupHeader = wrapper.get('[data-testid="admin-sidebar-group-content"]');
        groupHeader.element.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(sidebarClasses(wrapper)).toContain('flex');

        wrapper.unmount();
    });

    it('keeps the mobile menu open on a pointer press on the hamburger', async () => {
        const wrapper = mountLayoutAttached();

        await wrapper.get('[data-testid="admin-header-hamburger"]').trigger('click');

        const hamburger = wrapper.get('[data-testid="admin-header-hamburger"]');
        hamburger.element.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(sidebarClasses(wrapper)).toContain('flex');

        wrapper.unmount();
    });

    it('persists expanded groups through the sidebar store', async () => {
        const wrapper = mountLayout();

        await wrapper.get('[data-testid="admin-sidebar-group-content"]').trigger('click');

        expect(JSON.parse(localStorage.getItem('admin_sidebar_collapsed_groups') ?? 'null')).toEqual(
            ['content'],
        );
    });
});
