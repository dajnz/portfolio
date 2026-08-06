import BulkActionBar from '@shared-admin/BulkActionBar.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

const ACTIONS = [
    { label: 'Delete', key: 'delete', variant: 'danger' },
    { label: 'Publish', key: 'publish' },
];

function mountBar(props: Record<string, unknown> = {}) {
    return mount(BulkActionBar, {
        props: { selected_count: 2, actions: ACTIONS, ...props },
    });
}

describe('BulkActionBar', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders nothing when nothing is selected', () => {
        const wrapper = mountBar({ selected_count: 0 });

        expect(wrapper.find('[data-testid="bulk-action-bar"]').exists()).toBe(false);
    });

    it('shows the pluralized selected count', () => {
        expect(mountBar({ selected_count: 1 }).text()).toContain('1 item selected');
        expect(mountBar({ selected_count: 3 }).text()).toContain('3 items selected');
    });

    it('renders the action buttons with their labels', () => {
        const wrapper = mountBar();

        expect(wrapper.get('[data-testid="bulk-action-bar-action-delete"]').text()).toContain(
            'Delete',
        );
        expect(wrapper.get('[data-testid="bulk-action-bar-action-publish"]').text()).toContain(
            'Publish',
        );
    });

    it('emits the action key when an action is clicked', async () => {
        const wrapper = mountBar();

        await wrapper.get('[data-testid="bulk-action-bar-action-delete"]').trigger('click');

        expect(wrapper.emitted('action')).toEqual([['delete']]);
    });

    it('emits clear when the clear button is clicked', async () => {
        const wrapper = mountBar();

        await wrapper.get('[data-testid="bulk-action-bar-clear"]').trigger('click');

        expect(wrapper.emitted('clear')).toHaveLength(1);
    });

    it('disables all controls while disabled', async () => {
        const wrapper = mountBar({ disabled: true });

        const clear = wrapper.get('[data-testid="bulk-action-bar-clear"]');
        const action = wrapper.get('[data-testid="bulk-action-bar-action-delete"]');

        expect(clear.attributes('disabled')).toBeDefined();
        expect(action.attributes('disabled')).toBeDefined();

        await action.trigger('click');
        await clear.trigger('click');

        expect(wrapper.emitted('action')).toBeUndefined();
        expect(wrapper.emitted('clear')).toBeUndefined();
    });
});
