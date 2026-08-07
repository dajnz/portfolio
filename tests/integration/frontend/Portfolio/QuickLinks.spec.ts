import QuickLinks, { type QuickLinkData } from '@portfolio-admin/QuickLinks.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

function links(): Array<QuickLinkData> {
    return [
        { label: 'New Project', url: '/admin/projects/create', icon: 'heroicons:plus', variant: 'primary' },
        { label: 'New Article', url: '/admin/blog/create', icon: 'heroicons:plus', variant: 'secondary' },
        { label: 'Unread Feedback', url: '/admin/feedback', icon: 'heroicons:chat-bubble-left-right', variant: 'secondary' },
    ];
}

describe('QuickLinks', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders each link as an anchor with the target href', () => {
        const wrapper = mount(QuickLinks, { props: { links: links() } });

        const first = wrapper.get('[data-testid="quick-links-link-0"]');
        expect(first.element.tagName).toBe('A');
        expect(first.attributes('href')).toBe('/admin/projects/create');
        expect(first.text()).toBe('New Project');

        expect(wrapper.get('[data-testid="quick-links-link-2"]').attributes('href')).toBe('/admin/feedback');
    });

    it('applies the variant class to each link', () => {
        const wrapper = mount(QuickLinks, { props: { links: links() } });

        expect(wrapper.get('[data-testid="quick-links-link-0"]').classes()).toContain('bg-primary-600');
        expect(wrapper.get('[data-testid="quick-links-link-1"]').classes()).toContain('bg-white');
    });
});
