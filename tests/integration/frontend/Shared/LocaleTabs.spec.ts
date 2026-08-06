import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

const LOCALES = [
    { code: 'en', native_name: 'English' },
    { code: 'ru', native_name: 'Русский' },
];

describe('LocaleTabs', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a tab for each locale with its native name', () => {
        const wrapper = mount(LocaleTabs, { props: { modelValue: 'en', locales: LOCALES } });

        expect(wrapper.get('[data-testid="locale-tabs-tab-en"]').text()).toBe('English');
        expect(wrapper.get('[data-testid="locale-tabs-tab-ru"]').text()).toBe('Русский');
    });

    it('marks the active tab with aria-selected and the active styling', () => {
        const wrapper = mount(LocaleTabs, { props: { modelValue: 'en', locales: LOCALES } });

        const active = wrapper.get('[data-testid="locale-tabs-tab-en"]');
        const inactive = wrapper.get('[data-testid="locale-tabs-tab-ru"]');

        expect(active.attributes('aria-selected')).toBe('true');
        expect(active.classes()).toContain('bg-primary-600');
        expect(inactive.attributes('aria-selected')).toBe('false');
        expect(inactive.classes()).not.toContain('bg-primary-600');
    });

    it('emits update:modelValue when an inactive tab is clicked', async () => {
        const wrapper = mount(LocaleTabs, { props: { modelValue: 'en', locales: LOCALES } });

        await wrapper.get('[data-testid="locale-tabs-tab-ru"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['ru']]);
    });

    it('does not emit when the active tab is clicked', async () => {
        const wrapper = mount(LocaleTabs, { props: { modelValue: 'en', locales: LOCALES } });

        await wrapper.get('[data-testid="locale-tabs-tab-en"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('renders nothing when no locales are provided', () => {
        const wrapper = mount(LocaleTabs, { props: { modelValue: 'en' } });

        expect(wrapper.find('[role="tab"]').exists()).toBe(false);
    });
});
