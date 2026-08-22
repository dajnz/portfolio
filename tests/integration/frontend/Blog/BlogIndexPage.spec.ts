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
        prefetch: vi.fn(),
        on: () => () => {},
    },
    usePage: () => {
        if (pageRef.value === null) {
            pageRef.value = reactive({ props: pageProps, url: '/blog' });
        }

        return pageRef.value;
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), setPageContext: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import BlogIndexPage from '@blog/Public/Pages/BlogIndexPage.vue';

const ARTICLES = [
    {
        id: 1,
        title: 'Building Reusable Vue Composables',
        slug: 'vue-composables',
        short_description:
            '<p>Composable patterns for stateful logic.</p>',
        published_at: '2026-01-15T00:00:00Z',
        reading_time_minutes: 5,
        lead_image: 'blog/vue-composables.png',
        lead_image_alt: { en: 'Code editor with a composable', ru: 'Редактор кода с композаблом' },
        tags: [{ id: 11, slug: 'vue', name: 'Vue' }],
        skill_tags: [{ id: 21, name: 'Vue 3', icon: 'devicon:vuejs' }],
    },
    {
        id: 2,
        title: 'Cursor-Based Pagination in PostgreSQL',
        slug: 'cursor-pagination',
        short_description: 'Keyset pagination with composite cursors.',
        published_at: '2025-11-02T00:00:00Z',
        reading_time_minutes: 8,
        lead_image: null,
        lead_image_alt: null,
        tags: [{ id: 12, slug: 'postgresql', name: 'PostgreSQL' }],
        skill_tags: [],
    },
];

const TAGS = [
    { id: 11, slug: 'vue', name: 'Vue' },
    { id: 12, slug: 'postgresql', name: 'PostgreSQL' },
];

