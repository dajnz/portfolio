import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import InfiniteScrollLoader from '@shared-public/InfiniteScrollLoader.vue';

describe('InfiniteScrollLoader', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the load-more button when more items exist', () => {
        const wrapper = mount(InfiniteScrollLoader, {
            props: { has_more: true },
        });

        const button = wrapper.get('[data-testid="infinite-scroll-loader-button"]');

        expect(button).toBeDefined();
        expect(button.text()).toBe('Load More');
    });

    it('emits load-more on click', async () => {
        const wrapper = mount(InfiniteScrollLoader, {
            props: { has_more: true },
        });

        await wrapper.get('[data-testid="infinite-scroll-loader-button"]').trigger('click');

        expect(wrapper.emitted('load-more')).toHaveLength(1);
    });

    it('shows skeleton rows instead of the button while loading', () => {
        const wrapper = mount(InfiniteScrollLoader, {
            props: { has_more: true, is_loading: true },
        });

        expect(wrapper.find('[data-testid="infinite-scroll-loader-button"]').exists()).toBe(false);
        expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0);
    });

    it('renders nothing when exhausted', () => {
        const wrapper = mount(InfiniteScrollLoader, {
            props: { has_more: false },
        });

        expect(wrapper.text()).toBe('');
    });

    it('uses a custom label', () => {
        const wrapper = mount(InfiniteScrollLoader, {
            props: { has_more: true, label: 'More projects' },
        });

        expect(wrapper.get('[data-testid="infinite-scroll-loader-button"]').text()).toBe(
            'More projects',
        );
    });
});
