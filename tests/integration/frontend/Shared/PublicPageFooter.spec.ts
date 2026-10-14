import { usePage } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PublicPageFooter from '@shared-public/PublicPageFooter.vue';

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

describe('PublicPageFooter', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        setOwnerName('Alex Kovalev');
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the copyright with the start year and owner name', () => {
        pinTo(2026);

        const wrapper = mount(PublicPageFooter);

        expect(wrapper.get('.footer__copyright').text()).toBe(
            '© 2026 Alex Kovalev. All rights reserved.',
        );
    });

    it('renders a year range once the current year moves past 2026', () => {
        pinTo(2027);

        const wrapper = mount(PublicPageFooter);

        expect(wrapper.get('.footer__copyright').text()).toBe(
            '© 2026–2027 Alex Kovalev. All rights reserved.',
        );
    });

    it('glues the closing dot to the year when no owner name is shared', () => {
        pinTo(2026);
        setOwnerName('');

        const wrapper = mount(PublicPageFooter);

        expect(wrapper.get('.footer__copyright').text()).toBe('© 2026. All rights reserved.');
    });

    it('renders additional footer links from the default slot', () => {
        const wrapper = mount(PublicPageFooter, {
            slots: { default: '<a data-testid="footer-link" href="/rss">RSS</a>' },
        });

        expect(wrapper.find('[data-testid="footer-link"]').exists()).toBe(true);
    });

    it('wraps the slot content in an extras row and shrinks the footer paddings', () => {
        const wrapper = mount(PublicPageFooter, {
            slots: { default: '<a data-testid="footer-link" href="/rss">RSS</a>' },
        });

        expect(wrapper.find('.footer--has-extras').exists()).toBe(true);
        const extras = wrapper.find('.footer__extras');
        expect(extras.exists()).toBe(true);
        expect(extras.find('[data-testid="footer-link"]').exists()).toBe(true);
    });

    it('renders the extras above the copyright note', () => {
        const wrapper = mount(PublicPageFooter, {
            slots: { default: '<a data-testid="footer-link" href="/rss">RSS</a>' },
        });

        const inner = wrapper.get('.footer__inner').element.innerHTML;

        expect(inner.indexOf('footer__extras')).toBeLessThan(inner.indexOf('footer__copyright'));
    });

    it('renders no extras row and no modifier class when the slot is empty', () => {
        const wrapper = mount(PublicPageFooter);

        expect(wrapper.find('.footer__extras').exists()).toBe(false);
        expect(wrapper.find('.footer--has-extras').exists()).toBe(false);
    });

    it('ignores comment-only slot content (the layout forwards an empty footer slot)', () => {
        const wrapper = mount(PublicPageFooter, {
            slots: { default: '<!-- layout outlet placeholder -->' },
        });

        expect(wrapper.find('.footer__extras').exists()).toBe(false);
        expect(wrapper.find('.footer--has-extras').exists()).toBe(false);
    });
});
