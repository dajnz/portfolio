import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseButton from '@shared-public/PublicBaseButton.vue';

describe('PublicBaseButton', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a button with the slot content', () => {
        const wrapper = mount(PublicBaseButton, {
            slots: { default: 'Learn More' },
        });

        expect(wrapper.get('[data-testid="base-button"]').element.tagName).toBe('BUTTON');
        expect(wrapper.text()).toContain('Learn More');
    });

    it('renders an anchor with href when tag is "a"', () => {
        const wrapper = mount(PublicBaseButton, {
            props: { tag: 'a', href: '/blog' },
            slots: { default: 'Blog' },
        });

        const root = wrapper.get('[data-testid="base-button"]');
        expect(root.element.tagName).toBe('A');
        expect(root.attributes('href')).toBe('/blog');
    });

    it('emits click with the DOM event', async () => {
        const wrapper = mount(PublicBaseButton, {
            slots: { default: 'Click me' },
        });

        await wrapper.trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('applies the variant class', () => {
        const wrapper = mount(PublicBaseButton, {
            props: { variant: 'danger' },
        });

        expect(wrapper.get('[data-testid="base-button"]').classes()).toContain('btn--danger');
    });

    it('does not emit click when disabled', async () => {
        const wrapper = mount(PublicBaseButton, {
            props: { disabled: true },
            slots: { default: 'Disabled' },
        });

        const root = wrapper.get('[data-testid="base-button"]');
        expect(root.attributes('aria-disabled')).toBe('true');
        expect(root.attributes('disabled')).toBeDefined();

        await root.trigger('click');
        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('shows the loading state: aria-busy, disabled, hidden label', () => {
        const wrapper = mount(PublicBaseButton, {
            props: { loading: true },
            slots: { default: 'Saving' },
        });

        const root = wrapper.get('[data-testid="base-button"]');
        expect(root.attributes('aria-busy')).toBe('true');
        expect(root.attributes('disabled')).toBeDefined();
        expect(root.classes()).toContain('is-loading');
        expect(wrapper.get('.btn__label').classes()).toContain('btn__label--loading');
        expect(wrapper.text()).toContain('Saving');
    });

    it('renders an icon with icon_position and keeps the label', () => {
        const wrapper = mount(PublicBaseButton, {
            props: { icon: 'heroicons:arrow-right', icon_position: 'right' },
            slots: { default: 'Next' },
        });

        expect(wrapper.find('.btn__icon').exists()).toBe(true);
        expect(wrapper.get('.btn__icon-wrap').classes()).toContain('btn__icon-wrap--right');
    });

    it('icon-only variant renders only the icon', () => {
        const wrapper = mount(PublicBaseButton, {
            props: { variant: 'icon-only', icon: 'heroicons:x-mark' },
            slots: { default: 'Ignored text' },
        });

        expect(wrapper.get('[data-testid="base-button"]').classes()).toContain('btn--icon-only');
        expect(wrapper.find('.btn__icon-only').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Ignored text');
    });

    it('icon slot overrides the icon prop', () => {
        const wrapper = mount(PublicBaseButton, {
            props: { icon: 'heroicons:arrow-right' },
            slots: { icon: '<span class="custom-icon">X</span>' },
        });

        expect(wrapper.find('.custom-icon').exists()).toBe(true);
    });
});
