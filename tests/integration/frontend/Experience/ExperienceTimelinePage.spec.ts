import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { reloadMock, pageRef, pageProps } = vi.hoisted(() => {
    const pageProps = {
        locale: 'en',
        supported_locales: ['en', 'ru'],
    };

    return {
        reloadMock: vi.fn(),
        pageProps,
        pageRef: { value: null as { props: typeof pageProps; url?: string } | null },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: [],
        template: '<div style="display:none"><slot /></div>',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        reload: reloadMock,
        on: () => () => {},
    },
    usePage: () => {
        if (pageRef.value === null) {
            pageRef.value = reactive({ props: pageProps, url: '/experience' });
        }

        return pageRef.value;
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), setPageContext: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ExperienceTimelinePage from '@experience/Public/Pages/ExperienceTimelinePage.vue';

const ITEMS = [
    {
        id: 1,
        position: 'Senior Full-Stack Developer',
        company_name: 'InnoSoft GmbH',
        company_country: 'Germany',
        project_id: 7,
        project_name: 'Ledger — Invoice Automation Platform',
        project_slug: 'ledger',
        short_description: 'Leading the invoice automation platform: OCR pipeline and approvals.',
        started_at: '2024-05-10',
        ended_at: null,
        is_current: true,
        tech_tags: [
            { id: 11, name: 'Laravel', icon: 'devicon:laravel' },
            { id: 12, name: 'Vue 3', icon: 'devicon:vuejs' },
            { id: 13, name: 'PostgreSQL', icon: 'devicon:postgresql' },
            { id: 14, name: 'Redis', icon: 'devicon:redis' },
            { id: 15, name: 'Docker', icon: 'devicon:docker' },
        ],
        achievements: [
            { description: 'Cut p95 response times by 60%.', project_link: 'https://example.com/1' },
            { description: 'Mentored two juniors.', project_link: null },
        ],
    },
    {
        id: 2,
        position: 'Full-Stack Developer',
        company_name: 'WebCraft Studio',
        company_country: null,
        project_id: null,
        project_name: 'Order Management System',
        project_slug: null,
        short_description: 'Built e-commerce backends and admin dashboards.',
        started_at: '2021-06-01',
        ended_at: '2024-04-30',
        is_current: false,
        tech_tags: [
            { id: 21, name: 'PHP', icon: 'devicon:php' },
            { id: 22, name: 'Laravel', icon: 'devicon:laravel' },
        ],
        achievements: [],
    },
];

const PAGE_PROPS = {
    items: ITEMS,
    has_more: false,
    next_cursor: null,
    has_projects: true,
    seo: { title: 'Experience — Alex Kovalev', description: 'Professional experience.' },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(ExperienceTimelinePage, {
        props: { ...PAGE_PROPS, ...props } as unknown as InstanceType<typeof ExperienceTimelinePage>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

async function flush(): Promise<void> {
    // The eager icon registration resolves across a microtask/macrotask
    // turn; the modals' real Transition renders its v-if content after a
    // macrotask flush (VTU's default transition stub drops toggles).
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

// Template whitespace (newlines/indentation between interpolations and
// inline elements) collapses visually but survives in textContent.
function normalizedText(element: { text(): string }): string {
    return element.text().replace(/\s+/g, ' ').trim();
}

describe('ExperienceTimelinePage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        reloadMock.mockClear();
        reloadMock.mockReset();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders the layout, the page title and the nav with the Experience link active', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="experience-page"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="experience-page-title"]').text()).toBe('Work Experience');

        const experienceNav = wrapper.get('[data-testid="public-header-nav-/experience"]');
        expect(experienceNav.classes()).toContain('is-active');
        expect(experienceNav.attributes('aria-current')).toBe('page');
        expect(wrapper.get('[data-testid="public-header-nav-/#about"]').attributes('href')).toBe('/#about');
        expect(wrapper.get('[data-testid="public-header-nav-/projects"]').attributes('href')).toBe('/projects');
        expect(wrapper.get('[data-testid="public-header-nav-/blog"]').attributes('href')).toBe('/blog');
        expect(wrapper.get('[data-testid="public-header-nav-/#contacts"]').attributes('href')).toBe('/#contacts');
    });

    it('hides the Projects nav link when no published projects exist (ADR-0025)', async () => {
        const wrapper = mountPage({ has_projects: false });
        await flush();

        expect(wrapper.find('[data-testid="public-header-nav-/projects"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="public-header-nav-/experience"]').exists()).toBe(true);
    });

    it('renders the alternating items with period, heading, country and description', async () => {
        const wrapper = mountPage();
        await flush();

        const first = wrapper.get('[data-testid="experience-page-timeline-item-1"]');
        expect(first.classes()).toContain('timeline-item--left');
        expect(first.classes()).toContain('timeline-item--current');
        expect(first.get('[data-testid="experience-page-timeline-item-1-period"]').text()).toBe(
            '2024 – Present',
        );
        expect(first.get('[data-testid="experience-page-timeline-item-1-position"]').text()).toBe(
            'Senior Full-Stack Developer',
        );
        expect(
            normalizedText(first.get('[data-testid="experience-page-timeline-item-1-company"]')),
        ).toBe('InnoSoft GmbH · Germany');
        expect(
            first.get('[data-testid="experience-page-timeline-item-1-description"]').text(),
        ).toContain('OCR pipeline');

        const second = wrapper.get('[data-testid="experience-page-timeline-item-2"]');
        expect(second.classes()).toContain('timeline-item--right');
        expect(second.classes()).not.toContain('timeline-item--current');
        expect(second.get('[data-testid="experience-page-timeline-item-2-period"]').text()).toBe(
            '2021 – 2024',
        );
        expect(second.get('[data-testid="experience-page-timeline-item-2-position"]').text()).toBe(
            'Full-Stack Developer',
        );
        expect(second.get('[data-testid="experience-page-timeline-item-2-company"]').text()).toBe(
            'WebCraft Studio',
        );
    });

    it('links the project name to /projects/{slug} and renders plain text otherwise', async () => {
        const wrapper = mountPage();
        await flush();

        const linked = wrapper.get('[data-testid="experience-page-timeline-item-1-project"]');
        expect(linked.element.tagName).toBe('A');
        expect(linked.attributes('href')).toBe('/projects/ledger');
        expect(linked.text()).toBe('Ledger — Invoice Automation Platform');

        const plain = wrapper.get('[data-testid="experience-page-timeline-item-2-project"]');
        expect(plain.element.tagName).toBe('SPAN');
        expect(plain.text()).toBe('Order Management System');
    });

    it('shows the first 3 tags plus the +N overflow button opening the tag popup', async () => {
        const wrapper = mountPage();
        await flush();

        const tags = wrapper.get('[data-testid="experience-page-timeline-item-1-tags"]');
        expect(tags.findAll('[data-testid^="experience-page-timeline-item-1-tag-"]')).toHaveLength(3);

        const overflow = wrapper.get('[data-testid="experience-page-timeline-item-1-tags-overflow"]');
        expect(overflow.text()).toBe('+2');

        await overflow.trigger('click');
        await flush();

        const popup = wrapper.get('[data-testid="tag-popup-modal"]');
        expect(wrapper.find('[data-testid="tag-popup-modal"]').exists()).toBe(true);
        expect(popup.get('h2').text()).toBe('Senior Full-Stack Developer');
        expect(popup.get('[data-testid="tag-popup-modal-company"]').text()).toBe('InnoSoft GmbH');
        expect(popup.text()).toContain('All tags');
        expect(popup.findAll('[data-testid="tech-skill-tag"]')).toHaveLength(5);
    });

    it('omits the +N button when all tags fit the visible count', async () => {
        const wrapper = mountPage();
        await flush();

        expect(
            wrapper.find('[data-testid="experience-page-timeline-item-2-tags-overflow"]').exists(),
        ).toBe(false);
        expect(
            wrapper.findAll('[data-testid^="experience-page-timeline-item-2-tag-"]'),
        ).toHaveLength(2);
    });

    it('opens the detail modal via the Details button with achievements, tags and the project link', async () => {
        const wrapper = mountPage();
        await flush();

        await wrapper.get('[data-testid="experience-page-timeline-item-1-details"]').trigger('click');
        await flush();

        const modal = wrapper.get('[data-testid="experience-detail-modal"]');
        expect(wrapper.find('[data-testid="experience-detail-modal"]').exists()).toBe(true);
        expect(modal.get('h2').text()).toBe('Senior Full-Stack Developer');
        expect(modal.get('[data-testid="experience-detail-modal-company"]').text()).toBe('InnoSoft GmbH');
        expect(modal.get('[data-testid="experience-detail-modal-period"]').text()).toBe('2024 – Present');
        expect(modal.text()).toContain('Achievements');
        expect(modal.text()).toContain('Cut p95 response times by 60%.');
        expect(modal.get('[data-testid="experience-detail-modal-achievement-0-link"]').attributes('href')).toBe(
            'https://example.com/1',
        );
        expect(modal.text()).toContain('Tech Stack');
        expect(modal.findAll('[data-testid="tech-skill-tag"]')).toHaveLength(5);
        expect(modal.get('[data-testid="experience-detail-modal-project-link"]').attributes('href')).toBe(
            '/projects/ledger',
        );
    });

    it('opens the detail modal on a card click but not on its interactive children', async () => {
        const wrapper = mountPage();
        await flush();

        await wrapper.get('[data-testid="experience-page-timeline-item-1-description"]').trigger('click');
        await flush();
        expect(wrapper.find('[data-testid="experience-detail-modal"]').exists()).toBe(true);

        // Close via the scrim, then click the project LINK — an
        // interactive child must not trigger the modal.
        await wrapper.get('[data-testid="base-modal-scrim"]').trigger('click');
        await flush();
        expect(wrapper.find('[data-testid="experience-detail-modal"]').exists()).toBe(false);

        await wrapper.get('[data-testid="experience-page-timeline-item-1-project"]').trigger('click');
        await flush();
        expect(wrapper.find('[data-testid="experience-detail-modal"]').exists()).toBe(false);
    });

    it('loads more with the opaque cursor via a replace-state reload', async () => {
        const wrapper = mountPage({ has_more: true, next_cursor: 'eyJpZCI6MiwidiI6IjIwMjEtMDYtMDEifQ' });
        await flush();

        await wrapper.get('[data-testid="experience-page-load-more-button"]').trigger('click');
        await flush();

        expect(reloadMock).toHaveBeenCalledTimes(1);
        const options = reloadMock.mock.calls[0][0];
        expect(options.data).toEqual({ cursor: 'eyJpZCI6MiwidiI6IjIwMjEtMDYtMDEifQ' });
        expect(options.replace).toBe(true);
    });

    it('renders nothing for the load-more button when the list is exhausted', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="experience-page-load-more"]').exists()).toBe(false);
    });

    it('renders the empty state when no experience items exist', async () => {
        const wrapper = mountPage({ items: [] });
        await flush();

        const empty = wrapper.get('[data-testid="experience-page-timeline-empty"]');
        expect(wrapper.find('[data-testid="experience-page-timeline-empty"]').exists()).toBe(true);
        expect(empty.text()).toContain('No experience entries yet.');
    });

    it('shows the inline error banner on a failed load and retries from the initial batch', async () => {
        reloadMock.mockImplementationOnce((options: { onNetworkError?: (error: Error) => void }) => {
            options.onNetworkError?.(new Error('network down'));
        });

        const wrapper = mountPage({ has_more: true, next_cursor: 'eyJpZCI6MiwidiI6IjIwMjEtMDYtMDEifQ' });
        await flush();

        await wrapper.get('[data-testid="experience-page-load-more-button"]').trigger('click');
        await flush();

        const error = wrapper.get('[data-testid="experience-page-error"]');
        expect(error.text()).toContain('Could not load experience.');
        expect(wrapper.find('[data-testid="experience-page-timeline"]').exists()).toBe(false);

        await error.get('[data-testid="experience-page-error-action"]').trigger('click');
        await flush();

        expect(reloadMock).toHaveBeenCalledTimes(2);
        expect(reloadMock.mock.calls[1][0].data).toBeUndefined();
        expect(wrapper.find('[data-testid="experience-page-error"]').exists()).toBe(false);
    });
});
