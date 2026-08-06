import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminEmptyState', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the message with a polite status role', () => {
        const wrapper = mount(AdminEmptyState, {
            props: { message: 'Nothing here.' },
        });

        expect(wrapper.get('[data-testid="empty-state"]').attributes('role')).toBe('status');
        expect(wrapper.get('[data-testid="empty-state"]').attributes('aria-live')).toBe('polite');
        expect(wrapper.get('p').text()).toBe('Nothing here.');
    });

    it('renders a default icon per mode', () => {
        const empty = mount(AdminEmptyState, { props: { mode: 'empty', message: 'Empty.' } });
        const noResults = mount(AdminEmptyState, {
            props: { mode: 'no-results', message: 'No matches.' },
        });
        const error = mount(AdminEmptyState, { props: { mode: 'error', message: 'Failed.' } });

        expect(empty.find('svg').exists()).toBe(true);
        expect(noResults.find('svg').exists()).toBe(true);
        expect(error.find('svg').exists()).toBe(true);
    });

    it('renders an action link when action_url is set', () => {
        const wrapper = mount(AdminEmptyState, {
            props: {
                message: 'No items.',
                action_label: 'Clear filters',
                action_url: '/admin/projects',
            },
        });

        const action = wrapper.get('[data-testid="empty-state-action"]');

        expect(action.element.tagName).toBe('A');
        expect(action.attributes('href')).toBe('/admin/projects');
    });

    it('emits action from the button when no action_url is set', async () => {
        const wrapper = mount(AdminEmptyState, {
            props: { message: 'Failed.', action_label: 'Retry' },
        });

        await wrapper.get('[data-testid="empty-state-action"]').trigger('click');

        expect(wrapper.emitted('action')).toHaveLength(1);
    });

    it('renders additional slot content', () => {
        const wrapper = mount(AdminEmptyState, {
            props: { message: 'No items.' },
            slots: { default: '<p data-testid="extra">Extra</p>' },
        });

        expect(wrapper.find('[data-testid="extra"]').exists()).toBe(true);
    });

    it('renders an inline svg for the custom icon', () => {
        const wrapper = mount(AdminEmptyState, {
            props: { message: 'No items.', icon: 'heroicons:archive-box' },
        });

        expect(wrapper.find('svg').exists()).toBe(true);
    });
});
