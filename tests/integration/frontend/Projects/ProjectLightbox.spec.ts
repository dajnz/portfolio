import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import ProjectLightbox from '@projects/Public/Components/ProjectLightbox.vue';

const IMAGES = [
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
    {
        id: 3,
        image_url: '/assets/projects/ledger-3.png',
        alt_text: 'Payments sync',
        title: 'Payments Sync',
        description: 'Reconciliation with the accounting system.',
    },
];

function mountLightbox(props: Record<string, unknown> = {}) {
    return mount(ProjectLightbox, {
        props: {
            modelValue: true,
            images: IMAGES,
            ...props,
        } as unknown as InstanceType<typeof ProjectLightbox>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('ProjectLightbox', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders the current image, caption and thumbnails with view labels', () => {
        const wrapper = mountLightbox();

        expect(wrapper.get('[data-testid="project-lightbox-image"]').attributes('src')).toBe(
            '/assets/projects/ledger-1.png',
        );
        expect(wrapper.get('[data-testid="project-lightbox-caption"] h3').text()).toBe(
            'Dashboard',
        );
        expect(wrapper.get('[data-testid="project-lightbox-caption"] p').text()).toBe(
            'Invoice queue at a glance.',
        );
        expect(wrapper.get('[data-testid="project-lightbox-thumb-0"]').attributes('aria-label')).toBe(
            'View image 1: Dashboard',
        );
        expect(wrapper.get('[data-testid="project-lightbox-thumb-0"]').attributes('aria-current')).toBe(
            'true',
        );
        expect(wrapper.findAll('[data-testid^="project-lightbox-thumb-"]')).toHaveLength(3);
    });

    it('disables prev on the first image and next on the last (no wrapping)', async () => {
        const wrapper = mountLightbox();

        expect(
            (wrapper.get('[data-testid="project-lightbox-prev"]').element as HTMLButtonElement)
                .disabled,
        ).toBe(true);

        await wrapper.get('[data-testid="project-lightbox-next"]').trigger('click');
        await wrapper.get('[data-testid="project-lightbox-next"]').trigger('click');
        await wrapper.get('[data-testid="project-lightbox-next"]').trigger('click');

        expect(
            (wrapper.get('[data-testid="project-lightbox-next"]').element as HTMLButtonElement)
                .disabled,
        ).toBe(true);
        expect(wrapper.emitted('navigate')).toEqual([[1], [2]]);
    });

    it('navigates with the keyboard: arrows, Home and End', async () => {
        const wrapper = mountLightbox();
        const panel = wrapper.get('[data-testid="project-lightbox"]');

        await panel.trigger('keydown', { key: 'ArrowRight' });
        await panel.trigger('keydown', { key: 'ArrowLeft' });
        await panel.trigger('keydown', { key: 'End' });
        await panel.trigger('keydown', { key: 'Home' });

        expect(wrapper.emitted('navigate')).toEqual([[1], [0], [2], [0]]);
    });

    it('jumps to the image on a thumbnail click', async () => {
        const wrapper = mountLightbox();

        await wrapper.get('[data-testid="project-lightbox-thumb-2"]').trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([[2]]);
        expect(wrapper.get('[data-testid="project-lightbox-caption"] h3').text()).toBe(
            'Payments Sync',
        );
    });

    it('closes on Escape and the close button', async () => {
        const wrapper = mountLightbox();

        await wrapper.get('[data-testid="project-lightbox"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);

        const reopened = mountLightbox();
        await reopened.get('[data-testid="project-lightbox-close"]').trigger('click');
        expect(reopened.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('shows the skeleton while the image loads and the broken placeholder on error', async () => {
        const wrapper = mountLightbox();

        expect(wrapper.find('.skeleton--rectangle').exists()).toBe(true);

        await wrapper.get('[data-testid="project-lightbox-image"]').trigger('load');
        expect(wrapper.find('.skeleton--rectangle').exists()).toBe(false);

        await wrapper.get('[data-testid="project-lightbox-image"]').trigger('error');
        expect(wrapper.get('[data-testid="project-lightbox-broken"]').text()).toBe(
            'Ledger dashboard',
        );
    });

    it('traps the focus: Tab on the last element wraps to the close button', async () => {
        const wrapper = mountLightbox();

        const close = wrapper.get('[data-testid="project-lightbox-close"]').element as HTMLButtonElement;
        close.focus();

        await wrapper.get('[data-testid="project-lightbox"]').trigger('keydown', { key: 'Tab', shiftKey: false });

        expect(document.activeElement).toBe(close);
    });
});
