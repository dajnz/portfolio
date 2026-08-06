import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PublicBaseDropdown from '@shared-public/PublicBaseDropdown.vue';

const OPTIONS = [
    { value: 'php', label: 'PHP' },
    { value: 'vue', label: 'Vue' },
    { value: 'laravel', label: 'Laravel', disabled: true },
];

describe('PublicBaseDropdown', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the placeholder when nothing is selected', () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', placeholder: 'Pick one', options: OPTIONS },
        });

        expect(wrapper.text()).toContain('Pick one');
    });

    it('shows the selected option label for single select', () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: 'vue', options: OPTIONS },
        });

        expect(wrapper.get('.dropdown__trigger-value').text()).toBe('Vue');
    });

    it('opens on trigger click and lists the options', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');

        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);
        expect(wrapper.findAll('[role="option"]')).toHaveLength(3);
    });

    it('emits the selected value and closes for single select', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        await wrapper.get('[data-testid="base-dropdown-option-vue"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['vue']]);
        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('toggles values without closing in multiple mode', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: [], multiple: true, options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        await wrapper.get('[data-testid="base-dropdown-option-php"]').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([[['php']]]);

        await wrapper.setProps({ modelValue: ['php'] });
        await wrapper.get('[data-testid="base-dropdown-option-vue"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[['php']], [['php', 'vue']]]);
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);
    });

    it('ignores disabled options on click', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        await wrapper.get('[data-testid="base-dropdown-option-laravel"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('navigates options with the keyboard and selects on Enter', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        await wrapper.get('[data-testid="base-dropdown"]').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.get('[data-testid="base-dropdown"]').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')).toEqual([['vue']]);
    });

    it('closes on Escape and returns focus to the trigger', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        await wrapper.get('[data-testid="base-dropdown"]').trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('emits search after the debounce in searchable mode', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', searchable: true, debounce_ms: 0, options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        const search = wrapper.get('[data-testid="base-dropdown-search"]');
        await search.setValue('ph');

        vi.advanceTimersByTime(300);

        expect(wrapper.emitted('search')).toEqual([['ph']]);
    });

    it('shows skeleton rows while loading', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', loading: true, options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');

        expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0);
    });

    it('shows the empty message when there are no options', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: [] },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');

        expect(wrapper.get('.dropdown__empty').text()).toBe('No options');
    });

    it('closes on an outside click', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: '', options: OPTIONS },
        });

        await wrapper.get('[data-testid="base-dropdown"]').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        const outside = document.createElement('div');
        document.body.appendChild(outside);
        outside.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        outside.remove();
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('passes selected options to the trigger slot', async () => {
        const wrapper = mount(PublicBaseDropdown, {
            props: { modelValue: 'php', options: OPTIONS },
            slots: {
                trigger: `
                    <template #trigger="{ selected_options, open }">
                        <span data-testid="trigger-slot">{{ selected_options[0].label }}:{{ open }}</span>
                    </template>
                `,
            },
        });

        expect(wrapper.get('[data-testid="trigger-slot"]').text()).toBe('PHP:false');
    });
});
