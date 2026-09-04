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
        prefetch: vi.fn(),
        on: () => () => {},
    },
    usePage: () => {
        if (pageRef.value === null) {
            pageRef.value = reactive({ props: pageProps, url: '/blog/vue-composables' });
        }

        return pageRef.value;
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), setPageContext: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import BlogArticlePage from '@blog/Public/Pages/BlogArticlePage.vue';

const CODE_BLOCK =
    '<pre data-code-block data-language="typescript" data-raw="const x = 1"><code><span style="color:#1e7a5b;">const</span> x = 1</code></pre>';

const ARTICLE = {
    id: 7,
    title: 'Building Reusable Vue Composables',
    slug: 'vue-composables',
    content_html: `
        <h2 id="introduction">Introduction</h2>
        <p>Why composables matter.</p>
        <h3 id="setup">Setup</h3>
        <p>How to write one.</p>
        <h4 id="deep-dive">Deep dive</h4>
        <p>h4 must not reach the TOC.</p>
        ${CODE_BLOCK}
        <aside class="callout callout--warning" data-callout-type="warning">
            <div class="callout__title">Watch out</div>
            <div class="callout__content"><p>Timers leak without cleanup.</p></div>
        </aside>
        <h2 id="conclusion">Conclusion</h2>
        <p>Wrap up.</p>`,
    short_description: 'Composable patterns for stateful logic.',
    published_at: '2026-01-15T00:00:00Z',
    reading_time_minutes: 5,
    lead_image: 'blog/vue-composables.png',
    lead_image_alt: { en: 'Code editor', ru: 'Редактор кода' },
    tags: [
        { id: 11, slug: 'vue', name: 'Vue' },
        { id: 12, slug: 'typescript', name: 'TypeScript' },
    ],
    skill_tags: [{ id: 21, name: 'Vue 3', icon: 'devicon:vuejs' }],
    related: [
        {
            id: 2,
            title: 'Cursor-Based Pagination in PostgreSQL',
            slug: 'cursor-pagination',
            short_description: 'Keyset pagination.',
            published_at: '2025-11-02T00:00:00Z',
            reading_time_minutes: 8,
            lead_image: null,
            lead_image_alt: null,
            tags: [{ id: 12, slug: 'postgresql', name: 'PostgreSQL' }],
            skill_tags: [],
        },
    ],
    views_count: 142,
};

