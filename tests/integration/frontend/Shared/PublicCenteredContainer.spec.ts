import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicCenteredContainer from '@shared-public/PublicCenteredContainer.vue';

describe('PublicCenteredContainer', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a div with the default xl size class', () => {
        const wrapper = mount(PublicCenteredContainer, {
            slots: { default: '<p>Content</p>' },
        });

        const root = wrapper.get('[data-testid="centered-container"]');
        expect(root.element.tagName).toBe('DIV');
        expect(root.classes()).toContain('centered-container');
        expect(root.classes()).toContain('centered-container--xl');
    });

    it('applies the requested size class', () => {
        const wrapper = mount(PublicCenteredContainer, {
            props: { size: 'lg' },
        });

        expect(wrapper.get('[data-testid="centered-container"]').classes()).toContain(
            'centered-container--lg',
        );
    });

    it('renders a custom tag', () => {
        const wrapper = mount(PublicCenteredContainer, {
            props: { tag: 'main' },
        });

        expect(wrapper.get('[data-testid="centered-container"]').element.tagName).toBe('MAIN');
    });

    it('falls back to full width when centered is false', () => {
        const wrapper = mount(PublicCenteredContainer, {
            props: { centered: false },
        });

        const root = wrapper.get('[data-testid="centered-container"]');
        expect(root.classes()).toContain('centered-container--full');
        expect(root.classes()).not.toContain('centered-container--xl');
    });

    it('removes horizontal padding when padding is false', () => {
        const wrapper = mount(PublicCenteredContainer, {
            props: { padding: false },
        });

        expect(wrapper.get('[data-testid="centered-container"]').classes()).toContain(
            'centered-container--no-padding',
        );
    });

    it('passes through extra attributes like id', () => {
        const wrapper = mount(PublicCenteredContainer, {
            props: { tag: 'main' },
            attrs: { id: 'main-content' },
        });

        expect(wrapper.get('[data-testid="centered-container"]').attributes('id')).toBe('main-content');
    });
});