const PAGE_PROPS = {
    articles: ARTICLES,
    tags: TAGS,
    years: [2026, 2025],
    has_more: false,
    next_cursor: null,
    has_projects: true,
    seo: { title: 'Blog — Alex Kovalev', description: 'Articles and notes.' },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(BlogIndexPage, {
        props: { ...PAGE_PROPS, ...props } as unknown as InstanceType<typeof BlogIndexPage>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

async function flush(): Promise<void> {
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

/**
 * The unified endpoint returns matches with the `type` discriminator —
 * article tags and skills share the pill state but travel in separate
 * query parameters (content-management.md:198-200).
 */
function stubUnifiedTagSearch(): ReturnType<typeof vi.fn> {
    const fetchMock = vi.fn().mockResolvedValue(
        new Response(
            JSON.stringify({
                data: [
                    { id: 12, name: 'PostgreSQL', type: 'tag' },
                    { id: 21, name: 'Vue 3', type: 'skill' },
                ],
            }),
            { status: 200 },
        ),
    );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

/**
 * Types the tag-filter query and opens the suggestion dropdown — the
 * debounced fetch fired with `advanceTimersByTimeAsync`. The caller
 * owns the fake-timer window (armed before, restored after).
 */
async function openTagSuggestions(
    wrapper: ReturnType<typeof mountPage>,
    query = 'e',
): Promise<void> {
    await wrapper.get('[data-testid="blog-page-header-tags-input"]').setValue(query);
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();
}

/**
 * Clicks a suggestion row inside the armed fake-timer window. The
 * click must happen under fake timers: test-utils' `trigger` sets
 * `event._vts = Date.now() + 1` (its dedup workaround for Vue's event
 * dedup) — after a fake→real timer cycle the trigger silently no-ops
 * in this environment, while inside the window it works.
 */
async function selectSuggestion(
    wrapper: ReturnType<typeof mountPage>,
    index = 0,
): Promise<void> {
    await wrapper
        .get(`[data-testid="blog-page-header-tags-suggestion-${index}"]`)
        .trigger('click');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(0);
    await flushPromises();
}

describe('BlogIndexPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        getMock.mockReset();
        reloadMock.mockClear();
        reloadMock.mockReset();
        window.localStorage.clear();
        document.body.innerHTML = '';
        document.cookie = 'blog_view_mode=;path=/;max-age=0';
        window.history.replaceState({}, '', '/blog');
        // The search/tag autocompletes start their debounced fetches
        // during the flush() waits — without a stub they resolve against
        // happy-dom's default origin (http://localhost:3000) and print an
        // ECONNREFUSED AggregateError to the run output.
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] }), { status: 200 })),
        );
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('renders the layout, the page title and the nav with the Blog link active', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="blog-page"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="blog-page-title"]').text()).toBe('Blog');

        const blogNav = wrapper.get('[data-testid="public-header-nav-/blog"]');
        expect(blogNav.classes()).toContain('is-active');
        expect(blogNav.attributes('aria-current')).toBe('page');
        // A10: the Projects link renders only when a published project
        // exists (the `has_projects` signal, B268).
        expect(wrapper.find('[data-testid="public-header-nav-/projects"]').exists()).toBe(true);
    });

    it('hides the Projects nav link when no published project exists', async () => {
        const wrapper = mountPage({ has_projects: false });
        await flush();

        expect(wrapper.find('[data-testid="public-header-nav-/projects"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="public-header-nav-/blog"]').exists()).toBe(true);
    });

    it('renders the cards with resolved asset URLs, alt texts, badges and linked skill chips', async () => {
        const wrapper = mountPage();
        await flush();

        const first = wrapper.get('[data-testid="blog-page-card-1"]');
        expect(first.attributes('href')).toBe('/blog/vue-composables');
        expect(first.get('h3').text()).toBe('Building Reusable Vue Composables');
        expect(first.get('img').attributes('src')).toBe('/assets/blog/vue-composables.png');
        expect(first.get('img').attributes('alt')).toBe('Code editor with a composable');
        expect(first.get('[data-testid="blog-page-card-1-tag-11"]').text()).toBe('Vue');
        // The skill chip is a linked TechSkillTag to the skill filter.
        const skillChip = first.get('[data-testid="blog-page-card-1-skill-21"]');
        expect(skillChip.attributes('href')).toBe('/blog?skill_tags=21');
        // The meta row: the localized date + the reading time.
        expect(first.text()).toContain('Jan 15, 2026');
        expect(first.text()).toContain('5 min read');

        // The image-less card gets the placeholder and renders no img.
        const second = wrapper.get('[data-testid="blog-page-card-2"]');
        expect(second.find('img').exists()).toBe(false);
        expect(second.find('[data-testid="blog-page-card-2-placeholder"]').exists()).toBe(true);
    });

    it('renders the FTS <mark> snippet in the card description', async () => {
        const wrapper = mountPage({
            articles: [
                {
                    ...ARTICLES[0],
                    short_description:
                        'Text before <mark>composable</mark> patterns after.',
                },
            ],
        });
        await flush();

        const first = wrapper.get('[data-testid="blog-page-card-1"]');
        const mark = first.get('p mark');
        expect(mark.text()).toBe('composable');
        // The snippet HTML is the only markup allowed — the rest renders
        // as plain text.
        expect(first.get('p').text()).toContain('Text before composable patterns after.');
    });

    it('switches to list mode, writes the view-mode cookie and restores it on mount', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.get('[data-testid="blog-page-grid"]').classes()).toContain('blog-grid--grid');

        await wrapper.get('[data-testid="blog-page-header-view-list"]').trigger('click');
        await flush();

        expect(wrapper.get('[data-testid="blog-page-grid"]').classes()).toContain('blog-grid--list');
        expect(document.cookie).toContain('blog_view_mode=list');
        // The toggle does not reload the list.
        expect(getMock).not.toHaveBeenCalled();
        expect(reloadMock).not.toHaveBeenCalled();

        const remount = mountPage();
        await flush();
        expect(remount.get('[data-testid="blog-page-grid"]').classes()).toContain('blog-grid--list');
    });

    it('renders the year filter only when more than one year of articles exists', async () => {
        const wrapper = mountPage({ years: [2026, 2025] });
        await flush();
        expect(wrapper.find('[data-testid="blog-page-header-year"]').exists()).toBe(true);

        const single = mountPage({ years: [2026] });
        await flush();
        expect(single.find('[data-testid="blog-page-header-year"]').exists()).toBe(false);
    });

    it('filters by year through the dropdown and resets to All', async () => {
        const wrapper = mountPage();
        await flush();

        // The "All" option is the first entry of the dropdown.
        expect(wrapper.get('[data-testid="blog-page-header-year"]').text()).toContain('All');

        await wrapper.get('[data-testid="blog-page-header-year"]').trigger('click');
        await flush();
        await wrapper.get('[data-testid="blog-page-header-year-option-2025"]').trigger('click');
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ year: '2025' });

        await wrapper.get('[data-testid="blog-page-header-year"]').trigger('click');
        await flush();
        await wrapper.get('[data-testid="blog-page-header-year-option-"]').trigger('click');
        await flush();

        expect(getMock).toHaveBeenCalledTimes(2);
        expect(getMock.mock.calls[1][1]).toEqual({});
    });

    it('submits the search term as a full ?q= load on Enter', async () => {
        const wrapper = mountPage();
        await flush();

        vi.useFakeTimers();
        await wrapper
            .get('[data-testid="blog-page-header-search-input"]')
            .setValue('composable');
        await vi.advanceTimersByTimeAsync(300);
        await wrapper
            .get('[data-testid="blog-page-header-search-input"]')
            .trigger('keydown', { key: 'Enter' });
        await flushPromises();
        vi.useRealTimers();
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ q: 'composable' });
    });

    it('shows the unified tag suggestions with their type discriminator', async () => {
        const fetchMock = stubUnifiedTagSearch();
        const wrapper = mountPage();
        await flush();

        vi.useFakeTimers();
        try {
            await openTagSuggestions(wrapper);

            expect(
                wrapper.findAll('[data-testid^="blog-page-header-tags-suggestion-"]'),
            ).toHaveLength(2);
        } finally {
            vi.useRealTimers();
        }

        expect(fetchMock).toHaveBeenCalledWith('/api/blog-tags/search?q=e', expect.anything());
    });

    it('sends an article-tag selection as ?article_tags=', async () => {
        stubUnifiedTagSearch();
        const wrapper = mountPage();
        await flush();

        vi.useFakeTimers();
        try {
            await openTagSuggestions(wrapper);
            await selectSuggestion(wrapper);
        } finally {
            vi.useRealTimers();
        }
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ article_tags: '12' });
    });

    it('splits the skill selection into ?skill_tags= next to the article tags', async () => {
        stubUnifiedTagSearch();
        const wrapper = mountPage();
        await flush();

        vi.useFakeTimers();
        try {
            await openTagSuggestions(wrapper);
            await selectSuggestion(wrapper);
            // Selecting a suggestion keeps the dropdown open and clears
            // the query — the already-selected item disappears from the
            // list, so the second click lands on suggestion-0 again.
            await selectSuggestion(wrapper);
        } finally {
            vi.useRealTimers();
        }
        await flush();

        expect(getMock).toHaveBeenCalledTimes(2);
        expect(getMock.mock.calls[1][1]).toEqual({ article_tags: '12', skill_tags: '21' });
    });

    it('removes a selected pill and reloads without it', async () => {
        window.history.replaceState({}, '', '/blog?article_tags=11&skill_tags=21');
        const wrapper = mountPage({
            articles: [
                {
                    ...ARTICLES[0],
                    skill_tags: [{ id: 21, name: 'Vue 3', icon: 'devicon:vuejs' }],
                },
            ],
        });
        await flush();

        // Both pills hydrate: the article tag from the `tags` prop, the
        // skill pill from the batch card's skill_tags (B269).
        const articlePill = wrapper.get('[data-testid="blog-page-header-tags-pill-0"]');
        expect(articlePill.text()).toContain('Vue');
        const skillPill = wrapper.get('[data-testid="blog-page-header-tags-pill-1"]');
        expect(skillPill.text()).toContain('Vue 3');

        await articlePill.get('[data-testid="blog-page-header-tags-pill-0-remove"]').trigger('click');
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][1]).toEqual({ skill_tags: '21' });
    });

    it('loads more with the opaque cursor via a replace-state reload', async () => {
        const wrapper = mountPage({ has_more: true, next_cursor: 'eyJpZCI6MiwidiI6IjIwMjYtMDYtMTUifQ' });
        await flush();

        await wrapper.get('[data-testid="blog-page-load-more-button"]').trigger('click');
        await flush();

        expect(reloadMock).toHaveBeenCalledTimes(1);
        const options = reloadMock.mock.calls[0][0];
        expect(options.data).toEqual({ cursor: 'eyJpZCI6MiwidiI6IjIwMjYtMDYtMTUifQ' });
        expect(options.replace).toBe(true);
    });

    it('renders nothing for the load-more button when the list is exhausted', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="blog-page-load-more"]').exists()).toBe(false);
    });

    it('renders the empty state when no articles exist', async () => {
        const wrapper = mountPage({ articles: [], has_more: false });
        await flush();

        const empty = wrapper.get('[data-testid="blog-page-empty"]');
        expect(empty.text()).toContain('No articles yet');
    });

    it('renders the no-results state with Clear filters restoring the unfiltered list', async () => {
        window.history.replaceState({}, '', '/blog?year=2025&article_tags=11');
        const wrapper = mountPage({ articles: [], has_more: false });
        await flush();

        const noResults = wrapper.get('[data-testid="blog-page-no-results"]');
        expect(noResults.text()).toContain('No articles match your search.');

        await noResults.get('[data-testid="blog-page-no-results-action"]').trigger('click');
        await flush();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toBe('/blog');
        expect(getMock.mock.calls[0][1]).toEqual({});
    });

    it('caps the tags at 3 on grid cards and 5 on list cards with the +N chip', async () => {
        const TAG = (id: number, name: string) => ({ id, slug: name, name });
        const SKILL = (id: number, name: string) => ({ id, name, icon: `devicon:${name}` });

        const wrapper = mountPage({
            articles: [
                {
                    ...ARTICLES[0],
                    tags: [1, 2, 3, 4, 5].map((id) => TAG(id, `Tag ${id}`)),
                    skill_tags: [6, 7, 8].map((id) => SKILL(id, `Skill ${id}`)),
                },
            ],
        });
        await flush();

        const gridCard = wrapper.get('[data-testid="blog-page-card-1"]');
        expect(gridCard.findAll('[data-testid^="blog-page-card-1-tag-"]')).toHaveLength(3);
        expect(gridCard.findAll('[data-testid^="blog-page-card-1-skill-"]')).toHaveLength(0);
        const overflow = gridCard.get('[data-testid="blog-page-card-1-tags-overflow"]');
        expect(overflow.text()).toBe('+5');

        // List mode raises the cap to 5 (the combined article + skill
        // list, in the mockup's order).
        await wrapper.get('[data-testid="blog-page-header-view-list"]').trigger('click');
        await flush();

        const listCard = wrapper.get('[data-testid="blog-page-card-1"]');
        expect(listCard.findAll('[data-testid^="blog-page-card-1-tag-"]')).toHaveLength(5);
        expect(listCard.findAll('[data-testid^="blog-page-card-1-skill-"]')).toHaveLength(0);
        expect(listCard.get('[data-testid="blog-page-card-1-tags-overflow"]').text()).toBe('+3');
    });

    it('renders the card meta as a two-column subtle caption row', async () => {
        const wrapper = mountPage();
        await flush();

        const meta = wrapper.get('[data-testid="blog-page-card-1"] .article-card__meta');
        expect(meta.classes()).toContain('article-card__meta');
        expect(meta.get('.article-card__meta-date').text()).toBe('Jan 15, 2026');
        expect(meta.get('.article-card__meta-read').text()).toBe('5 min read');
    });

    it('collapses and expands the filters with the mobile toggle', async () => {
        const wrapper = mountPage();
        await flush();

        const toggle = wrapper.get('[data-testid="blog-page-header-filters-toggle"]');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(toggle.attributes('aria-label')).toBe('Hide filters');
        expect(toggle.attributes('aria-controls')).toBe('blog-toolbar-filters');

        await toggle.trigger('click');
        await flush();

        expect(wrapper.get('[data-testid="blog-page-header"]').classes()).toContain(
            'blog-toolbar--filters-hidden',
        );
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(toggle.attributes('aria-label')).toBe('Show filters');
        // The collapse is purely visual — no reload is triggered.
        expect(getMock).not.toHaveBeenCalled();
        expect(reloadMock).not.toHaveBeenCalled();

        await toggle.trigger('click');
        await flush();

        expect(wrapper.get('[data-testid="blog-page-header"]').classes()).not.toContain(
            'blog-toolbar--filters-hidden',
        );
    });

    it('shows the inline error banner on a failed load and retries from the initial batch', async () => {
        reloadMock.mockImplementationOnce((options: { onNetworkError?: (error: Error) => void }) => {
            options.onNetworkError?.(new Error('network down'));
        });

        const wrapper = mountPage({ has_more: true, next_cursor: 'eyJpZCI6MiwidiI6IjIwMjYtMDYtMTUifQ' });
        await flush();

        await wrapper.get('[data-testid="blog-page-load-more-button"]').trigger('click');
        await flush();

        const error = wrapper.get('[data-testid="blog-page-error"]');
        expect(error.text()).toContain('Could not load articles.');
        expect(wrapper.find('[data-testid="blog-page-grid"]').exists()).toBe(false);

        await error.get('[data-testid="blog-page-error-action"]').trigger('click');
        await flush();

        expect(reloadMock).toHaveBeenCalledTimes(2);
        expect(reloadMock.mock.calls[1][0].data).toBeUndefined();
        expect(wrapper.find('[data-testid="blog-page-error"]').exists()).toBe(false);
    });
});