const PAGE_PROPS = {
    article: ARTICLE,
    reactions: { user_reaction: 'like', likes: 12, dislikes: 2 },
    has_projects: true,
    seo: { title: 'Building Reusable Vue Composables — Alex Kovalev', description: 'Notes.' },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(BlogArticlePage, {
        props: { ...PAGE_PROPS, ...props } as unknown as InstanceType<typeof BlogArticlePage>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

async function flush(): Promise<void> {
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

describe('BlogArticlePage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        window.localStorage.clear();
        document.body.innerHTML = '';
        window.history.replaceState({}, '', '/blog/vue-composables');
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] }), { status: 200 })),
        );
        // The copy button of the mounted CodeBlock reads the clipboard
        // (a getter-only property in happy-dom — defineProperty).
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText: vi.fn().mockResolvedValue(undefined) },
            configurable: true,
        });
        // The TOC's IntersectionObserver needs a stub in happy-dom.
        vi.stubGlobal('IntersectionObserver', class {
            observe() {}
            disconnect() {}
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('renders the layout, the nav with Blog active and the article content', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.find('[data-testid="blog-article-page"]').exists()).toBe(true);
        const blogNav = wrapper.get('[data-testid="public-header-nav-/blog"]');
        expect(blogNav.classes()).toContain('is-active');
        expect(wrapper.find('[data-testid="public-header-nav-/projects"]').exists()).toBe(true);

        const content = wrapper.get('[data-testid="blog-article-page-content"]');
        expect(content.get('[data-testid="blog-article-page-content-title"]').text()).toBe(
            'Building Reusable Vue Composables',
        );
        expect(content.text()).toContain('Published: Jan 15, 2026');
        expect(content.text()).toContain('5 min read');
        expect(content.get('[data-testid="blog-article-page-content-tag-11"]').text()).toBe('Vue');
        expect(content.get('[data-testid="blog-article-page-content-tag-12"]').text()).toBe(
            'TypeScript',
        );
        const skillChip = content.get('[data-testid="blog-article-page-content-skill-21"]');
        expect(skillChip.attributes('href')).toBe('/blog?skill_tags=21');
        expect(content.text()).toContain('Views: 142');
    });

    it('renders the RSS footer link pointing at the feed', async () => {
        const wrapper = mountPage();
        await flush();

        const link = wrapper.get('[data-testid="footer-rss-link"]');
        expect(link.attributes('href')).toBe('/rss');
        expect(link.text()).toBe('RSS');
    });

    it('extracts h2/h3 headings only into the TOC (h4 silently dropped)', async () => {
        const wrapper = mountPage();
        await flush();

        const toc = wrapper.get('[data-testid="blog-article-page-toc"]');
        const links = toc.findAll('[data-testid^="blog-article-page-toc-link-"]');
        expect(links.map((link) => link.text())).toEqual([
            'Introduction',
            'Setup',
            'Conclusion',
        ]);
        // No h4 ("Deep dive") entry.
        expect(toc.find('[data-testid*="deep-dive"]').exists()).toBe(false);
    });

    it('navigates to the heading on a TOC link click', async () => {
        const scrollIntoView = vi.fn();
        window.HTMLElement.prototype.scrollIntoView = scrollIntoView;

        const wrapper = mountPage();
        await flush();

        await wrapper
            .get('[data-testid="blog-article-page-toc-link-conclusion"]')
            .trigger('click');
        await flush();

        expect(scrollIntoView).toHaveBeenCalledTimes(1);
    });

    it('mounts the CodeBlock over the data-code-block marker with the copy button', async () => {
        const wrapper = mountPage();
        await flush();

        const codeBlock = wrapper.get('[data-testid="blog-article-page-content-code-0"]');
        expect(codeBlock.find('.code-block__language').text()).toBe('typescript');
        expect(codeBlock.find('pre code').text()).toContain('const x = 1');

        await codeBlock.get('[data-testid="blog-article-page-content-code-0-copy"]').trigger('click');
        await flush();

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('const x = 1');
        expect(codeBlock.get('[data-testid="blog-article-page-content-code-0-copy"]').text()).toBe(
            'Copied!',
        );
    });

    it('mounts the CalloutBlock over the data-callout-type marker with the type styling', async () => {
        const wrapper = mountPage();
        await flush();

        const callout = wrapper.get('[data-testid="blog-article-page-content-callout-0"]');
        expect(callout.attributes('data-callout-type')).toBe('warning');
        expect(callout.classes()).toContain('callout--warning');
        // The markdown `> [!WARNING] Watch out` title drives the heading.
        expect(callout.find('.callout__title').text()).toBe('Watch out');
        expect(callout.find('.callout__content').text()).toContain('Timers leak without cleanup.');
    });

    it('renders the reactions widget with the visitor reaction highlighted', async () => {
        const wrapper = mountPage();
        await flush();

        const reactions = wrapper.get('[data-testid="blog-article-page-content-reactions"]');
        const like = reactions.get('[data-testid="blog-article-page-content-reactions-like"]');
        const dislike = reactions.get(
            '[data-testid="blog-article-page-content-reactions-dislike"]',
        );

        expect(like.attributes('aria-pressed')).toBe('true');
        expect(dislike.attributes('aria-pressed')).toBe('false');
        expect(like.text()).toContain('12');
        expect(dislike.text()).toContain('2');
    });

    it('posts a reaction and updates the counts from the response', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(
                new Response(
                    JSON.stringify({
                        data: { reaction: 'dislike', likes: 12, dislikes: 3 },
                    }),
                    { status: 200 },
                ),
            );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage();
        await flush();

        await wrapper
            .get('[data-testid="blog-article-page-content-reactions-dislike"]')
            .trigger('click');
        await flush();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith(
            '/api/blog/7/reaction',
            expect.objectContaining({ method: 'POST' }),
        );
        const body = JSON.parse(String(fetchMock.mock.calls[0][1]?.body));
        expect(body).toEqual({ reaction: 'dislike' });

        const reactions = wrapper.get('[data-testid="blog-article-page-content-reactions"]');
        expect(
            reactions.get('[data-testid="blog-article-page-content-reactions-dislike"]').text(),
        ).toContain('3');
    });

    it('undoes the reaction when the same reaction is clicked again', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(
                new Response(
                    JSON.stringify({ data: { reaction: null, likes: 11, dislikes: 2 } }),
                    { status: 200 },
                ),
            );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage();
        await flush();

        const like = wrapper.get('[data-testid="blog-article-page-content-reactions-like"]');
        expect(like.attributes('aria-pressed')).toBe('true');

        await like.trigger('click');
        await flush();

        const body = JSON.parse(String(fetchMock.mock.calls[0][1]?.body));
        expect(body).toEqual({ reaction: 'like' });
        expect(like.attributes('aria-pressed')).toBe('false');
        expect(like.text()).toContain('11');
    });

    it('hides the reactions widget when the wire sends null (flag off or preview)', async () => {
        const wrapper = mountPage({ reactions: null });
        await flush();

        expect(
            wrapper.find('[data-testid="blog-article-page-content-reactions"]').exists(),
        ).toBe(false);
        // The views stat still renders.
        expect(wrapper.text()).toContain('Views: 142');
    });

    it('renders the related posts as compact cards and hides the section when empty', async () => {
        const wrapper = mountPage();
        await flush();

        const related = wrapper.get('[data-testid="blog-article-page-related"]');
        expect(related.get('h2').text()).toBe('Related Articles');
        const card = related.get('[data-testid="blog-article-page-related-card-2"]');
        expect(card.attributes('href')).toBe('/blog/cursor-pagination');
        // Compact variant: no image.
        expect(card.find('img').exists()).toBe(false);

        const empty = mountPage({ article: { ...ARTICLE, related: [] } });
        await flush();
        expect(empty.find('[data-testid="blog-article-page-related"]').exists()).toBe(false);
    });

    it('keeps the TOC sidebar with an empty message when the article has no headings', async () => {
        const wrapper = mountPage({
            article: { ...ARTICLE, content_html: '<p>No headings here at all.</p>' },
        });
        await flush();

        const toc = wrapper.get('[data-testid="blog-article-page-toc"]');
        expect(toc.get('[data-testid="blog-article-page-toc-empty"]').text()).toBe(
            'No headings in this article.',
        );
        expect(toc.findAll('[data-testid^="blog-article-page-toc-link-"]')).toHaveLength(0);

        // The mobile floating variant shows the same empty message.
        const mobile = wrapper.get('[data-testid="blog-article-page-toc-mobile"]');
        await mobile.get('[data-testid="blog-article-page-toc-mobile-toggle"]').trigger('click');
        await flush();
        expect(mobile.get('[data-testid="blog-article-page-toc-mobile-empty"]').text()).toBe(
            'No headings in this article.',
        );
    });

    it('shows the localized type heading for title-less callouts', async () => {
        const wrapper = mountPage({
            article: {
                ...ARTICLE,
                content_html:
                    '<aside class="callout callout--tip" data-callout-type="tip">' +
                    '<div class="callout__content"><p>Bare tip.</p></div></aside>',
            },
        });
        await flush();

        const callout = wrapper.get('[data-testid="blog-article-page-content-callout-0"]');
        expect(callout.attributes('data-callout-type')).toBe('tip');
        // No markdown title — the localized type label takes over.
        expect(callout.find('.callout__title').text()).toBe('Tip');
        expect(callout.find('.callout__title .callout__title-icon').exists()).toBe(true);
        expect(callout.find('.callout__content').text()).toContain('Bare tip.');
    });

    it('renders the mobile TOC floating button with the same headings', async () => {
        const wrapper = mountPage();
        await flush();

        const mobile = wrapper.get('[data-testid="blog-article-page-toc-mobile"]');
        const toggle = mobile.get('[data-testid="blog-article-page-toc-mobile-toggle"]');
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');
        await flush();

        expect(toggle.attributes('aria-expanded')).toBe('true');
        const mobileLinks = mobile.findAll('[data-testid^="blog-article-page-toc-mobile-link-"]');
        expect(mobileLinks).toHaveLength(3);
        expect(mobileLinks[0].text()).toBe('Introduction');
    });
});
