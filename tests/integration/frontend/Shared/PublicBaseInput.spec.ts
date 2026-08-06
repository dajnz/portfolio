import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseInput from '@shared-public/PublicBaseInput.vue';

describe('PublicBaseInput', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders label and input with the model value', () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: 'hello', label: 'Email' },
        });

        const input = wrapper.get('[data-testid="base-input"]');
        expect(input.element.value).toBe('hello');
        expect(wrapper.text()).toContain('Email');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-input"]').setValue('typed');

        expect(wrapper.emitted('update:modelValue')).toEqual([['typed']]);
    });

    it('forwards blur and focus events', async () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-input"]').trigger('blur');
        await wrapper.get('[data-testid="base-input"]').trigger('focus');

        expect(wrapper.emitted('blur')).toHaveLength(1);
        expect(wrapper.emitted('focus')).toHaveLength(1);
    });

    it('renders the error state with aria-invalid and aria-describedby', () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: 'bad', error: 'Invalid value.' },
        });

        const input = wrapper.get('[data-testid="base-input"]');
        const error = wrapper.get('[role="alert"]');

        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe(error.attributes('id'));
        expect(error.text()).toBe('Invalid value.');
    });

    it('shows help text instead of the error when there is no error', () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: '', help: 'We never share your email.' },
        });

        expect(wrapper.get('.form-group__help').text()).toBe('We never share your email.');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('marks the label required and sets aria-required', () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: '', label: 'Name', required: true },
        });

        expect(wrapper.get('.form-group__label').classes()).toContain('form-group__label--required');
        expect(wrapper.get('[data-testid="base-input"]').attributes('aria-required')).toBe('true');
    });

    it('disables the input', () => {
        const wrapper = mount(PublicBaseInput, {
            props: { modelValue: '', disabled: true },
        });

        const input = wrapper.get('[data-testid="base-input"]');
        expect(input.attributes('disabled')).toBeDefined();
        expect(input.classes()).toContain('is-disabled');
    });
});
