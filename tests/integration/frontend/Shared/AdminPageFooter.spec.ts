import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import AdminPageFooter from '@shared-admin/AdminPageFooter.vue';

function mountFooter(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
    return mount(AdminPageFooter, { props, slots });
}

describe('AdminPageFooter', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the copyright line with the given year and name', () => {
        const wrapper = mountFooter({ copyright_year: '2026', copyright_name: 'John Doe' });

        expect(wrapper.text()).toContain('© 2026 John Doe. All rights reserved.');
    });

    it('falls back to the current year', () => {
        const wrapper = mountFooter({ copyright_name: 'John Doe' });

        expect(wrapper.text()).toContain(
            `© ${new Date().getFullYear()} John Doe. All rights reserved.`,
        );
    });

    it('glues the closing dot to the year when no name is given', () => {
        const wrapper = mountFooter({ copyright_year: '2026' });

        expect(wrapper.text()).toContain('© 2026. All rights reserved.');
        expect(wrapper.text()).not.toContain('© 2026 .');
    });

    it('renders the default slot content', () => {
        const wrapper = mountFooter({}, { default: '<a data-testid="footer-link">GitHub</a>' });

        expect(wrapper.find('[data-testid="footer-link"]').exists()).toBe(true);
    });
});
