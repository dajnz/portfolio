import { usePage } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AdminPageFooter from '@shared-admin/AdminPageFooter.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(() => ({ props: {} })),
}));

function setOwnerName(name: string): void {
    vi.mocked(usePage).mockReturnValue({
        props: { copyright: { owner_name: name } },
    } as unknown as ReturnType<typeof usePage>);
}

function pinTo(year: number): void {
    vi.useFakeTimers();
    vi.setSystemTime(new Date(year, 5, 15));
}

describe('AdminPageFooter', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        setOwnerName('John Doe');
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the copyright line with the start year and owner name', () => {
        pinTo(2026);

        const wrapper = mount(AdminPageFooter);

        expect(wrapper.text()).toContain('© 2026 John Doe. All rights reserved.');
    });

    it('renders a year range once the current year moves past 2026', () => {
        pinTo(2027);

        const wrapper = mount(AdminPageFooter);

        expect(wrapper.text()).toContain('© 2026–2027 John Doe. All rights reserved.');
    });

    it('glues the closing dot to the year when no owner name is shared', () => {
        pinTo(2026);
        setOwnerName('');

        const wrapper = mount(AdminPageFooter);

        expect(wrapper.text()).toContain('© 2026. All rights reserved.');
        expect(wrapper.text()).not.toContain('© 2026 .');
    });

    it('renders the default slot content', () => {
        const wrapper = mount(AdminPageFooter, {
            slots: { default: '<a data-testid="footer-link">GitHub</a>' },
        });

        expect(wrapper.find('[data-testid="footer-link"]').exists()).toBe(true);
    });
});
