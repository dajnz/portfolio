import AdminRadioGroup from '@shared-admin/AdminRadioGroup.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

const OPTIONS = [
    { value: 'grid', label: 'Grid view' },
    { value: 'list', label: 'List view' },
];

describe('AdminRadioGroup', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a fieldset with a legend and all options with the shared name', () => {
        const wrapper = mount(AdminRadioGroup, {
            props: { modelValue: 'list', name: 'view', label: 'View mode', options: OPTIONS },
        });

        const root = wrapper.get('[data-testid="radio-group"]');
        expect(root.element.tagName).toBe('FIELDSET');
        expect(wrapper.get('legend').text()).toContain('View mode');

        const radios = wrapper.findAll('input[type="radio"]');
        expect(radios).toHaveLength(2);
        expect(radios[0].attributes('name')).toBe('view');
        expect((radios[0].element as HTMLInputElement).checked).toBe(false);
        expect((radios[1].element as HTMLInputElement).checked).toBe(true);
    });

    it('emits the selected value on change', async () => {
        const wrapper = mount(AdminRadioGroup, {
            props: { modelValue: 'grid', options: OPTIONS },
        });

        await wrapper.get('input[value="list"]').setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([['list']]);
    });

    it('renders the label and error message', () => {
        const wrapper = mount(AdminRadioGroup, {
            props: {
                modelValue: '',
                label: 'View mode',
                error: 'Choose a view.',
                options: OPTIONS,
            },
        });

        expect(wrapper.text()).toContain('View mode');
        expect(wrapper.get('[role="alert"]').text()).toBe('Choose a view.');
    });

    it('disables every radio when disabled', () => {
        const wrapper = mount(AdminRadioGroup, {
            props: { modelValue: '', disabled: true, options: OPTIONS },
        });

        wrapper.findAll('input[type="radio"]').forEach((radio) => {
            expect(radio.attributes('disabled')).toBeDefined();
        });
    });

    it('applies the horizontal direction classes', () => {
        const wrapper = mount(AdminRadioGroup, {
            props: { modelValue: '', direction: 'horizontal', options: OPTIONS },
        });

        const root = wrapper.get('[data-testid="radio-group"]');
        expect(root.classes()).toContain('gap-6');
        expect(root.classes()).not.toContain('flex-col');
    });

    it('exposes the checked state through the option slot', () => {
        const wrapper = mount(AdminRadioGroup, {
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
