import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseTextarea from '@shared-public/PublicBaseTextarea.vue';

describe('PublicBaseTextarea', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a textarea with the model value', () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: 'body' },
        });

        expect(wrapper.get('[data-testid="base-textarea"]').element.tagName).toBe('TEXTAREA');
        expect((wrapper.get('[data-testid="base-textarea"]').element as HTMLTextAreaElement).value).toBe('body');
    });

    it('emits update:modelValue on input', async () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-textarea"]').setValue('text');

        expect(wrapper.emitted('update:modelValue')).toEqual([['text']]);
    });

    it('shows the character counter when character_counter and max are set', async () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: 'ab', max: 10, character_counter: true },
        });

        expect(wrapper.get('[data-testid="base-textarea"]').attributes('maxlength')).toBe('10');
        expect(wrapper.get('.form-group__counter').text()).toBe('2/10');
    });

    it('hides the counter when max is undefined even with character_counter', () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: '', character_counter: true },
        });

        expect(wrapper.find('.form-group__counter').exists()).toBe(false);
    });

    it('applies the fixed class when resizable is false', () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: '', resizable: false },
        });

        expect(wrapper.get('[data-testid="base-textarea"]').classes()).toContain(
            'form-group__textarea--fixed',
        );
    });

    it('renders error with role alert and describedby wiring', () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: '', error: 'Too short.' },
        });

        const textarea = wrapper.get('[data-testid="base-textarea"]');
        const error = wrapper.get('[role="alert"]');

        expect(textarea.attributes('aria-invalid')).toBe('true');
        expect(textarea.attributes('aria-describedby')).toBe(error.attributes('id'));
    });

    it('forwards blur and focus events', async () => {
        const wrapper = mount(PublicBaseTextarea, {
            props: { modelValue: '' },
        });

        await wrapper.get('[data-testid="base-textarea"]').trigger('blur');
        await wrapper.get('[data-testid="base-textarea"]').trigger('focus');

        expect(wrapper.emitted('blur')).toHaveLength(1);
        expect(wrapper.emitted('focus')).toHaveLength(1);
    });
});
