import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseTextarea', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a textarea with the model value', () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: 'body' },
        });

        expect(wrapper.get('[data-testid="base-textarea"]').element.tagName).toBe('TEXTAREA');
        expect(
            (wrapper.get('[data-testid="base-textarea"]').element as HTMLTextAreaElement).value,
        ).toBe('body');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-textarea"]').setValue('text');

        expect(wrapper.emitted('update:modelValue')).toEqual([['text']]);
    });

    it('shows the character counter when character_counter and max are set', async () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: 'ab', max: 10, character_counter: true },
        });

        expect(wrapper.get('[data-testid="base-textarea"]').attributes('maxlength')).toBe('10');
        expect(wrapper.get('.text-right').text()).toBe('2/10');
    });

    it('hides the counter when max is undefined even with character_counter', () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: '', character_counter: true },
        });

        expect(wrapper.find('.text-right').exists()).toBe(false);
    });

    it('renders the rows attribute', () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: '', rows: 6 },
        });

        expect(wrapper.get('[data-testid="base-textarea"]').attributes('rows')).toBe('6');
    });

    it('applies the resize classes from resizable', () => {
        const fixed = mount(AdminBaseTextarea, {
            props: { modelValue: '', resizable: false },
        });
        const resizable = mount(AdminBaseTextarea, {
            props: { modelValue: '' },
        });

        expect(fixed.get('[data-testid="base-textarea"]').classes()).toContain('resize-none');
        expect(resizable.get('[data-testid="base-textarea"]').classes()).toContain('resize-y');
    });

    it('renders error with role alert and describedby wiring', () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: '', error: 'Too short.' },
        });

        const textarea = wrapper.get('[data-testid="base-textarea"]');
        const error = wrapper.get('[role="alert"]');

        expect(textarea.attributes('aria-invalid')).toBe('true');
        expect(textarea.attributes('aria-describedby')).toBe(error.attributes('id'));
    });

    it('forwards blur and focus events', async () => {
        const wrapper = mount(AdminBaseTextarea, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-textarea"]').trigger('blur');
        await wrapper.get('[data-testid="base-textarea"]').trigger('focus');

        expect(wrapper.emitted('blur')).toHaveLength(1);
        expect(wrapper.emitted('focus')).toHaveLength(1);
    });
});
