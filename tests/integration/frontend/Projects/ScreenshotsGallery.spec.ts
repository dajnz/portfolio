import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ScreenshotsGallery from '@projects/Public/Components/ScreenshotsGallery.vue';

const SCREENSHOTS = [
    {
        id: 1,
        image_url: '/assets/projects/ledger-1.png',
        alt_text: 'Ledger dashboard',
        title: 'Dashboard',
        description: 'Invoice queue at a glance.',
    },
    {
        id: 2,
        image_url: '/assets/projects/ledger-2.png',
        alt_text: 'Approval workflow',
        title: 'Approval Workflow',
        description: 'Multi-stage approval with audit trail.',
    },
];

function mountGallery(props: Record<string, unknown> = {}) {
    return mount(ScreenshotsGallery, {
        props: { screenshots: SCREENSHOTS, ...props } as unknown as InstanceType<
            typeof ScreenshotsGallery
        >['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('ScreenshotsGallery', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('is hidden when there are no screenshots', () => {
        const wrapper = mountGallery({ screenshots: [] });

        expect(wrapper.find('[data-testid="screenshots-gallery"]').exists()).toBe(false);
    });

    it('renders the slides with captions and dot indicators', async () => {
        const wrapper = mountGallery();

        const gallery = wrapper.get('[data-testid="screenshots-gallery"]');
        expect(gallery.findAll('[data-testid^="screenshots-gallery-slide-"]')).toHaveLength(2);
        expect(
            gallery.get('[data-testid="screenshots-gallery-slide-0"] figcaption h3').text(),
        ).toBe('Dashboard');
        expect(
            gallery.get('[data-testid="screenshots-gallery-slide-0"] figcaption p').text(),
        ).toBe('Invoice queue at a glance.');
        expect(
            gallery.findAll('[data-testid^="screenshots-gallery-carousel-dot-"]'),
        ).toHaveLength(2);
        // The detail gallery keeps the large dots (no compact modifier).
        expect(gallery.get('[data-testid="screenshots-gallery-carousel"]').classes()).not.toContain(
            'carousel--compact-dots',
        );
    });

    it('opens the lightbox at the clicked index (chunk load first)', async () => {
        const wrapper = mountGallery();

        await wrapper.get('[data-testid="screenshots-gallery-slide-1"]').trigger('click');

        // The chunk skeleton overlay shows while the lightbox module loads.
        expect(wrapper.find('[data-testid="screenshots-gallery-chunk-loading"]').exists()).toBe(true);

        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="screenshots-gallery-lightbox"]').exists()).toBe(
                    true,
                );
            },
            { timeout: 5000 },
        );

        const lightbox = wrapper.get('[data-testid="screenshots-gallery-lightbox"]');
        expect(lightbox.get('[data-testid="screenshots-gallery-lightbox-caption"] h3').text()).toBe(
            'Approval Workflow',
        );
        expect(wrapper.find('[data-testid="screenshots-gallery-chunk-loading"]').exists()).toBe(
            false,
        );
    });

    it('does not open the lightbox when the carousel arrows or dots are clicked', async () => {
        const wrapper = mountGallery();

        await wrapper.get('[data-testid="screenshots-gallery-carousel-next"]').trigger('click');
        await wrapper.get('[data-testid="screenshots-gallery-carousel-dot-1"]').trigger('click');

        expect(wrapper.find('[data-testid="screenshots-gallery-lightbox"]').exists()).toBe(false);
    });
});
