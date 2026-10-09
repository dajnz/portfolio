import AdminBaseModal from '@shared-admin/AdminBaseModal.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

function mountModal(props: Record<string, unknown> = {}, slots: Record<string, unknown> = {}) {
    return mount(AdminBaseModal, {
        props: { modelValue: false, title: 'Dialog', ...props },
        slots: {
            default:
                '<button type="button" data-testid="inner-btn">Inner</button><input data-testid="inner-input" />',
            ...slots,
        },
        attachTo: document.body,
        // The real Teleport moves content to <body> (outside the wrapper);
        // stubbing it keeps the content inside the wrapper for assertions.
        global: { stubs: { Teleport: true } },
    });
}

describe('AdminBaseModal', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders nothing while hidden', () => {
        const wrapper = mountModal();

        expect(wrapper.find('[data-testid="base-modal"]').exists()).toBe(false);
    });

    it('renders the dialog with aria attributes when open', async () => {
        const wrapper = mountModal({ modelValue: true, title: 'Confirm' });

        const panel = wrapper.get('[data-testid="base-modal-panel"]');

        expect(panel.attributes('role')).toBe('dialog');
        expect(panel.attributes('aria-modal')).toBe('true');
        expect(panel.attributes('aria-labelledby')).toBe(
            wrapper.get('#base-modal-title').attributes('id'),
        );
        expect(wrapper.text()).toContain('Confirm');
    });

    it('omits the body block when the default slot is empty (5.2 rework)', async () => {
        const wrapper = mount(AdminBaseModal, {
            props: { modelValue: true, title: 'Confirm' },
            attachTo: document.body,
            global: { stubs: { Teleport: true } },
        });

        const panel = wrapper.get('[data-testid="base-modal-panel"]');
        expect(panel.attributes('role')).toBe('dialog');
        // The empty default slot must not render the body wrapper.
        expect(panel.find('.px-6.py-4.text-sm').exists()).toBe(false);
    });

    it('omits the body block when the default slot exists but renders nothing (5.2 rework)', async () => {
        // A consumer (ConfirmationDialog) can pass a `v-if`-guarded body —
        // the slot EXISTS but produces no content when the condition is
        // false. The wrapper must not render an empty box.
        const wrapper = mount(AdminBaseModal, {
            props: { modelValue: true, title: 'Confirm' },
            slots: { default: '<div v-if="false">guarded away</div>' },
            attachTo: document.body,
            global: { stubs: { Teleport: true } },
        });

        const panel = wrapper.get('[data-testid="base-modal-panel"]');
        expect(panel.find('.px-6.py-4.text-sm').exists()).toBe(false);

        await wrapper.setProps({ modelValue: false });
        const withContent = mount(AdminBaseModal, {
            props: { modelValue: true, title: 'Confirm' },
            slots: { default: '<p class="text-sm">shown</p>' },
            attachTo: document.body,
            global: { stubs: { Teleport: true } },
        });
        expect(
            withContent.get('[data-testid="base-modal-panel"]').find('.px-6.py-4.text-sm').exists(),
        ).toBe(true);
    });

    it('applies the size class to the panel', async () => {
        const wrapper = mountModal({ modelValue: true, size: 'lg' });

        expect(wrapper.get('[data-testid="base-modal-panel"]').classes()).toContain('max-w-lg');
    });

    it('auto-focuses the first focusable element on open', async () => {
        const wrapper = mountModal({ modelValue: false });
        const trigger = document.createElement('button');
        document.body.appendChild(trigger);
        trigger.focus();

        await wrapper.setProps({ modelValue: true });
        await new Promise((resolve) => setTimeout(resolve));

        // The close button is the first focusable element in the header.
        expect(document.activeElement?.getAttribute('data-testid')).toBe('base-modal-close');
        trigger.remove();
    });

    it('returns focus to the trigger element on close', async () => {
        const wrapper = mountModal({ modelValue: false });
        const trigger = document.createElement('button');
        trigger.setAttribute('data-testid', 'modal-trigger');
        document.body.appendChild(trigger);
        trigger.focus();

        await wrapper.setProps({ modelValue: true });
        await wrapper.setProps({ modelValue: false });
        await new Promise((resolve) => setTimeout(resolve));

        expect(document.activeElement).toBe(trigger);
        trigger.remove();
    });

    it('emits update:modelValue false from the close button', async () => {
        const wrapper = mountModal({ modelValue: true });

        await wrapper.get('[data-testid="base-modal-close"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('closes on scrim click but not on panel click', async () => {
        const wrapper = mountModal({ modelValue: true });

        await wrapper.get('[data-testid="base-modal-scrim"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);

        await wrapper.setProps({ modelValue: true });
        await wrapper.get('[data-testid="base-modal-panel"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toHaveLength(1);
    });

    it('closes on Escape', async () => {
        const wrapper = mountModal({ modelValue: true });

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('does not close on Escape when close_on_escape is false', async () => {
        const wrapper = mountModal({ modelValue: true, close_on_escape: false });

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('traps Tab focus: wraps from the last focusable back to the first', async () => {
        const wrapper = mountModal({ modelValue: true });

        const closeButton = wrapper.get('[data-testid="base-modal-close"]').element as HTMLElement;
        const innerInput = wrapper.get('[data-testid="inner-input"]').element as HTMLElement;

        innerInput.focus();
        await wrapper.get('[data-testid="base-modal-panel"]').trigger('keydown', { key: 'Tab' });

        expect(document.activeElement).toBe(closeButton);

        closeButton.focus();
        await wrapper.get('[data-testid="base-modal-panel"]').trigger('keydown', {
            key: 'Tab',
            shiftKey: true,
        });

        expect(document.activeElement).toBe(innerInput);
    });

    it('emits closed after the leave transition finishes', async () => {
        // The Teleport stub re-creates the slot vnodes on every update, which
        // remounts the Transition component and skips its leave hooks — use
        // the real Teleport (content moves to document.body) for this test.
        const wrapper = mount(AdminBaseModal, {
            props: { modelValue: true, title: 'Dialog' },
            attachTo: document.body,
            global: { stubs: { Teleport: false, Transition: false } },
        });

        await wrapper.setProps({ modelValue: false });
        // Vue's leave transition resolves after two requestAnimationFrame
        // frames — give happy-dom's rAF scheduler time to run them.
        await new Promise((resolve) => setTimeout(resolve, 100));

        expect(wrapper.emitted('closed')).toHaveLength(1);
    });

    it('renders the footer and header slots', async () => {
        const wrapper = mountModal(
            { modelValue: true },
            {
                header: '<span data-testid="custom-header">Custom</span>',
                footer: '<button data-testid="custom-footer">Ok</button>',
            },
        );

        expect(wrapper.find('[data-testid="custom-header"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="custom-footer"]').exists()).toBe(true);
    });
});
