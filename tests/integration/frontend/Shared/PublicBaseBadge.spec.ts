import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseBadge from '@shared-public/PublicBaseBadge.vue';

describe('PublicBaseBadge', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the slot content with the default variant class', () => {
        const wrapper = mount(PublicBaseBadge, {
            slots: { default: 'Laravel' },
        });

        expect(wrapper.text()).toBe('Laravel');
        expect(wrapper.get('[data-testid="base-badge"]').classes()).toContain('badge--default');
    });

    it('maps variants to their BEM classes', () => {
        const wrapper = mount(PublicBaseBadge, {
            props: { variant: 'success' },
        });

        expect(wrapper.get('[data-testid="base-badge"]').classes()).toContain('badge--success');
    });

    it('applies the sm size class', () => {
        const wrapper = mount(PublicBaseBadge, {
            props: { size: 'sm' },
        });

        expect(wrapper.get('[data-testid="base-badge"]').classes()).toContain('badge--sm');
    });

    it('emits remove from the x button and not click', async () => {
        const wrapper = mount(PublicBaseBadge, {
            props: { removable: true, clickable: true },
            slots: { default: 'Tag' },
        });

        await wrapper.get('[data-testid="base-badge-remove"]').trigger('click');

        expect(wrapper.emitted('remove')).toHaveLength(1);
        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('emits click when clickable and the badge body is clicked', async () => {
        const wrapper = mount(PublicBaseBadge, {
            props: { clickable: true },
            slots: { default: 'Tag' },
        });

        await wrapper.get('[data-testid="base-badge"]').trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('activates the clickable badge with the keyboard', async () => {
        const wrapper = mount(PublicBaseBadge, {
            props: { clickable: true },
            slots: { default: 'Tag' },
        });

        await wrapper.get('[data-testid="base-badge"]').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('does not emit click when not clickable', async () => {
        const wrapper = mount(PublicBaseBadge, {
            slots: { default: 'Tag' },
        });

        await wrapper.get('[data-testid="base-badge"]').trigger('click');

        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('renders the icon', () => {
        const wrapper = mount(PublicBaseBadge, {
            props: { icon: 'heroicons:code-bracket' },
        });

        expect(wrapper.find('.badge__icon').exists()).toBe(true);
    });
});
