import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicPageLayout from '@shared-public/PublicPageLayout.vue';

const NAV_ITEMS = [{ label: 'Blog', url: '/blog' }];

function mountLayout(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
    return mount(PublicPageLayout, {
        props: { nav_items: [], logo_text: 'Alex', ...props },
        slots,
    });
}

async function flush(): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve));
}

describe('PublicPageLayout', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('composes the structural components', () => {
        localStorage.setItem('cookies_accepted', '1');
        const wrapper = mountLayout({
            nav_items: NAV_ITEMS,
            copyright_year: '2026',
            copyright_name: 'Alex',
        }, {
            default: '<h1>Page</h1>',
        });

        expect(wrapper.find('[data-testid="public-layout-skip-link"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="public-header"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="public-footer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="go-top"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="toast-container"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cookies-notice"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Page');
    });

    it('renders the main landmark with the skip-link target id', () => {
        const wrapper = mountLayout();

        const main = wrapper.get('[data-testid="public-layout-main"]');

        expect(main.element.tagName).toBe('MAIN');
        expect(main.attributes('id')).toBe('main-content');
        expect(wrapper.get('[data-testid="public-layout-skip-link"]').attributes('href')).toBe(
            '#main-content',
        );
    });

    it('passes the centered prop to the main container', async () => {
        const wrapper = mountLayout();

        expect(wrapper.get('[data-testid="public-layout-main"]').classes()).toContain(
            'centered-container--xl',
        );

        await wrapper.setProps({ centered: false });

        expect(wrapper.get('[data-testid="public-layout-main"]').classes()).toContain(
            'centered-container--full',
        );
    });

    it('shows the cookies notice once the component mounts without consent', async () => {
        const wrapper = mountLayout();
        await flush();

        expect(wrapper.find('[data-testid="cookies-notice"]').exists()).toBe(true);
    });

    it('passes nav items and active route to the header', () => {
        const wrapper = mountLayout({
            nav_items: NAV_ITEMS,
            active_route: '/blog',
        });

        expect(wrapper.get('[data-testid="public-header-nav-/blog"]').attributes('aria-current')).toBe(
            'page',
        );
    });

    it('renders the controls slot into the header', () => {
        const wrapper = mountLayout({}, {
            controls: '<button data-testid="layout-controls">EN</button>',
        });

        expect(wrapper.find('[data-testid="layout-controls"]').exists()).toBe(true);
    });
});
