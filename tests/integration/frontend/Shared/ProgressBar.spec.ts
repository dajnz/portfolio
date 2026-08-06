import ProgressBar from '@shared-admin/ProgressBar.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

function mountBar(props: Record<string, unknown> = {}) {
    return mount(ProgressBar, { props });
}

describe('ProgressBar', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('shows the value with aria-valuenow, width and percentage text', () => {
        const wrapper = mountBar({ value: 50 });

        const bar = wrapper.get('[role="progressbar"]');
        const fill = wrapper.get('[role="progressbar"] > div');

        expect(bar.attributes('aria-valuenow')).toBe('50');
        expect(bar.attributes('aria-valuemin')).toBe('0');
        expect(bar.attributes('aria-valuemax')).toBe('100');
        expect((fill.element as HTMLElement).style.width).toBe('50%');
        expect(wrapper.text()).toContain('50%');
    });

    it('clamps the value to the 0-100 range', () => {
        const high = mountBar({ value: 150 });
        const low = mountBar({ value: -10 });

        expect(high.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('100');
        expect((high.get('[role="progressbar"] > div').element as HTMLElement).style.width).toBe(
            '100%',
        );
        expect(high.text()).toContain('150%');

        expect(low.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('0');
        expect((low.get('[role="progressbar"] > div').element as HTMLElement).style.width).toBe(
            '0%',
        );
    });

    it('renders an indeterminate bar without a value when indeterminate', () => {
        const wrapper = mountBar({ indeterminate: true });

        const bar = wrapper.get('[role="progressbar"]');

        expect(bar.attributes('aria-valuenow')).toBeUndefined();
        expect(wrapper.get('[role="progressbar"] > div').classes()).toContain('animate-pulse');
        expect(wrapper.text()).not.toContain('%');
    });

    it('treats a null value as indeterminate', () => {
        const wrapper = mountBar({ value: null });

        expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBeUndefined();
        expect(wrapper.get('[role="progressbar"] > div').classes()).toContain('animate-pulse');
    });

    it('applies the variant fill classes', () => {
        expect(mountBar({ value: 50 }).get('[role="progressbar"] > div').classes()).toContain(
            'bg-primary-600',
        );
        expect(
            mountBar({ value: 50, variant: 'success' }).get('[role="progressbar"] > div').classes(),
        ).toContain('bg-success-500');
        expect(
            mountBar({ value: 50, variant: 'danger' }).get('[role="progressbar"] > div').classes(),
        ).toContain('bg-danger-600');
    });

    it('hides the percentage text when show_percentage is false', () => {
        const wrapper = mountBar({ value: 50, show_percentage: false });

        expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('50');
        expect(wrapper.text()).not.toContain('50%');
    });

    it('renders the label and exposes it via aria-label', () => {
        const wrapper = mountBar({ value: 50, label: 'Uploading' });

        expect(wrapper.text()).toContain('Uploading');
        expect(wrapper.get('[role="progressbar"]').attributes('aria-label')).toBe('Uploading');
    });
});
