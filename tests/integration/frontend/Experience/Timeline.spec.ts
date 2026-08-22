import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    router: { on: () => () => {} },
    usePage: () => ({ props: { locale: 'en' } }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import Timeline from '@experience-public/Timeline.vue';
import { ensureExperienceIcons } from '@experience/Public/iconRegistration';

function viewItem(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        position: 'Engineer',
        company_name: 'Acme Ltd',
        company_country: null,
        started_at: '2024-01-01',
        ended_at: null,
        is_current: true,
        project_name: null,
        project_slug: null,
        short_description: 'Builds things.',
        tech_tags: [
            { id: 11, name: 'Laravel', icon: 'devicon:laravel' },
            { id: 12, name: 'Vue 3', icon: 'devicon:vuejs' },
            { id: 13, name: 'PostgreSQL', icon: 'devicon:postgresql' },
            { id: 14, name: 'Redis', icon: 'devicon:redis' },
        ],
        achievements: [],
        period: '2024 – Present',
        heading: 'Engineer @ Acme Ltd',
        ...overrides,
    };
}

async function flush(): Promise<void> {
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

describe('Timeline', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('renders the loading state as alternating item skeletons', () => {
        const wrapper = mount(Timeline, { props: { items: [], is_loading: true } });

        const list = wrapper.get('[data-testid="timeline"]');
        expect(list.attributes('data-state')).toBe('loading');

        const skeletons = list.findAll('.timeline-item');
        expect(skeletons).toHaveLength(4);
        expect(skeletons[0].classes()).toContain('timeline-item--left');
        expect(skeletons[1].classes()).toContain('timeline-item--right');
    });

    it('caps the rail with a decorative briefcase badge', async () => {
        const wrapper = mount(Timeline, { props: { items: [viewItem()] } });
        await flush();

        const cap = wrapper.get('[data-testid="timeline-cap"]');
        expect(cap.attributes('aria-hidden')).toBe('true');
        expect(cap.find('.iconify').exists()).toBe(true);
    });

    it('hides the cap with the empty state', () => {
        const wrapper = mount(Timeline, { props: { items: [] } });

        expect(wrapper.find('[data-testid="timeline-cap"]').exists()).toBe(false);
    });

    it('renders the empty state when there are no items', () => {
        const wrapper = mount(Timeline, { props: { items: [] } });

        const empty = wrapper.get('[data-testid="timeline-empty"]');
        expect(empty.text()).toContain('No experience entries yet.');
    });

    it('renders the items alternating left and right by index', async () => {
        const wrapper = mount(Timeline, {
            props: {
                items: [viewItem({ id: 1 }), viewItem({ id: 2, company_name: 'Beta GmbH' })],
            },
        });
        await flush();

        const items = wrapper.findAll('.timeline-item');
        expect(items).toHaveLength(2);
        expect(items[0].classes()).toContain('timeline-item--left');
        expect(items[1].classes()).toContain('timeline-item--right');
    });

    it('bubbles the show-details and show-tags events from the items', async () => {
        // The +N overflow button renders only after the icon registration
        // resolves (the `iconsReady` guard, B237 pattern) — the page
        // triggers it, the direct-mount spec must too. The lazy import
        // pays SFC/JSON transform time that varies under parallel suite
        // load (B242) — poll instead of a fixed wait.
        ensureExperienceIcons();

        const wrapper = mount(Timeline, { props: { items: [viewItem()] } });

        const deadline = Date.now() + 10_000;
        while (!wrapper.find('[data-testid="timeline-item-1-tags-overflow"]').exists()) {
            if (Date.now() > deadline) {
                throw new Error('The +N overflow button did not render within 10s');
            }

            await new Promise((resolve) => setTimeout(resolve, 100));
            await flushPromises();
        }

        await wrapper.get('[data-testid="timeline-item-1-details"]').trigger('click');
        expect(wrapper.emitted('show-details')).toEqual([[1]]);

        await wrapper.get('[data-testid="timeline-item-1-tags-overflow"]').trigger('click');
        expect(wrapper.emitted('show-tags')).toEqual([[1]]);
    });
});
