import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { getMock, reloadMock, pageRef, pageProps } = vi.hoisted(() => {
    const pageProps = {
        locale: 'en',
        supported_locales: ['en', 'ru'],
    };

    return {
        getMock: vi.fn(),
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
        get: getMock,
        reload: reloadMock,
        on: () => () => {},
    },
    usePage: () => {
        if (pageRef.value === null) {
            pageRef.value = reactive({ props: pageProps, url: '/projects' });
        }

        return pageRef.value;
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import ProjectsIndexPage from '@projects/Public/Pages/ProjectsIndexPage.vue';

const PROJECTS = [
    {
        id: 1,
        title: 'Ledger — Invoice Automation',
        slug: 'ledger',
        brief_description: 'Automated invoice processing pipeline with OCR.',
        published_at: '2026-06-15T00:00:00Z',
        preview_image: 'projects/ledger.png',
        preview_image_alt: { en: 'Ledger dashboard preview', ru: 'Превью Ledger' },
        github_url: 'https://example.com/ledger',
        live_demo_url: null,
        is_featured: false,
        tech_tags: [
            { id: 11, name: 'Laravel', icon: 'devicon:laravel' },
            { id: 12, name: 'Vue 3', icon: 'devicon:vuejs' },
        ],
        screenshots: [
            { image_path: 'projects/ledger-1.png', alt_text: { en: 'Dashboard', ru: 'Дашборд' } },
            { image_path: 'projects/ledger-2.png', alt_text: { en: 'Approvals', ru: 'Согласования' } },
        ],
    },
    {
        id: 2,
        title: 'CLI Toolbox',
        slug: 'cli-toolbox',
        brief_description: 'Collection of small developer CLI tools.',
        published_at: '2025-11-02T00:00:00Z',
        preview_image: null,
        preview_image_alt: { en: '', ru: '' },
        github_url: null,
        live_demo_url: 'https://example.com/demo',
        is_featured: false,
        tech_tags: [{ id: 21, name: 'PHP', icon: 'devicon:php' }],
        screenshots: null,
    },
];

const SKILL_TAGS = [
    { id: 11, name: 'Laravel', icon: 'devicon:laravel' },
    { id: 12, name: 'Vue 3', icon: 'devicon:vuejs' },
];

const PAGE_PROPS = {
    projects: PROJECTS,
    skill_tags: SKILL_TAGS,
    has_more: false,
    next_cursor: null,
    seo: { title: 'Projects — Alex Kovalev', description: 'Projects and case studies.' },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(ProjectsIndexPage, {
        props: { ...PAGE_PROPS, ...props } as unknown as InstanceType<typeof ProjectsIndexPage>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

async function flush(): Promise<void> {
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

describe('ProjectsIndexPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        getMock.mockReset();
        reloadMock.mockClear();
        reloadMock.mockReset();
        window.localStorage.clear();
        document.body.innerHTML = '';
        document.cookie = 'projects_view_mode=;max-age=0';
        window.history.replaceState({}, '', '/projects');
        // The search/tag autocompletes start their debounced fetches
        // during the flush() waits — without a stub they resolve against
        // happy-dom's default origin (http://localhost:3000) and print an
        // ECONNREFUSED AggregateError to the run output. Tests that
        // assert on the fetched suggestions re-stub fetch with their own
        // mock (stubGlobal replaces the previous stub).
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] }), { status: 200 })),
        );
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('renders the layout, the page title and the nav with the Projects link active', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="projects-page"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="projects-page-title"]').text()).toBe('Projects');

        const projectsNav = wrapper.get('[data-testid="public-header-nav-/projects"]');
        expect(projectsNav.classes()).toContain('is-active');
        expect(projectsNav.attributes('aria-current')).toBe('page');
        expect(wrapper.get('[data-testid="public-header-nav-/#about"]').attributes('href')).toBe(
            '/#about',
        );
        expect(wrapper.get('[data-testid="public-header-nav-/experience"]').attributes('href')).toBe(
            '/experience',
        );
        expect(wrapper.get('[data-testid="public-header-nav-/blog"]').attributes('href')).toBe(
            '/blog',
        );
    });

    it('renders the cards with resolved asset URLs and localized alt texts', async () => {
        const wrapper = mountPage();
        await flush();

        const first = wrapper.get('[data-testid="projects-page-card-1"]');
        expect(first.attributes('href')).toBe('/projects/ledger');
        expect(first.get('h3').text()).toBe('Ledger — Invoice Automation');
        expect(first.find('[data-testid="projects-page-card-1-carousel"]').exists()).toBe(true);
        // The wire screenshot alt map resolves to the EN value.
        expect(first.get('[data-testid="projects-page-card-1-carousel"] img').attributes('alt')).toBe(
            'Dashboard',
        );
        expect(first.findAll('a.tech-skill-tag')).toHaveLength(2);

        const second = wrapper.get('[data-testid="projects-page-card-2"]');
        expect(second.find('[data-testid="projects-page-card-2-carousel"]').exists()).toBe(false);
        expect(second.get('[data-testid="projects-page-card-2-demo"]').attributes('href')).toBe(
            'https://example.com/demo',
        );
    });

    it('switches to list mode, writes the view-mode cookie and restores it on mount', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.get('[data-testid="projects-page-grid"]').classes()).toContain(
            'projects-grid--grid',
        );

        await wrapper.get('[data-testid="projects-page-header-view-list"]').trigger('click');
        await flush();

        expect(wrapper.get('[data-testid="projects-page-grid"]').classes()).toContain(
            'projects-grid--list',
        );
        expect(document.cookie).toContain('projects_view_mode=list');
        // The toggle does not reload the list.
        expect(getMock).not.toHaveBeenCalled();
        expect(reloadMock).not.toHaveBeenCalled();

        const remount = mountPage();
        await flush();
        expect(remount.get('[data-testid="projects-page-grid"]').classes()).toContain(
            'projects-grid--list',
        );
    });

    it('collapses and expands the filters with the mobile toggle', async () => {
        const wrapper = mountPage();
        await flush();

        const toggle = wrapper.get('[data-testid="projects-page-header-filters-toggle"]');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(toggle.attributes('aria-label')).toBe('Hide filters');
        expect(toggle.attributes('aria-controls')).toBe('projects-toolbar-filters');

        await toggle.trigger('click');
        await flush();

        expect(wrapper.get('[data-testid="projects-page-header"]').classes()).toContain(
            'projects-toolbar--filters-hidden',
        );
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(toggle.attributes('aria-label')).toBe('Show filters');
        // The collapse is purely visual — no reload is triggered.
        expect(getMock).not.toHaveBeenCalled();
        expect(reloadMock).not.toHaveBeenCalled();

        await toggle.trigger('click');
        await flush();

        expect(wrapper.get('[data-testid="projects-page-header"]').classes()).not.toContain(
            'projects-toolbar--filters-hidden',
        );
    });

    it('submits the search term as a full ?q= load on Enter', async () => {
        const wrapper = mountPage();
        await flush();

        // Fake timers: the autocomplete's debounced fetch fires within
        // the test (against the beforeEach fetch stub) instead of
        // floating past it — an unstubbed 300ms-late fetch resolves
        // against happy-dom's default origin (localhost:3000) and
        // prints an ECONNREFUSED AggregateError to the run output.
        vi.useFakeTimers();
        await wrapper
            .get('[data-testid="projects-page-header-search-input"]')
            .setValue('ocr pipeline');
        await vi.advanceTimersByTimeAsync(300);
        await wrapper
            .get('[data-testid="projects-page-header-search-input"]')
            .trigger('keydown', { key: 'Enter' });
        await flushPromises();
        vi.useRealTimers();
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ q: 'ocr pipeline' });
    });

    it('applies the tag filter through the skill autocomplete with the AND ids', async () => {
        // The endpoint wraps the matches in `{ data: [...] }` — the
        // filter must unwrap it (the 2026-08-16 rework; a bare array
        // response is still tolerated).
        const fetchMock = vi
            .fn()
            .mockResolvedValue(
                new Response(JSON.stringify({ data: [{ id: 12, name: 'Vue 3' }] }), {
                    status: 200,
                }),
            );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage();
        await flush();

        vi.useFakeTimers();
        await wrapper.get('[data-testid="projects-page-header-tags-input"]').setValue('vue');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();

        expect(
            wrapper.findAll('[data-testid^="projects-page-header-tags-suggestion-"]'),
        ).toHaveLength(1);

        await wrapper.get('[data-testid="projects-page-header-tags-suggestion-0"]').trigger('click');
        await flushPromises();
        await vi.advanceTimersByTimeAsync(0);
        await flushPromises();
        vi.useRealTimers();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ tags: '12' });
        expect(fetchMock).toHaveBeenCalledWith('/api/skills/search?q=vue', expect.anything());
    });

    it('closes the tag filter dropdown on a click outside the control', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(JSON.stringify({ data: [{ id: 12, name: 'Vue 3' }] }), {
                    status: 200,
                }),
            ),
        );

        const wrapper = mountPage();
        await flush();

        vi.useFakeTimers();
        await wrapper.get('[data-testid="projects-page-header-tags-input"]').setValue('vue');
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        vi.useRealTimers();

        expect(
            wrapper.find('[data-testid="projects-page-header-tags-dropdown"]').exists(),
        ).toBe(true);

        await wrapper.get('[data-testid="projects-page-title"]').trigger('click');
        await flush();

        expect(
            wrapper.find('[data-testid="projects-page-header-tags-dropdown"]').exists(),
        ).toBe(false);
    });

    it('applies the newest-last sort through the sort dropdown (direction=asc)', async () => {
        const wrapper = mountPage();
        await flush();

        // "Newest first" (date:desc) is the default selection.
        expect(wrapper.get('[data-testid="projects-page-header-sort"]').text()).toContain(
            'Newest first',
        );

        await wrapper.get('[data-testid="projects-page-header-sort"]').trigger('click');
        await flush();
        await wrapper
            .get('[data-testid="projects-page-header-sort-option-date:asc"]')
            .trigger('click');
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ direction: 'asc' });
        expect(
            wrapper
                .find('[data-testid="projects-page-header-sort-option-most-complex"]')
                .exists(),
        ).toBe(false);
    });

    it('loads more with the opaque cursor via a replace-state reload', async () => {
        const wrapper = mountPage({ has_more: true, next_cursor: 'eyJpZCI6MiwidiI6IjIwMjYtMDYtMTUifQ' });
        await flush();

        await wrapper.get('[data-testid="projects-page-load-more-button"]').trigger('click');
        await flush();

        expect(reloadMock).toHaveBeenCalledTimes(1);
        const options = reloadMock.mock.calls[0][0];
        expect(options.data).toEqual({ cursor: 'eyJpZCI6MiwidiI6IjIwMjYtMDYtMTUifQ' });
        expect(options.replace).toBe(true);
    });

    it('renders nothing for the load-more button when the list is exhausted', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="projects-page-load-more"]').exists()).toBe(false);
    });

    it('renders the empty state when no projects exist', async () => {
        const wrapper = mountPage({ projects: [], has_more: false });
        await flush();

        const empty = wrapper.get('[data-testid="projects-page-empty"]');
        expect(empty.text()).toContain('No projects published yet.');
    });

    it('renders the no-results state with Clear filters restoring the unfiltered list', async () => {
        window.history.replaceState({}, '', '/projects?tags=11');

        const wrapper = mountPage({
            projects: [],
            skill_tags: SKILL_TAGS,
            has_more: false,
        });
        await flush();

        const noResults = wrapper.get('[data-testid="projects-page-no-results"]');
        expect(noResults.text()).toContain('No projects match your search.');
        expect(wrapper.find('[data-testid="projects-page-header-tags-pill-0"]').exists()).toBe(true);

        await noResults.get('[data-testid="projects-page-no-results-action"]').trigger('click');
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toBe('/projects');
        expect(getMock.mock.calls[0][1]).toEqual({});
    });

    it('shows the inline error banner on a failed load and retries from the initial batch', async () => {
        reloadMock.mockImplementationOnce((options: { onNetworkError?: (error: Error) => void }) => {
            options.onNetworkError?.(new Error('network down'));
        });

        const wrapper = mountPage({ has_more: true, next_cursor: 'eyJpZCI6MiwidiI6IjIwMjYtMDYtMTUifQ' });
        await flush();

        await wrapper.get('[data-testid="projects-page-load-more-button"]').trigger('click');
        await flush();

        const error = wrapper.get('[data-testid="projects-page-error"]');
        expect(error.text()).toContain('Could not load projects.');
        expect(wrapper.find('[data-testid="projects-page-grid"]').exists()).toBe(false);

        await error.get('[data-testid="projects-page-error-action"]').trigger('click');
        await flush();

        expect(reloadMock).toHaveBeenCalledTimes(2);
        expect(reloadMock.mock.calls[1][0].data).toBeUndefined();
        expect(wrapper.find('[data-testid="projects-page-error"]').exists()).toBe(false);
    });
});
