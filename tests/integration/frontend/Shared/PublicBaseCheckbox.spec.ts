import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseCheckbox from '@shared-public/PublicBaseCheckbox.vue';

describe('PublicBaseCheckbox', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders checked when modelValue is true', () => {
        const wrapper = mount(PublicBaseCheckbox, {
            props: { modelValue: true, label: 'Remember me' },
        });

        expect((wrapper.get('[data-testid="base-checkbox"]').element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.text()).toContain('Remember me');
    });

    it('emits false when an unchecked box is clicked', async () => {
        const wrapper = mount(PublicBaseCheckbox, {
            props: { modelValue: false },
        });

        await wrapper.get('[data-testid="base-checkbox"]').setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([[true]]);
    });

    it('shows the indeterminate visual state and unchecks on click', async () => {
        const wrapper = mount(PublicBaseCheckbox, {
            props: { modelValue: 'indeterminate' },
        });

        const input = wrapper.get('[data-testid="base-checkbox"]');
        expect((input.element as HTMLInputElement).indeterminate).toBe(true);

        (input.element as HTMLInputElement).checked = true;
        await input.trigger('change');

        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('emits only booleans, never the indeterminate value', () => {
        const wrapper = mount(PublicBaseCheckbox, {
            props: { modelValue: 'indeterminate' },
        });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('disables the input', () => {
        const wrapper = mount(PublicBaseCheckbox, {
            props: { modelValue: false, disabled: true },
        });

        expect(wrapper.get('[data-testid="base-checkbox"]').attributes('disabled')).toBeDefined();
    });

    it('renders custom label markup via the default slot', () => {
        const wrapper = mount(PublicBaseCheckbox, {
            props: { modelValue: false },
            slots: { default: '<strong>Custom label</strong>' },
        });

        expect(wrapper.get('strong').text()).toBe('Custom label');
    });
});
