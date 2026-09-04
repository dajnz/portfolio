import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicPageFooter from '@shared-public/PublicPageFooter.vue';

describe('PublicPageFooter', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the copyright with year and name', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026', copyright_name: 'Alex Kovalev' },
        });

        expect(wrapper.get('.footer__copyright').text()).toBe(
            '© 2026 Alex Kovalev. All rights reserved.',
        );
    });

    it('falls back to the current year when not provided', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_name: 'Alex' },
        });

        expect(wrapper.get('.footer__copyright').text()).toContain(String(new Date().getFullYear()));
    });

    it('glues the closing dot to the year when no name is given', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026' },
        });

        expect(wrapper.get('.footer__copyright').text()).toBe('© 2026. All rights reserved.');
    });

    it('renders additional footer links from the default slot', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026', copyright_name: 'Alex' },
            slots: { default: '<a data-testid="footer-link" href="/rss">RSS</a>' },
        });

        expect(wrapper.find('[data-testid="footer-link"]').exists()).toBe(true);
    });

    it('wraps the slot content in an extras row and shrinks the footer paddings', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026', copyright_name: 'Alex' },
            slots: { default: '<a data-testid="footer-link" href="/rss">RSS</a>' },
        });

        expect(wrapper.find('.footer--has-extras').exists()).toBe(true);
        const extras = wrapper.find('.footer__extras');
        expect(extras.exists()).toBe(true);
        expect(extras.find('[data-testid="footer-link"]').exists()).toBe(true);
    });

    it('renders no extras row and no modifier class when the slot is empty', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026', copyright_name: 'Alex' },
        });

        expect(wrapper.find('.footer__extras').exists()).toBe(false);
        expect(wrapper.find('.footer--has-extras').exists()).toBe(false);
    });

    it('ignores comment-only slot content (the layout forwards an empty footer slot)', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026', copyright_name: 'Alex' },
            slots: { default: '<!-- layout outlet placeholder -->' },
        });

        expect(wrapper.find('.footer__extras').exists()).toBe(false);
        expect(wrapper.find('.footer--has-extras').exists()).toBe(false);
    });
});
