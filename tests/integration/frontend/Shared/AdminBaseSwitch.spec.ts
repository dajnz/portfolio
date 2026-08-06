import AdminBaseSwitch from '@shared-admin/AdminBaseSwitch.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseSwitch', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a checkbox with the switch role and the checked state', () => {
        const wrapper = mount(AdminBaseSwitch, {
            props: { modelValue: true, label: 'Maintenance mode' },
        });

        const input = wrapper.get('[data-testid="base-switch"]');

        expect(input.attributes('type')).toBe('checkbox');
        expect(input.attributes('role')).toBe('switch');
        expect(input.attributes('aria-checked')).toBe('true');
        expect((input.element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.text()).toContain('Maintenance mode');
    });

    it('reflects the unchecked state', () => {
        const wrapper = mount(AdminBaseSwitch, {
            props: { modelValue: false, label: 'Maintenance mode' },
        });

        expect(wrapper.get('[data-testid="base-switch"]').attributes('aria-checked')).toBe('false');
    });

    it('toggles on click and emits the new value', async () => {
        const wrapper = mount(AdminBaseSwitch, {
            props: { modelValue: false, label: 'On' },
        });

        await wrapper.get('[data-testid="base-switch"]').setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('emits false when the checked switch is toggled off', async () => {
        const wrapper = mount(AdminBaseSwitch, {
            props: { modelValue: true, label: 'On' },
        });

        await wrapper.get('[data-testid="base-switch"]').setValue(false);

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('toggles when activated with the Enter or Space key', async () => {
        const wrapper = mount(AdminBaseSwitch, {
            props: { modelValue: false, label: 'On' },
        });
        const input = wrapper.get('[data-testid="base-switch"]');

        for (const key of [' ', 'Enter']) {
            await input.trigger('keydown', { key });
            (input.element as HTMLInputElement).checked = true;
            await input.trigger('change');
        }

        expect(wrapper.emitted('update:modelValue')).toEqual([[true], [true]]);
    });

    it('disables the switch', () => {
        const wrapper = mount(AdminBaseSwitch, {
            props: { modelValue: false, label: 'On', disabled: true },
        });

        expect(wrapper.get('[data-testid="base-switch"]').attributes('disabled')).toBeDefined();
    });
});
