import AdminCenteredContainer from '@shared-admin/AdminCenteredContainer.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminCenteredContainer', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a div with the default centered xl size classes', () => {
        const wrapper = mount(AdminCenteredContainer, {
            slots: { default: '<p>Content</p>' },
        });

        const root = wrapper.get('[data-testid="centered-container"]');
        expect(root.element.tagName).toBe('DIV');
        expect(root.classes()).toContain('mx-auto');
        expect(root.classes()).toContain('max-w-container-xl');
        expect(root.classes()).toContain('px-4');
        expect(root.classes()).toContain('lg:px-8');
    });

    it('applies the requested size class', () => {
        const wrapper = mount(AdminCenteredContainer, {
            props: { size: 'lg' },
        });

        expect(wrapper.get('[data-testid="centered-container"]').classes()).toContain(
            'max-w-container-lg',
        );
    });

    it('renders a custom tag', () => {
        const wrapper = mount(AdminCenteredContainer, {
            props: { tag: 'main' },
        });

        expect(wrapper.get('[data-testid="centered-container"]').element.tagName).toBe('MAIN');
    });

    it('falls back to full width when centered is false', () => {
        const wrapper = mount(AdminCenteredContainer, {
            props: { centered: false },
        });

        const root = wrapper.get('[data-testid="centered-container"]');
        expect(root.classes()).not.toContain('mx-auto');
        expect(root.classes()).not.toContain('max-w-container-xl');
    });

    it('removes horizontal padding when padding is false', () => {
        const wrapper = mount(AdminCenteredContainer, {
            props: { padding: false },
        });

        expect(wrapper.get('[data-testid="centered-container"]').classes()).toContain('px-0');
    });

    it('passes through extra attributes like id', () => {
        const wrapper = mount(AdminCenteredContainer, {
            attrs: { id: 'main-content' },
        });

        expect(wrapper.get('[data-testid="centered-container"]').attributes('id')).toBe(
            'main-content',
        );
    });
});
