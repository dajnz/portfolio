import AdminBaseToast from '@shared-admin/AdminBaseToast.vue';
import { useToast } from '@shared-cross/Composables/useToast';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseToast', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders nothing when the queue is empty', () => {
        const wrapper = mount(AdminBaseToast);

        expect(wrapper.find('[data-testid="toast-0"]').exists()).toBe(false);
    });

    it('renders the queued toasts with their messages', () => {
        const { show } = useToast();
        show('Saved');
        show('Danger');

        const wrapper = mount(AdminBaseToast);

        expect(wrapper.find('[data-testid="toast-0"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="toast-1"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Saved');
        expect(wrapper.text()).toContain('Danger');
    });

    it('uses role=status for non-error toasts and role=alert for errors', () => {
        const { show } = useToast();
        show('Saved', { variant: 'success' });
        show('Failed', { variant: 'error' });

        const wrapper = mount(AdminBaseToast);

        expect(wrapper.get('[data-testid="toast-0"]').attributes('role')).toBe('status');
        expect(wrapper.get('[data-testid="toast-0"]').attributes('aria-live')).toBe('polite');
        expect(wrapper.get('[data-testid="toast-1"]').attributes('role')).toBe('alert');
        expect(wrapper.get('[data-testid="toast-1"]').attributes('aria-live')).toBeUndefined();
    });

    it('applies the variant class to the toast', () => {
        const { show } = useToast();
        show('Info', { variant: 'info' });

        const wrapper = mount(AdminBaseToast);

        expect(wrapper.get('[data-testid="toast-0"] span').classes()).toContain('bg-primary-600');
    });

    it('applies the position classes', () => {
        const wrapper = mount(AdminBaseToast, {
            props: { position: 'bottom-left' },
        });

        const container = wrapper.get('[data-testid="toast-container"]');
        expect(container.classes()).toContain('bottom-4');
        expect(container.classes()).toContain('left-4');
    });

    it('dismisses via the close button and emits dismiss', async () => {
        const { show } = useToast();
        const id = show('Saved');

        const wrapper = mount(AdminBaseToast);
        await wrapper.get('[data-testid="toast-0-dismiss"]').trigger('click');

        expect(wrapper.find('[data-testid="toast-0"]').exists()).toBe(false);
        expect(wrapper.emitted('dismiss')).toEqual([[id]]);
    });

    it('labels the close button with the real Close translation', () => {
        const { show } = useToast();
        show('Saved');

        const wrapper = mount(AdminBaseToast);

        expect(wrapper.get('[data-testid="toast-0-dismiss"]').attributes('aria-label')).toBe(
            'Close',
        );
    });

    it('hides the close button for non-dismissible toasts', () => {
        const { show } = useToast();
        show('Sticky', { dismissible: false });

        const wrapper = mount(AdminBaseToast);

        expect(wrapper.find('[data-testid="toast-0-dismiss"]').exists()).toBe(false);
    });
});
