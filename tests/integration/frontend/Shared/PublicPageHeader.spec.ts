import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

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

    it('renders the logo with the fallback url and logo text', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { logo_text: 'Alex Kovalev', nav_items: [] },
        });

        const logo = wrapper.get('[data-testid="public-header-logo"]');
        expect(logo.text()).toBe('Alex Kovalev');
        expect(logo.attributes('href')).toBe('/');
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

    it('renders the controls slot inside the header controls', () => {
        const wrapper = mount(PublicPageHeader, {
            props: { nav_items: [] },
            slots: { controls: '<button data-testid="theme-switch">Theme</button>' },
        });

        expect(wrapper.find('[data-testid="theme-switch"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="theme-switch"]').element.parentElement?.classList.contains('header__controls')).toBe(true);
    });
});
