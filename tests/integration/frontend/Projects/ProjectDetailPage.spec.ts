import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { pageRef, pageProps } = vi.hoisted(() => {
    const pageProps = {
        locale: 'en',
        supported_locales: ['en', 'ru'],
    };

    return {
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
        on: () => () => {},
    },
    usePage: () => {
        if (pageRef.value === null) {
            pageRef.value = reactive({ props: pageProps, url: '/projects/ledger' });
        }

        return pageRef.value;
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), setPageContext: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ProjectDetailPage from '@projects/Public/Pages/ProjectDetailPage.vue';

const PROJECT = {
    id: 7,
    title: 'Ledger — Invoice Automation Platform',
    slug: 'ledger',
    full_description:
        '<p>Automates the full invoice lifecycle for a mid-size accounting firm.</p>',
    problem: '<p>Invoices arrived as PDFs by email and were manually re-entered.</p><ul><li>~600 invoices/month</li></ul>',
    solution: '<p>An OCR pipeline extracts line items.</p>',
    brief_description: 'Automated invoice processing pipeline with OCR.',
    published_at: '2026-06-15T00:00:00Z',
    preview_image: 'projects/ledger.png',
    preview_image_alt: { en: 'Ledger dashboard preview', ru: 'Превью Ledger' },
    github_url: 'https://example.com/ledger',
    live_demo_url: 'https://example.com/demo',
    is_featured: true,
    tech_tags: [
        { id: 11, name: 'Laravel', icon: 'devicon:laravel' },
        { id: 12, name: 'PostgreSQL', icon: 'devicon:postgresql' },
    ],
    screenshots: [
        {
            id: 1,
            image_path: 'projects/ledger-1.png',
            alt_text: { en: 'Ledger dashboard', ru: 'Дашборд Ledger' },
            title: { en: 'Dashboard', ru: 'Дашборд' },
            description: { en: 'Invoice queue at a glance.', ru: 'Очередь счетов.' },
            sort_order: 0,
        },
        {
            id: 2,
            image_path: 'projects/ledger-2.png',
            alt_text: { en: 'Approval workflow', ru: 'Согласование' },
            title: { en: 'Approval Workflow', ru: 'Согласование' },
            description: { en: 'Multi-stage approval with audit trail.', ru: 'Многоступенчатое согласование.' },
            sort_order: 1,
        },
    ],
    detail_items: [
        {
            id: 31,
            icon: 'heroicons:shield-check',
            title: 'Approval audit trail',
            description: 'Every state transition is append-only.',
        },
        {
            id: 32,
            icon: null,
            title: 'Keyset pagination',
            description: 'Cursor-based pagination on the invoice queue.',
        },
    ],
    tech_writeup: {
        id: 9,
        slug: 'cursor-based-pagination',
        title: 'How we built cursor-based pagination that stays fast',
    },
};

const PAGE_PROPS = {
    project: PROJECT,
    seo: {
        title: 'Ledger — Alex Kovalev',
        description: 'Automated invoice processing pipeline with OCR.',
        json_ld: [
            { '@type': 'SoftwareSourceCode', name: 'Ledger' },
            { '@type': 'BreadcrumbList', itemListElement: [] },
        ],
    },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(ProjectDetailPage, {
        props: { ...PAGE_PROPS, ...props } as unknown as InstanceType<typeof ProjectDetailPage>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

async function flush(): Promise<void> {
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

describe('ProjectDetailPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.body.innerHTML = '';
        window.localStorage.clear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders the title and the nav with Projects active (no back button)', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.get('[data-testid="project-detail-page-title"]').text()).toBe(
            'Ledger — Invoice Automation Platform',
        );
        expect(wrapper.find('[data-testid="project-detail-page-back"]').exists()).toBe(false);

        const projectsNav = wrapper.get('[data-testid="public-header-nav-/projects"]');
        expect(projectsNav.classes()).toContain('is-active');
        expect(projectsNav.attributes('aria-current')).toBe('page');
    });

    it('renders the overview, problem and solution bodies from the pipeline HTML', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.get('[data-testid="project-detail-page-overview"]').html()).toContain(
            'full invoice lifecycle',
        );
        expect(wrapper.get('[data-testid="project-detail-page-problem"]').html()).toContain(
            '<li>~600 invoices/month</li>',
        );
        expect(wrapper.get('[data-testid="project-detail-page-solution"]').html()).toContain(
            'OCR pipeline',
        );
    });

    it('renders the tech stack chips linked to the project tag filter', async () => {
        const wrapper = mountPage();
        await flush();

        const tags = wrapper.get('[data-testid="project-detail-page-tags"]').findAll('a.tech-skill-tag');
        expect(tags).toHaveLength(2);
        expect(tags[0].attributes('href')).toBe('/projects?tags=11');
    });

    it('renders the bottom block as two columns: the writeup card and the labeled links row', async () => {
        const wrapper = mountPage();
        await flush();

        const bottom = wrapper.get('[data-testid="project-detail-page-bottom"]');
        expect(bottom.classes()).toContain('project-detail__bottom--two-col');

        const writeup = bottom.get('[data-testid="project-detail-page-writeup"]');
        expect(writeup.get('a').attributes('href')).toBe('/blog/cursor-based-pagination');
        expect(writeup.text()).toContain('Tech Writeup');
        expect(writeup.text()).toContain('How we built cursor-based pagination that stays fast');

        const links = bottom.get('[data-testid="project-detail-page-links"]');
        expect(links.get('h2').text()).toBe('Links');
        expect(links.get('[data-testid="project-detail-page-github"]').attributes('href')).toBe(
            'https://example.com/ledger',
        );
        expect(links.get('[data-testid="project-detail-page-demo"]').attributes('href')).toBe(
            'https://example.com/demo',
        );
    });

    it('renders a single-column bottom block when only the writeup or only the links exist', async () => {
        const writeupOnly = mountPage({
            project: { ...PROJECT, github_url: null, live_demo_url: null },
        });
        await flush();
        const writeupBottom = writeupOnly.get('[data-testid="project-detail-page-bottom"]');
        expect(writeupBottom.classes()).not.toContain('project-detail__bottom--two-col');
        expect(writeupBottom.find('[data-testid="project-detail-page-writeup"]').exists()).toBe(true);
        expect(writeupBottom.find('[data-testid="project-detail-page-links"]').exists()).toBe(false);

        const linksOnly = mountPage({ project: { ...PROJECT, tech_writeup: null } });
        await flush();
        const linksBottom = linksOnly.get('[data-testid="project-detail-page-bottom"]');
        expect(linksBottom.classes()).not.toContain('project-detail__bottom--two-col');
        expect(linksBottom.find('[data-testid="project-detail-page-writeup"]').exists()).toBe(false);
        expect(linksBottom.find('[data-testid="project-detail-page-links"]').exists()).toBe(true);
    });

    it('hides the bottom block entirely when neither the writeup nor the links exist', async () => {
        const wrapper = mountPage({
            project: {
                ...PROJECT,
                tech_writeup: null,
                github_url: null,
                live_demo_url: null,
            },
        });
        await flush();

        expect(wrapper.find('[data-testid="project-detail-page-bottom"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="project-detail-page-writeup"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="project-detail-page-links"]').exists()).toBe(false);
    });

    it('renders the engineering details grid including the no-icon state', async () => {
        const wrapper = mountPage();
        await flush();

        const details = wrapper.get('[data-testid="project-detail-page-details"]');
        expect(details.findAll('[data-testid^="project-detail-page-detail-"]')).toHaveLength(2);

        const iconItem = wrapper.get('[data-testid="project-detail-page-detail-31"]');
        expect(iconItem.get('.detail-item__icon').attributes('data-icon')).toBe(
            'heroicons:shield-check',
        );
        expect(iconItem.get('h3').text()).toBe('Approval audit trail');

        const plainItem = wrapper.get('[data-testid="project-detail-page-detail-32"]');
        expect(plainItem.classes()).toContain('detail-item--no-icon');
        expect(plainItem.find('.detail-item__icon').exists()).toBe(false);
    });

    it('renders the tech-writeup card only when the linked article is published', async () => {
        const wrapper = mountPage();
        await flush();

        const writeup = wrapper.get('[data-testid="project-detail-page-writeup"]');
        expect(writeup.get('a').attributes('href')).toBe('/blog/cursor-based-pagination');
        expect(writeup.text()).toContain('Tech Writeup');
        expect(writeup.text()).toContain('How we built cursor-based pagination that stays fast');

        const withoutWriteup = mountPage({ project: { ...PROJECT, tech_writeup: null } });
        await flush();
        expect(withoutWriteup.find('[data-testid="project-detail-page-writeup"]').exists()).toBe(
            false,
        );
    });

    it('renders the gallery with the locale-resolved captions and opens the lazy lightbox', async () => {
        const wrapper = mountPage();
        await flush();

        const gallery = wrapper.get('[data-testid="project-detail-page-gallery"]');
        expect(gallery.findAll('[data-testid^="project-detail-page-gallery-slide-"]')).toHaveLength(2);
        expect(gallery.get('[data-testid="project-detail-page-gallery-slide-0"] img').attributes('src')).toBe(
            '/assets/projects/ledger-1.png',
        );
        expect(gallery.get('[data-testid="project-detail-page-gallery-slide-0"] img').attributes('alt')).toBe(
            'Ledger dashboard',
        );
        expect(gallery.get('[data-testid="project-detail-page-gallery-slide-0"] h3').text()).toBe(
            'Dashboard',
        );

        await gallery
            .get('[data-testid="project-detail-page-gallery-slide-1"]')
            .trigger('click');

        await vi.waitFor(
            () => {
                expect(
                    wrapper.find('[data-testid="project-detail-page-gallery-lightbox"]').exists(),
                ).toBe(true);
            },
            { timeout: 5000 },
        );

        const lightbox = wrapper.get('[data-testid="project-detail-page-gallery-lightbox"]');
        expect(lightbox.get('[data-testid="project-detail-page-gallery-lightbox-caption"] h3').text()).toBe(
            'Approval Workflow',
        );
    });

    it('opens the lightbox at the clicked index and closes it via the close button', async () => {
        const wrapper = mountPage();
        await flush();

        await wrapper
            .get('[data-testid="project-detail-page-gallery-slide-1"]')
            .trigger('click');
        await vi.waitFor(
            () => {
                expect(
                    wrapper.find('[data-testid="project-detail-page-gallery-lightbox"]').exists(),
                ).toBe(true);
            },
            { timeout: 5000 },
        );

        const lightbox = wrapper.get('[data-testid="project-detail-page-gallery-lightbox"]');
        expect(lightbox.get('[data-testid="project-detail-page-gallery-lightbox-thumb-1"]').attributes('aria-current')).toBe('true');

        await lightbox.get('[data-testid="project-detail-page-gallery-lightbox-close"]').trigger('click');
        await flush();

        expect(wrapper.find('[data-testid="project-detail-page-gallery-lightbox"]').exists()).toBe(
            false,
        );
    });
});
