import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import AdminBaseToggle from '@shared-admin/AdminBaseToggle.vue';

describe('AdminBaseToggle', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a switch-role input with the checked state and the visible label', () => {
        const wrapper = mount(AdminBaseToggle, {
            props: { modelValue: true, label: 'Flag' },
        });

        const input = wrapper.get('[data-testid="base-toggle"]');

        expect(input.attributes('type')).toBe('checkbox');
        expect(input.attributes('role')).toBe('switch');
        expect(input.attributes('aria-checked')).toBe('true');
        expect(input.attributes('aria-label')).toBe('Flag');
        expect((input.element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.text()).toContain('Flag');
    });

    it('uses aria_label as the accessible name when rendered bare', () => {
        const wrapper = mount(AdminBaseToggle, {
            props: { modelValue: false, aria_label: 'Blog reactions' },
        });

        const input = wrapper.get('[data-testid="base-toggle"]');

        expect(input.attributes('aria-label')).toBe('Blog reactions');
        expect(wrapper.text()).not.toContain('Blog reactions');
    });

    it('reflects the unchecked state', () => {
        const wrapper = mount(AdminBaseToggle, {
            props: { modelValue: false, label: 'Flag' },
        });

        expect(wrapper.get('[data-testid="base-toggle"]').attributes('aria-checked')).toBe('false');
    });

    it('toggles on click and emits the new value', async () => {
        const wrapper = mount(AdminBaseToggle, {
            props: { modelValue: false, label: 'Flag' },
        });

        await wrapper.get('[data-testid="base-toggle"]').setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('disables the toggle', () => {
        const wrapper = mount(AdminBaseToggle, {
            props: { modelValue: false, label: 'Flag', disabled: true },
        });

        expect(wrapper.get('[data-testid="base-toggle"]').attributes('disabled')).toBeDefined();
    });
});
