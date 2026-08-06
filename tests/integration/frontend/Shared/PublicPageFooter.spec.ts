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

    it('renders additional footer links from the default slot', () => {
        const wrapper = mount(PublicPageFooter, {
            props: { copyright_year: '2026', copyright_name: 'Alex' },
            slots: { default: '<a data-testid="footer-link" href="/rss">RSS</a>' },
        });

        expect(wrapper.find('[data-testid="footer-link"]').exists()).toBe(true);
    });
});
