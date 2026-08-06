import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicEmptyState from '@shared-public/PublicEmptyState.vue';

describe('PublicEmptyState', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the message with a polite status role', () => {
        const wrapper = mount(PublicEmptyState, {
            props: { message: 'Nothing here.' },
        });

        expect(wrapper.get('[data-testid="empty-state"]').attributes('role')).toBe('status');
        expect(wrapper.get('.empty-state__message').text()).toBe('Nothing here.');
    });

    it('renders the mode class and a default icon per mode', () => {
        const wrapper = mount(PublicEmptyState, {
            props: { mode: 'no-results', message: 'No matches.' },
        });

        expect(wrapper.get('[data-testid="empty-state"]').classes()).toContain('empty-state--no-results');
        expect(wrapper.find('.empty-state__icon-image').exists()).toBe(true);
    });

    it('renders an action link when action_url is set', () => {
        const wrapper = mount(PublicEmptyState, {
            props: { message: 'No items.', action_label: 'Clear filters', action_url: '/projects' },
        });

        const action = wrapper.get('[data-testid="empty-state-action"]');

        expect(action.element.tagName).toBe('A');
        expect(action.attributes('href')).toBe('/projects');
    });

    it('emits action from the button when no action_url is set', async () => {
        const wrapper = mount(PublicEmptyState, {
            props: { message: 'Failed.', action_label: 'Retry' },
        });

        await wrapper.get('[data-testid="empty-state-action"]').trigger('click');

        expect(wrapper.emitted('action')).toHaveLength(1);
    });

    it('renders additional slot content', () => {
        const wrapper = mount(PublicEmptyState, {
            props: { message: 'No items.' },
            slots: { default: '<p data-testid="extra">Extra</p>' },
        });

        expect(wrapper.find('[data-testid="extra"]').exists()).toBe(true);
    });

    it('renders an inline svg for the icon', () => {
        const wrapper = mount(PublicEmptyState, {
            props: { message: 'No items.', icon: 'heroicons:archive-box' },
        });

        expect(wrapper.get('.empty-state__icon-image').element.tagName).toBe('svg');
    });
});
