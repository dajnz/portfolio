import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseButton', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a button with the slot content', () => {
        const wrapper = mount(AdminBaseButton, {
            slots: { default: 'Save changes' },
        });

        expect(wrapper.get('[data-testid="base-button"]').element.tagName).toBe('BUTTON');
        expect(wrapper.text()).toContain('Save changes');
    });

    it('renders an anchor with href when tag is "a"', () => {
        const wrapper = mount(AdminBaseButton, {
            props: { tag: 'a', href: '/admin/blog' },
            slots: { default: 'Blog' },
        });

        const root = wrapper.get('[data-testid="base-button"]');
        expect(root.element.tagName).toBe('A');
        expect(root.attributes('href')).toBe('/admin/blog');
    });

    it('emits click with the DOM event', async () => {
        const wrapper = mount(AdminBaseButton, {
            slots: { default: 'Click me' },
        });

        await wrapper.trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });

    it('applies the variant class', () => {
        const wrapper = mount(AdminBaseButton, {
            props: { variant: 'danger' },
        });

        expect(wrapper.get('[data-testid="base-button"]').classes()).toContain('bg-danger-600');
    });

    it('applies the size classes', () => {
        const sm = mount(AdminBaseButton, { props: { size: 'sm' } });
        const md = mount(AdminBaseButton, { props: { size: 'md' } });
        const lg = mount(AdminBaseButton, { props: { size: 'lg' } });

        expect(sm.get('[data-testid="base-button"]').classes()).toContain('h-8');
        expect(md.get('[data-testid="base-button"]').classes()).toContain('h-10');
        expect(lg.get('[data-testid="base-button"]').classes()).toContain('h-12');
    });

    it('does not emit click when disabled', async () => {
        const wrapper = mount(AdminBaseButton, {
            props: { disabled: true },
            slots: { default: 'Disabled' },
        });

        const root = wrapper.get('[data-testid="base-button"]');
        expect(root.attributes('aria-disabled')).toBe('true');
        expect(root.attributes('disabled')).toBeDefined();

        await root.trigger('click');
        expect(wrapper.emitted('click')).toBeUndefined();
    });

    it('shows the loading state: aria-busy, disabled, pulse classes', () => {
        const wrapper = mount(AdminBaseButton, {
            props: { loading: true },
            slots: { default: 'Saving' },
        });

        const root = wrapper.get('[data-testid="base-button"]');
        expect(root.attributes('aria-busy')).toBe('true');
        expect(root.attributes('disabled')).toBeDefined();
        expect(root.classes()).toContain('animate-pulse');
        expect(root.classes()).toContain('cursor-wait');
        expect(wrapper.text()).toContain('Saving');
    });

    it('renders an icon with icon_position and keeps the label', () => {
        const wrapper = mount(AdminBaseButton, {
            props: { icon: 'heroicons:arrow-right', icon_position: 'right' },
            slots: { default: 'Next' },
        });

        expect(wrapper.find('svg').exists()).toBe(true);
        expect(wrapper.get('[data-testid="base-button"]').classes()).toContain('flex-row-reverse');
    });

    it('icon-only variant renders only the icon', () => {
        const wrapper = mount(AdminBaseButton, {
            props: { variant: 'icon-only', icon: 'heroicons:x-mark' },
            slots: { default: 'Ignored text' },
        });

        expect(wrapper.get('[data-testid="base-button"]').classes()).toContain('w-10');
        expect(wrapper.find('svg').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Ignored text');
    });

    it('icon slot overrides the icon prop', () => {
        const wrapper = mount(AdminBaseButton, {
            props: { icon: 'heroicons:arrow-right' },
            slots: { icon: '<span class="custom-icon">X</span>' },
        });

        expect(wrapper.find('.custom-icon').exists()).toBe(true);
        expect(wrapper.find('svg').exists()).toBe(false);
    });
});
