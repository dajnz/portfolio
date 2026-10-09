import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HorizontalCarousel from '@shared-public/HorizontalCarousel.vue';

const ITEMS = [{ id: 1 }, { id: 2 }, { id: 3 }];

describe('HorizontalCarousel', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders one slide per item with slide labels', () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
            slots: { default: '<template #default="{ item }"><span>{{ item.id }}</span></template>' },
        });

        expect(wrapper.findAll('[data-testid^="carousel-slide-"]')).toHaveLength(3);
        expect(wrapper.get('[data-testid="carousel-slide-0"]').attributes('aria-label')).toBe(
            'Slide 1 of 3',
        );
        expect(wrapper.get('[data-testid="carousel-track"]').attributes('aria-roledescription')).toBe(
            'carousel',
        );
    });

    it('exposes item, index and is_active through the slot', () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
            slots: {
                default: `
                    <template #default="{ item, index, is_active }">
                        <span data-testid="slide-info">{{ item.id }}:{{ index }}:{{ is_active }}</span>
                    </template>
                `,
            },
        });

        expect(wrapper.get('[data-testid="slide-info"]').text()).toBe('1:0:true');
        expect(wrapper.get('[data-testid="slide-info"]').text()).toContain('1:0');
    });

    it('emits change with the new index on arrow navigation', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
        });

        await wrapper.get('[data-testid="carousel-next"]').trigger('click');

        expect(wrapper.emitted('change')).toEqual([[1]]);
    });

    it('disables the prev arrow on the first slide and next on the last', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
        });

        expect(
            (wrapper.get('[data-testid="carousel-prev"]').element as HTMLButtonElement).disabled,
        ).toBe(true);

        await wrapper.get('[data-testid="carousel-next"]').trigger('click');
        await wrapper.get('[data-testid="carousel-next"]').trigger('click');
        await wrapper.get('[data-testid="carousel-next"]').trigger('click');

        expect(
            (wrapper.get('[data-testid="carousel-next"]').element as HTMLButtonElement).disabled,
        ).toBe(true);
    });

    it('navigates with the keyboard: arrows, Home and End', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
        });

        const track = wrapper.get('[data-testid="carousel-track"]');

        await track.trigger('keydown', { key: 'ArrowRight' });
        expect(wrapper.emitted('change')).toEqual([[1]]);

        await track.trigger('keydown', { key: 'ArrowLeft' });
        expect(wrapper.emitted('change')).toEqual([[1], [0]]);

        await track.trigger('keydown', { key: 'End' });
        expect(wrapper.emitted('change')).toEqual([[1], [0], [2]]);

        await track.trigger('keydown', { key: 'Home' });
        expect(wrapper.emitted('change')).toEqual([[1], [0], [2], [0]]);
    });

    it('renders dot indicators with aria-current on the active slide', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS, show_dots: true },
        });

        const dots = wrapper.findAll('[data-testid^="carousel-dot-"]');

        expect(dots).toHaveLength(3);
        expect(dots[0].attributes('aria-label')).toBe('Go to slide 1');
        expect(dots[0].attributes('aria-current')).toBe('true');
        expect(dots[1].attributes('aria-current')).toBeUndefined();

        await wrapper.get('[data-testid="carousel-dot-2"]').trigger('click');

        expect(dots[2].attributes('aria-current')).toBe('true');
        expect(wrapper.emitted('change')).toEqual([[2]]);
    });

    it('hides the dots for a single item', () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: [{ id: 1 }], show_dots: true },
        });

        expect(wrapper.findAll('[data-testid^="carousel-dot-"]')).toHaveLength(0);
    });

    it('auto-advances on the configured interval', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS, auto_advance_ms: 1000 },
        });

        await vi.advanceTimersByTimeAsync(1000);
        expect(wrapper.emitted('change')).toEqual([[1]]);

        await vi.advanceTimersByTimeAsync(1000);
        expect(wrapper.emitted('change')).toEqual([[1], [2]]);

        await vi.advanceTimersByTimeAsync(1000);
        expect(wrapper.emitted('change')).toEqual([[1], [2], [0]]);
    });

    it('pauses auto-advance while hovered or focused', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS, auto_advance_ms: 1000 },
        });

        const track = wrapper.get('[data-testid="carousel-track"]');

        await track.trigger('mouseenter');
        await vi.advanceTimersByTimeAsync(5000);
        expect(wrapper.emitted('change')).toBeUndefined();

        await track.trigger('mouseleave');
        await vi.advanceTimersByTimeAsync(1000);
        expect(wrapper.emitted('change')).toEqual([[1]]);
    });

    it('does not auto-advance when auto_advance_ms is null', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
        });

        await vi.advanceTimersByTimeAsync(5000);

        expect(wrapper.emitted('change')).toBeUndefined();
    });

    it('changes slides on a horizontal swipe', async () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS },
        });

        const track = wrapper.get('[data-testid="carousel-track"]');

        await track.trigger('pointerdown', { clientX: 200, clientY: 100 });
        await track.trigger('pointerup', { clientX: 150, clientY: 105 });

        expect(wrapper.emitted('change')).toEqual([[1]]);
    });

    it('applies the slides_per_view and gap as css variables', () => {
        const wrapper = mount(HorizontalCarousel, {
            props: { items: ITEMS, slides_per_view: 2, gap: '24px' },
        });

        const viewport = wrapper.get('.carousel__viewport');
        expect(viewport.attributes('style')).toContain('--carousel-gap: 24px');
        expect(viewport.attributes('style')).toContain('--carousel-slides: 2');
    });
});
