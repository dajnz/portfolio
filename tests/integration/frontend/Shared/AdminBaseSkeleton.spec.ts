import AdminBaseSkeleton from '@shared-admin/AdminBaseSkeleton.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseSkeleton', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a single text line by default', () => {
        const wrapper = mount(AdminBaseSkeleton);

        expect(wrapper.findAll('[data-testid="base-skeleton"]')).toHaveLength(1);
        expect(wrapper.get('[data-testid="base-skeleton"]').element.tagName).toBe('SPAN');
    });

    it('renders repeat text lines with distinct widths', () => {
        const wrapper = mount(AdminBaseSkeleton, {
            props: { repeat: 3 },
        });

        const lines = wrapper.findAll('[data-testid="base-skeleton"]');

        expect(lines).toHaveLength(3);
        expect(lines[0].attributes('style')).toContain('width: 40%');
        expect(lines[1].attributes('style')).toContain('width: 60%');
        expect(lines[2].attributes('style')).toContain('width: 75%');
    });

    it('uses the explicit width when provided', () => {
        const wrapper = mount(AdminBaseSkeleton, {
            props: { width: '70%' },
        });

        expect(wrapper.get('[data-testid="base-skeleton"]').attributes('style')).toContain(
            'width: 70%',
        );
    });

    it('renders the shape classes for non-text shapes', () => {
        const circle = mount(AdminBaseSkeleton, {
            props: { shape: 'circle', width: '120px', height: '120px' },
        });
        const rectangle = mount(AdminBaseSkeleton, {
            props: { shape: 'rectangle', width: '100%', height: '80px' },
        });

        expect(circle.get('[data-testid="base-skeleton"]').element.tagName).toBe('DIV');
        expect(circle.get('[data-testid="base-skeleton"]').classes()).toContain('rounded-full');
        expect(circle.get('[data-testid="base-skeleton"]').attributes('style')).toContain(
            'height: 120px',
        );
        expect(rectangle.get('[data-testid="base-skeleton"]').classes()).toContain('rounded-md');
    });

    it('disables the shimmer animation when animated is false', () => {
        const wrapper = mount(AdminBaseSkeleton, {
            props: { animated: false },
        });

        expect(wrapper.get('[data-testid="base-skeleton"]').classes()).not.toContain(
            'animate-pulse',
        );
    });

    it('is hidden from assistive technology', () => {
        const wrapper = mount(AdminBaseSkeleton);

        expect(wrapper.get('[data-testid="base-skeleton"]').attributes('aria-hidden')).toBe('true');
    });
});
