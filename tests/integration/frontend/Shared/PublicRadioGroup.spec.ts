import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicRadioGroup from '@shared-public/PublicRadioGroup.vue';

const OPTIONS = [
    { value: 'grid', label: 'Grid view' },
    { value: 'list', label: 'List view' },
];

describe('PublicRadioGroup', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders all options with the shared name and checks the selected one', () => {
        const wrapper = mount(PublicRadioGroup, {
            props: { modelValue: 'list', name: 'view', options: OPTIONS },
        });

        const radios = wrapper.findAll('input[type="radio"]');

        expect(radios).toHaveLength(2);
        expect(radios[0].attributes('name')).toBe('view');
        expect((radios[0].element as HTMLInputElement).checked).toBe(false);
        expect((radios[1].element as HTMLInputElement).checked).toBe(true);
    });

    it('emits the selected value on change', async () => {
        const wrapper = mount(PublicRadioGroup, {
            props: { modelValue: 'grid', options: OPTIONS },
        });

        await wrapper.get('input[value="list"]').setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([['list']]);
    });

    it('renders the label and error message', () => {
        const wrapper = mount(PublicRadioGroup, {
            props: { modelValue: '', label: 'View mode', error: 'Choose a view.', options: OPTIONS },
        });

        expect(wrapper.text()).toContain('View mode');
        expect(wrapper.get('[role="alert"]').text()).toBe('Choose a view.');
    });

    it('disables every radio when disabled', () => {
        const wrapper = mount(PublicRadioGroup, {
            props: { modelValue: '', disabled: true, options: OPTIONS },
        });

        wrapper.findAll('input[type="radio"]').forEach((radio) => {
            expect(radio.attributes('disabled')).toBeDefined();
        });
    });

    it('applies the horizontal direction class', () => {
        const wrapper = mount(PublicRadioGroup, {
            props: { modelValue: '', direction: 'horizontal', options: OPTIONS },
        });

        expect(wrapper.get('[data-testid="radio-group"]').classes()).toContain('form-group--horizontal');
    });

    it('exposes the checked state through the option slot', () => {
        const wrapper = mount(PublicRadioGroup, {
            props: { modelValue: 'grid', options: OPTIONS },
            slots: {
                option: `
                    <template #option="{ option, checked }">
                        <span data-testid="slot-checked">{{ checked }}:{{ option.value }}</span>
                    </template>
                `,
            },
        });

        expect(wrapper.get('[data-testid="slot-checked"]').text()).toBe('true:grid');
    });
});
