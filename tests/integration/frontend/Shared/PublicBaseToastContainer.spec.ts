import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseToastContainer from '@shared-public/PublicBaseToastContainer.vue';
import { useToast } from '@shared-cross/Composables/useToast';

describe('PublicBaseToastContainer', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders nothing when the queue is empty', () => {
        const wrapper = mount(PublicBaseToastContainer);

        expect(wrapper.findAll('.toast')).toHaveLength(0);
    });

    it('renders the queued toasts with their messages', () => {
        const { show } = useToast();
        show('Saved');
        show('Danger');

        const wrapper = mount(PublicBaseToastContainer);

        expect(wrapper.findAll('.toast')).toHaveLength(2);
        expect(wrapper.text()).toContain('Saved');
        expect(wrapper.text()).toContain('Danger');
    });

    it('uses role=status for non-error toasts and role=alert for errors', () => {
        const { show } = useToast();
        show('Saved', { variant: 'success' });
        show('Failed', { variant: 'error' });

        const wrapper = mount(PublicBaseToastContainer);

        expect(wrapper.get('[data-testid="toast-0"]').attributes('role')).toBe('status');
        expect(wrapper.get('[data-testid="toast-0"]').attributes('aria-live')).toBe('polite');
        expect(wrapper.get('[data-testid="toast-1"]').attributes('role')).toBe('alert');
    });

    it('applies the variant class and the position class', () => {
        const { show } = useToast();
        show('Info', { variant: 'info' });

        const wrapper = mount(PublicBaseToastContainer, {
            props: { position: 'bottom-left' },
        });

        expect(wrapper.get('[data-testid="toast-0"]').classes()).toContain('toast--info');
        expect(wrapper.get('.toast-container').classes()).toContain('toast-container--bottom-left');
    });

    it('dismisses via the close button and emits dismiss', async () => {
        const { show } = useToast();
        const id = show('Saved');

        const wrapper = mount(PublicBaseToastContainer);
        await wrapper.get('[data-testid="toast-0-dismiss"]').trigger('click');

        expect(wrapper.findAll('.toast')).toHaveLength(0);
        expect(wrapper.emitted('dismiss')).toEqual([[id]]);
    });

    it('hides the close button for non-dismissible toasts', () => {
        const { show } = useToast();
        show('Sticky', { dismissible: false });

        const wrapper = mount(PublicBaseToastContainer);

        expect(wrapper.find('[data-testid="toast-0-dismiss"]').exists()).toBe(false);
    });
});
