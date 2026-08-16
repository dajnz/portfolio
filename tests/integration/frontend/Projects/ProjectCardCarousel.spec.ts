import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ProjectCardCarousel from '@projects/Public/Components/ProjectCardCarousel.vue';

const SCREENSHOTS = [
    { image_url: '/assets/projects/ledger-1.png', alt_text: 'Dashboard' },
    { image_url: '/assets/projects/ledger-2.png', alt_text: 'Approvals' },
    { image_url: '/assets/projects/ledger-3.png', alt_text: 'Payments sync' },
];

function mountCarousel(props: Record<string, unknown> = {}) {
    return mount(ProjectCardCarousel, {
        props: { screenshots: SCREENSHOTS, ...props } as unknown as InstanceType<
            typeof ProjectCardCarousel
        >['$props'],
        attachTo: document.body,
    });
}

describe('ProjectCardCarousel', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders a static image for a single screenshot (no arrows, no dots)', () => {
        const wrapper = mountCarousel({ screenshots: [SCREENSHOTS[0]] });

        expect(wrapper.find('[data-testid="project-card-carousel-carousel"]').exists()).toBe(false);
        expect(wrapper.get('img').attributes('src')).toBe('/assets/projects/ledger-1.png');
        expect(wrapper.get('img').attributes('alt')).toBe('Dashboard');
        expect(wrapper.find('[data-testid="project-card-carousel-carousel-dot-0"]').exists()).toBe(
            false,
        );
    });

    it('renders the carousel with dots for multiple screenshots', () => {
        const wrapper = mountCarousel();

        const carousel = wrapper.get('[data-testid="project-card-carousel-carousel"]');
        expect(carousel.findAll('[data-testid^="project-card-carousel-carousel-dot-"]')).toHaveLength(
            3,
        );
        expect(carousel.findAll('img')).toHaveLength(3);
        // The card variant uses the compact dots (the detail gallery
        // keeps the larger ones).
        expect(carousel.classes()).toContain('carousel--compact-dots');
    });

    it('does not navigate (bubble) when an arrow or a dot is clicked', async () => {
        const wrapper = mountCarousel();
        const clickSpy = vi.fn();
        wrapper.element.addEventListener('click', clickSpy);

        await wrapper.get('[data-testid="project-card-carousel-carousel-next"]').trigger('click');
        await wrapper.get('[data-testid="project-card-carousel-carousel-dot-2"]').trigger('click');

        expect(clickSpy).not.toHaveBeenCalled();
    });

    it('auto-advances every 5 seconds and stops while hovered', async () => {
        vi.useFakeTimers();

        const wrapper = mountCarousel();

        await wrapper
            .get('[data-testid="project-card-carousel-carousel-track"]')
            .trigger('mouseenter');
        await vi.advanceTimersByTimeAsync(5000);
        expect(
            wrapper
                .get('[data-testid="project-card-carousel-carousel-slide-0"]')
                .classes(),
        ).toContain('is-active');

        await wrapper
            .get('[data-testid="project-card-carousel-carousel-track"]')
            .trigger('mouseleave');
        await vi.advanceTimersByTimeAsync(5000);

        expect(
            wrapper
                .get('[data-testid="project-card-carousel-carousel-slide-1"]')
                .classes(),
        ).toContain('is-active');

        vi.useRealTimers();
    });

    it('renders the skeleton while loading and the placeholder when empty', () => {
        const loading = mountCarousel({ is_loading: true });
        expect(loading.find('.skeleton--rectangle').exists()).toBe(true);

        const empty = mountCarousel({ screenshots: [] });
        expect(empty.find('.project-card-carousel__image--placeholder').exists()).toBe(true);
    });
});
