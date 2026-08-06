import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseInput', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders label and input with the model value', () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: 'hello', label: 'Email' },
        });

        const input = wrapper.get('[data-testid="base-input"]');
        expect((input.element as HTMLInputElement).value).toBe('hello');
        expect(wrapper.text()).toContain('Email');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-input"]').setValue('typed');

        expect(wrapper.emitted('update:modelValue')).toEqual([['typed']]);
    });

    it('forwards blur and focus events', async () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-input"]').trigger('blur');
        await wrapper.get('[data-testid="base-input"]').trigger('focus');

        expect(wrapper.emitted('blur')).toHaveLength(1);
        expect(wrapper.emitted('focus')).toHaveLength(1);
    });

    it('renders the error state with aria-invalid and aria-describedby', () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: 'bad', error: 'Invalid value.' },
        });

        const input = wrapper.get('[data-testid="base-input"]');
        const error = wrapper.get('[role="alert"]');

        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe(error.attributes('id'));
        expect(error.text()).toBe('Invalid value.');
    });

    it('shows help text instead of the error when there is no error', () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: '', help: 'We never share your email.' },
        });

        expect(wrapper.get('p').text()).toBe('We never share your email.');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('marks the label required and sets aria-required', () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: '', label: 'Name', required: true },
        });

        expect(wrapper.find('.text-danger-500').exists()).toBe(true);
        expect(wrapper.get('[data-testid="base-input"]').attributes('aria-required')).toBe('true');
    });

    it('disables the input', () => {
        const wrapper = mount(AdminBaseInput, {
            props: { modelValue: '', disabled: true },
        });

        const input = wrapper.get('[data-testid="base-input"]');
        expect(input.attributes('disabled')).toBeDefined();
        expect(input.classes()).toContain('cursor-not-allowed');
    });

    it('renders the extended type union', () => {
        const password = mount(AdminBaseInput, { props: { modelValue: '', type: 'password' } });
        const date = mount(AdminBaseInput, { props: { modelValue: '', type: 'date' } });

        expect(password.get('[data-testid="base-input"]').attributes('type')).toBe('password');
        expect(date.get('[data-testid="base-input"]').attributes('type')).toBe('date');
    });
});
