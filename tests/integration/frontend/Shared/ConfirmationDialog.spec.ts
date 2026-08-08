import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

function mountDialog(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
    return mount(ConfirmationDialog, {
        props: {
            modelValue: true,
            title: 'Delete post',
            message: 'This cannot be undone.',
            ...props,
        },
        slots,
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('ConfirmationDialog', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the title and message while open', () => {
        const wrapper = mountDialog();

        expect(wrapper.get('[data-testid="confirmation-dialog-panel"]').attributes('role')).toBe(
            'dialog',
        );
        expect(wrapper.text()).toContain('Delete post');
        expect(wrapper.text()).toContain('This cannot be undone.');
    });

    it('omits the whole body block when no message is set (5.2 rework)', () => {
        const wrapper = mountDialog({ message: undefined });

        // No message paragraph AND no body wrapper — the title-only
        // dialog must not render a stray empty box.
        expect(wrapper.find('p').exists()).toBe(false);
        expect(
            wrapper.get('[data-testid="confirmation-dialog-panel"]').find('.px-6.py-4.text-sm').exists(),
        ).toBe(false);
    });

    it('uses the i18n defaults for the confirm and abort labels', () => {
        const wrapper = mountDialog();

        expect(wrapper.get('[data-testid="confirmation-dialog-confirm"]').text()).toContain(
            'Confirm',
        );
        expect(wrapper.get('[data-testid="confirmation-dialog-abort"]').text()).toContain('Cancel');
    });

    it('emits confirm with a null password when password confirmation is not required', async () => {
        const wrapper = mountDialog();

        await wrapper.get('[data-testid="confirmation-dialog-confirm"]').trigger('click');

        expect(wrapper.emitted('confirm')).toEqual([[{ password: null }]]);
    });

    it('emits abort and closes when the cancel button is clicked', async () => {
        const wrapper = mountDialog();

        await wrapper.get('[data-testid="confirmation-dialog-abort"]').trigger('click');

        expect(wrapper.emitted('abort')).toHaveLength(1);
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('shows the password field and disables confirm while it is empty', async () => {
        const wrapper = mountDialog({ require_password: true });

        expect(wrapper.text()).toContain('Enter your password to confirm.');
        const confirm = wrapper.get('[data-testid="confirmation-dialog-confirm"]');
        expect(confirm.attributes('disabled')).toBeDefined();

        await confirm.trigger('click');

        expect(wrapper.emitted('confirm')).toBeUndefined();
    });

    it('emits the typed password when password confirmation is required', async () => {
        const wrapper = mountDialog({ require_password: true });

        await wrapper.get('[data-testid="confirmation-dialog-password"]').setValue('secret');
        await wrapper.get('[data-testid="confirmation-dialog-confirm"]').trigger('click');

        expect(wrapper.emitted('confirm')).toEqual([[{ password: 'secret' }]]);
    });

    it('resets the password when the dialog reopens', async () => {
        const wrapper = mountDialog({ require_password: true });

        await wrapper.get('[data-testid="confirmation-dialog-password"]').setValue('secret');
        await wrapper.setProps({ modelValue: false });
        await wrapper.setProps({ modelValue: true });

        expect(
            (
                wrapper.get('[data-testid="confirmation-dialog-password"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('');
    });

    it('disables the abort and password inputs while loading', () => {
        const wrapper = mountDialog({ require_password: true, is_loading: true });

        expect(
            wrapper.get('[data-testid="confirmation-dialog-abort"]').attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper.get('[data-testid="confirmation-dialog-password"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('renders the body slot instead of the message', () => {
        const wrapper = mountDialog({}, { body: '<p data-testid="custom-body">Custom body</p>' });

        expect(wrapper.find('[data-testid="custom-body"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('This cannot be undone.');
    });

    it('closes via the modal close button', async () => {
        const wrapper = mountDialog();

        await wrapper.get('[data-testid="confirmation-dialog-close"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });
});
