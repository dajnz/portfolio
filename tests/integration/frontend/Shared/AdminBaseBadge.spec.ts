import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseBadge', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the slot content with the default variant class', () => {
        const wrapper = mount(AdminBaseBadge, {
            slots: { default: 'Laravel' },
        });

        expect(wrapper.text()).toBe('Laravel');
        expect(wrapper.get('[data-testid="base-badge"]').classes()).toContain('bg-slate-100');
    });

    it('maps variants to their classes', () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { variant: 'success' },
        });

        expect(wrapper.get('[data-testid="base-badge"]').classes()).toContain('bg-success-50');
    });

    it('applies the sm size class', () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { size: 'sm' },
        });

        expect(wrapper.get('[data-testid="base-badge"]').classes()).toContain('text-xs');
    });

    it('emits remove from the x button and not click', async () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { removable: true, clickable: true },
            slots: { default: 'Tag' },
        });

        const remove = wrapper.get('[data-testid="base-badge-remove"]');
        expect(remove.attributes('aria-label')).toBe('Remove');

        await remove.trigger('click');

        expect(wrapper.emitted('remove')).toHaveLength(1);
        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('emits click when clickable and the badge body is clicked', async () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { clickable: true },
            slots: { default: 'Tag' },
        });

        await wrapper.get('[data-testid="base-badge"]').trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('activates the clickable badge with the keyboard', async () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { clickable: true },
            slots: { default: 'Tag' },
        });

        const badge = wrapper.get('[data-testid="base-badge"]');
        await badge.trigger('keydown', { key: 'Enter' });
        await badge.trigger('keydown', { key: ' ' });

        expect(wrapper.emitted('click')).toHaveLength(2);
    });

    it('marks the clickable badge as a button', () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { clickable: true },
        });

        expect(wrapper.get('[data-testid="base-badge"]').attributes('role')).toBe('button');
        expect(wrapper.get('[data-testid="base-badge"]').attributes('tabindex')).toBe('0');
    });

    it('does not emit click when not clickable', async () => {
        const wrapper = mount(AdminBaseBadge, {
            slots: { default: 'Tag' },
        });

        await wrapper.get('[data-testid="base-badge"]').trigger('click');

        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('renders the icon', () => {
        const wrapper = mount(AdminBaseBadge, {
            props: { icon: 'heroicons:code-bracket' },
        });

        expect(wrapper.find('svg').exists()).toBe(true);
    });
});
