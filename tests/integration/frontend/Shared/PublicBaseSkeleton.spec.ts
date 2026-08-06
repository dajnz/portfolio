import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';

describe('PublicBaseSkeleton', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a single text line by default with the text shape class', () => {
        const wrapper = mount(PublicBaseSkeleton);

        expect(wrapper.findAll('.skeleton--text')).toHaveLength(1);
    });

    it('renders repeat text lines with distinct widths', () => {
        const wrapper = mount(PublicBaseSkeleton, {
            props: { repeat: 3 },
        });

        const lines = wrapper.findAll('.skeleton--text');

        expect(lines).toHaveLength(3);
        expect(lines[0].attributes('style')).toContain('width: 40%');
        expect(lines[1].attributes('style')).toContain('width: 60%');
    });

    it('uses the explicit width when provided', () => {
        const wrapper = mount(PublicBaseSkeleton, {
            props: { width: '70%' },
        });

        expect(wrapper.get('.skeleton--text').attributes('style')).toContain('width: 70%');
    });

    it('renders the shape class for non-text shapes', () => {
        const wrapper = mount(PublicBaseSkeleton, {
            props: { shape: 'circle', width: '120px', height: '120px' },
        });

        const skeleton = wrapper.get('.skeleton');

        expect(skeleton.classes()).toContain('skeleton--circle');
        expect(skeleton.attributes('style')).toContain('height: 120px');
    });

    it('disables the shimmer animation when animated is false', () => {
        const wrapper = mount(PublicBaseSkeleton, {
            props: { animated: false },
        });

        expect(wrapper.get('.skeleton').classes()).toContain('skeleton--static');
    });

    it('is hidden from assistive technology', () => {
        const wrapper = mount(PublicBaseSkeleton);

        expect(wrapper.get('.skeleton').attributes('aria-hidden')).toBe('true');
    });
});
