import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import PublicPageHeader from '@shared-public/PublicPageHeader.vue';

const NAV_ITEMS = [
    { label: 'About Me', url: '/#about' },
    { label: 'Projects', url: '/projects', condition: false },
    { label: 'Experience', url: '/experience' },
    { label: 'Blog', url: '/blog' },
];

describe('PublicPageHeader', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the logo image with the fallback url and alt text', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { logo_text: 'Alex Kovalev', nav_items: [] },
        });

        const logo = wrapper.get('[data-testid="public-header-logo"]');
        expect(logo.attributes('href')).toBe('/');
        const image = wrapper.get('[data-testid="public-header-logo-image"]');
        // themeStore defaults to light without hydration.
        expect(image.attributes('src')).toBe('/images/logo-full-light.svg');
        expect(image.attributes('alt')).toBe('Alex Kovalev');
    });

    it('renders only nav items whose condition is not false', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { nav_items: NAV_ITEMS },
        });

        const links = wrapper.findAll('a[data-testid^="public-header-nav-"]');

        expect(links).toHaveLength(3);
        expect(wrapper.text()).not.toContain('Projects');
    });

    it('marks the active route with aria-current', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { nav_items: NAV_ITEMS, active_route: '/blog' },
        });

        const blogLink = wrapper.get('[data-testid="public-header-nav-/blog"]');

        expect(blogLink.attributes('aria-current')).toBe('page');
        expect(blogLink.classes()).toContain('is-active');
    });

    it('labels the navigation landmark', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { nav_items: [] },
        });

        expect(wrapper.get('nav').attributes('aria-label')).toBe('Main navigation');
    });

    it('toggles the teleported mobile menu via the hamburger and closes it on a nav link click', async () => {
        const wrapper = mount(PublicPageHeader, {
            props: { nav_items: NAV_ITEMS },
        });

        const hamburger = wrapper.get('[data-testid="public-header-hamburger"]');
        expect(hamburger.attributes('aria-expanded')).toBe('false');
        expect(document.querySelector('[data-testid="public-header-mobile-menu"]')).toBeNull();

        await hamburger.trigger('click');
        expect(hamburger.attributes('aria-expanded')).toBe('true');

        // The panel is teleported to <body> so its backdrop-filter blurs
        // the page content (inside the header it would blur nothing).
        const panel = document.querySelector('[data-testid="public-header-mobile-menu"]');
        expect(panel).not.toBeNull();
        expect(panel?.parentElement?.tagName).toBe('BODY');
        const links = panel?.querySelectorAll('a');
        expect(links?.length).toBe(3);

        (panel?.querySelector('[data-testid="public-header-nav-/blog"]') as HTMLElement | null)?.click();
        await wrapper.vm.$nextTick();

        expect(hamburger.attributes('aria-expanded')).toBe('false');
        expect(document.querySelector('[data-testid="public-header-mobile-menu"]')).toBeNull();
    });

    it('renders the controls slot inside the header controls', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { nav_items: [] },
            slots: { controls: '<button data-testid="theme-switch">Theme</button>' },
        });

        expect(wrapper.find('[data-testid="theme-switch"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="theme-switch"]').element.parentElement?.classList.contains('header__controls')).toBe(true);
    });
});
