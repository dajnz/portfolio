import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { reloadMock, pageProps } = vi.hoisted(() => ({
    reloadMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        reload: reloadMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import AnalyticsDashboardPage from '@analytics/Admin/Pages/AnalyticsDashboardPage.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

// Wire fixtures mirroring the OpenAPI AnalyticsDashboardPageProps shape
// (docs/openapi/inertia/analytics.yaml — AnalyticsBlock, AnalyticsDefaultChart,
// AnalyticsDefaultRankings).
const OVERVIEW_BLOCKS = [
    { key: 'total_visits', value: 12847 },
    { key: 'unique_visitors', value: 890 },
    { key: 'cta_clicks', value: 24 },
    { key: 'feedback_submissions', value: 7 },
    { key: 'language_switches', value: 5 },
    { key: 'avg_time_on_page', value: 75, min: 3, max: 240 },
];

const BLOG_BLOCKS = [
    { key: 'total_views', value: 4820 },
    { key: 'unique_visitors', value: 300 },
    { key: 'avg_read_time', value: 60, min: 5, max: 300 },
    { key: 'completion_rate', value: 87.4, min: 40, max: 100 },
    { key: 'reactions', value: 49 },
];

const PROJECTS_BLOCKS = [
    { key: 'total_views', value: 24 },
    { key: 'unique_views', value: 9 },
];

const DEFAULT_CHART = {
    metric: 'page_views',
    spans: {
        day: {
            points: [
                { label: '2026-08-19', value: 100 },
                { label: '2026-08-20', value: 150 },
            ],
        },
        week: { points: [{ label: '2026-08-10', value: 700 }] },
        month: { points: [] },
        all: { points: [] },
    },
};

const DEFAULT_RANKINGS = {
    metric: 'time_on_page_seconds',
    scope: 'page',
    rows: {
        top: [{ page_url: '/about', avg: 75, min: 3, max: 240 }],
        bottom: [],
    },
};

function mountPage(props: Record<string, unknown> = {}) {
    const wrapper = mount(AnalyticsDashboardPage, {
        props: {
            overview_blocks: OVERVIEW_BLOCKS,
            blog_blocks: BLOG_BLOCKS,
            projects_blocks: PROJECTS_BLOCKS,
            default_chart: DEFAULT_CHART,
            default_rankings: DEFAULT_RANKINGS,
            ...props,
        } as unknown as InstanceType<typeof AnalyticsDashboardPage>['$props'],
    });
    mountedWrappers.push(wrapper);

    return wrapper;
}

function jsonResponse(payload: unknown) {
    return { ok: true, json: async () => payload };
}

// The lazy AnalyticsChart (defineAsyncComponent — architecture.md:1250)
// resolves across several microtask/macrotask turns (the first dynamic
// import pays the SFC transform time), so poll instead of sleeping.
async function flushUntil(
    wrapper: ReturnType<typeof mountPage>,
    selector: string,
): Promise<void> {
    const deadline = Date.now() + 10_000;

    while (!wrapper.find(selector).exists()) {
        if (Date.now() > deadline) {
            throw new Error(`Element ${selector} did not render within 10s`);
        }

        await new Promise((resolve) => setTimeout(resolve, 100));
        await flushPromises();
    }
}

interface FetchInit {
    signal?: AbortSignal;
}

let mountedWrappers: Array<ReturnType<typeof mount>> = [];

describe('AnalyticsDashboardPage', () => {
    let toastStore: ReturnType<typeof useToastStore>;
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setActivePinia(createPinia());
        toastStore = useToastStore();
        reloadMock.mockClear();
        pageProps.locale = 'en';
        window.localStorage.clear();
        fetchMock = vi.fn().mockImplementation((url: string) =>
            Promise.resolve(
                url.includes('/rankings')
                    ? jsonResponse({ rows: [] })
                    : jsonResponse({ points: [] }),
            ),
        );
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        for (const wrapper of mountedWrappers) {
            wrapper.unmount();
        }
        mountedWrappers = [];
        vi.unstubAllGlobals();
    });

    it('renders within the admin layout with the title, three tabs and Generate Now', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="analytics-dashboard-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="analytics-dashboard-page"] h1').text()).toBe('Analytics');
        expect(
            wrapper
                .findAll('[data-testid^="analytics-dashboard-page-tab-"]')
                .map((tab) => tab.text()),
        ).toEqual(['Overview', 'Blog', 'Projects']);
        expect(wrapper.get('[data-testid="analytics-dashboard-page-generate-now"]').text()).toBe(
            'Generate Now',
        );
    });

    it('shows the whole-time note on the Overview and Blog tabs but not on Projects', async () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="analytics-dashboard-page-note"]').text()).toBe(
            'Showing whole-time analytics.',
        );

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-testid="analytics-dashboard-page-note"]').text()).toBe(
            'Showing whole-time analytics.',
        );

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-projects"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-testid="analytics-dashboard-page-note"]').exists()).toBe(false);
    });

    it('renders the overview blocks with their whole-time values and the avg min/max line', () => {
        const wrapper = mountPage();

        const blocks = wrapper.findAll('[data-testid^="analytics-dashboard-page-blocks-block-"]');
        expect(blocks).toHaveLength(6);
        expect(blocks[0].text()).toContain('Total Visits');
        expect(blocks[0].text()).toContain('12,847');
        expect(blocks[2].text()).toContain('CTA Clicks');
        expect(blocks[2].text()).toContain('24');

        const avgBlock = wrapper.get(
            '[data-testid="analytics-dashboard-page-blocks-block-avg_time_on_page"]',
        );
        expect(avgBlock.text()).toContain('1m 15s');
        expect(avgBlock.text()).toContain('Min 0m 03s');
        expect(avgBlock.text()).toContain('Max 4m 00s');
    });

    it('renders the default chart (total visits, daily) from the default_chart prop without fetching', async () => {
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        const svg = wrapper.get('[data-testid="analytics-dashboard-page-chart-svg"]');
        expect(svg.attributes('aria-label')).toBe('Bar chart: Visits over time');
        expect(
            svg.findAll('text').map((node) => node.text()),
        ).toEqual(['100', '2026-08-19', '150', '2026-08-20']);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('fetches the series of the clicked chart block', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ points: [{ label: '2026-08-21', value: 10 }] }));
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/series?metric=unique_visitors&span=day',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        const labels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
            .map((node) => node.text());
        expect(labels).toContain('10');
    });

    it('refetches the chart at the chosen span', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ points: [{ label: '2026-08-10', value: 700 }] }));
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper.get('[data-testid="analytics-dashboard-page-span"]').trigger('click');
        await wrapper.get('[data-testid="analytics-dashboard-page-span-option-week"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/series?metric=page_views&span=week',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        const labels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
            .map((node) => node.text());
        expect(labels).toContain('700');
    });

    it('renders the CTA multi-series chart with a legend of the six targets', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ points: [{ label: '2026-08-19', value: 3 }] }));
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-cta_clicks"]')
            .trigger('click');
        await flushPromises();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-legend"]');

        for (const target of ['github', 'demo', 'cv', 'feedback', 'language', 'theme']) {
            expect(fetchMock).toHaveBeenCalledWith(
                `/admin/analytics/series?metric=cta_clicks&span=day&dimension=${target}`,
                expect.objectContaining({ signal: expect.any(AbortSignal) }),
            );
        }

        const legend = wrapper.get('[data-testid="analytics-dashboard-page-chart-legend"]');
        expect(legend.findAll('li').map((item) => item.text())).toEqual([
            'GitHub',
            'Live Demo',
            'CV',
            'Feedback',
            'Language',
            'Theme',
        ]);
        expect(
            wrapper.findAll('[data-testid="analytics-dashboard-page-chart-svg"] polyline'),
        ).toHaveLength(6);
    });

    it('shows the seeded top pages for the time-on-page block and refetches on variant change', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ rows: [{ page_url: '/blog', avg: 45, min: 2, max: 130 }] }),
        );
        const wrapper = mountPage();

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-avg_time_on_page"]')
            .trigger('click');
        await flushPromises();

        // The default top rankings come from the prop — no fetch.
        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(['Page', 'Avg', 'Min', 'Max']);
        expect(wrapper.get('tbody').text()).toContain('/about');
        expect(wrapper.get('tbody').text()).toContain('1m 15s');

        await wrapper.get('[data-testid="analytics-dashboard-page-rankings-variant"]').trigger('click');
        await wrapper
            .get('[data-testid="analytics-dashboard-page-rankings-variant-option-bottom"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/rankings?metric=time_on_page_seconds&variant=bottom&scope=page',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(wrapper.get('tbody').text()).toContain('/blog');
        expect(wrapper.get('tbody').text()).toContain('0m 45s');
    });

    it('cancels the previous series request when another block is clicked', async () => {
        let firstSignal: AbortSignal | undefined;
        fetchMock.mockImplementation((url: string, init?: FetchInit) => {
            if (url.includes('metric=unique_visitors')) {
                firstSignal = init?.signal;

                return new Promise(() => {});
            }

            return Promise.resolve(jsonResponse({ points: [{ label: '2026-08-20', value: 5 }] }));
        });
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-feedback_submissions"]')
            .trigger('click');
        await flushPromises();

        expect(firstSignal?.aborted).toBe(true);
    });

    it('shows the article rankings and the liked/disliked variant for the reactions block', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ rows: [{ id: 1, title: 'Payments Gateway', value: 42 }] }),
        );
        const wrapper = mountPage();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-reactions"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/rankings?metric=reactions_likes&variant=liked&scope=article',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(['Article', 'Likes']);
        expect(wrapper.get('tbody').text()).toContain('Payments Gateway');
        expect(
            wrapper.get('[data-testid="analytics-dashboard-page-rankings-variant"]').text(),
        ).toBe('Most liked');

        await wrapper.get('[data-testid="analytics-dashboard-page-rankings-variant"]').trigger('click');
        await wrapper
            .get('[data-testid="analytics-dashboard-page-rankings-variant-option-disliked"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/rankings?metric=reactions_dislikes&variant=disliked&scope=article',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(['Article', 'Dislikes']);
    });

    it('shows the project rankings on the Projects tab', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ rows: [{ id: 1, title: 'Ledger', value: 24 }] }));
        const wrapper = mountPage();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-projects"]').trigger('click');
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_views"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/rankings?metric=views&variant=top&scope=project',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(['Project', 'Views']);
        expect(wrapper.get('tbody').text()).toContain('Ledger');
    });

    it('shows the chart error state on a failed fetch and refetches on block re-click', async () => {
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-dashboard-page-chart-error"]').text()).toBe(
            'Could not load chart. [Retry]',
        );

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await flushPromises();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('shows the rankings error state with a retry button that refetches', async () => {
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });
        const wrapper = mountPage();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_views"]')
            .trigger('click');
        await flushPromises();

        const errorState = wrapper.get('[data-testid="analytics-dashboard-page-rankings-table-empty"]');
        expect(errorState.text()).toContain('Could not load rankings');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-rankings-table-empty-action"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('shows the empty state on a tab without any blocks', async () => {
        const wrapper = mountPage({ blog_blocks: [] });

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-testid="analytics-dashboard-page-empty"]').text()).toBe(
            'No data available',
        );
    });

    it('re-seeds the default overview chart when returning to the Overview tab', async () => {
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-testid="analytics-dashboard-page-tab-overview"]').trigger('click');
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        const labels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
            .map((node) => node.text());
        expect(labels).toEqual(['100', '2026-08-19', '150', '2026-08-20']);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('renders the CTA multi-series chart after a failed single-series fetch', async () => {
        fetchMock.mockImplementation((url: string) =>
            Promise.resolve(
                url.includes('dimension=')
                    ? jsonResponse({ points: [{ label: '2026-08-19', value: 3 }] })
                    : { ok: false, json: async () => ({}) },
            ),
        );
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-testid="analytics-dashboard-page-chart-error"]').text()).toBe(
            'Could not load chart. [Retry]',
        );

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-cta_clicks"]')
            .trigger('click');
        await flushPromises();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-legend"]');

        expect(wrapper.find('[data-testid="analytics-dashboard-page-chart-error"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.findAll('[data-testid="analytics-dashboard-page-chart-svg"] polyline'),
        ).toHaveLength(6);
    });

    it('aborts the in-flight series fetch when a default block is clicked (no refetch)', async () => {
        let firstSignal: AbortSignal | undefined;
        fetchMock.mockImplementation((url: string, init?: FetchInit) => {
            if (url.includes('metric=unique_visitors')) {
                firstSignal = init?.signal;

                return new Promise(() => {});
            }

            return Promise.resolve(jsonResponse({ points: [] }));
        });
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_visits"]')
            .trigger('click');
        await flushPromises();

        expect(firstSignal?.aborted).toBe(true);
        const labels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
            .map((node) => node.text());
        expect(labels).toEqual(['100', '2026-08-19', '150', '2026-08-20']);
    });

    it('aborts an in-flight rankings fetch on tab switch and keeps the re-seeded rows', async () => {
        let firstSignal: AbortSignal | undefined;
        fetchMock.mockImplementation((url: string, init?: FetchInit) => {
            if (url.includes('metric=views')) {
                firstSignal = init?.signal;

                return new Promise(() => {});
            }

            return Promise.resolve(jsonResponse({ points: [] }));
        });
        const wrapper = mountPage();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_views"]')
            .trigger('click');
        await flushPromises();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-overview"]').trigger('click');
        await flushPromises();

        expect(firstSignal?.aborted).toBe(true);

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-avg_time_on_page"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.get('tbody').text()).toContain('/about');
    });

    it('re-seeds the default chart series when the default block is re-clicked after a completed fetch', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ points: [{ label: '2026-08-21', value: 10 }] }),
        );
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await flushPromises();
        expect(
            wrapper
                .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
                .map((node) => node.text()),
        ).toEqual(['10', '2026-08-21']);

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_visits"]')
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
                .map((node) => node.text()),
        ).toEqual(['100', '2026-08-19', '150', '2026-08-20']);
    });

    it('does not render the default page_views series under the non-default block after a tab round-trip', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ points: [{ label: '2026-08-21', value: 10 }] }),
        );
        const wrapper = mountPage();
        await flushUntil(wrapper, '[data-testid="analytics-dashboard-page-chart-svg"]');

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-unique_visitors"]')
            .trigger('click');
        await flushPromises();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-testid="analytics-dashboard-page-tab-overview"]').trigger('click');
        await flushPromises();

        // The unique-visitors block is still selected — the chart must not
        // show the default page_views series under its title.
        expect(wrapper.find('[data-testid="analytics-dashboard-page-chart-svg"]').exists()).toBe(
            false,
        );
        expect(wrapper.get('[data-testid="analytics-dashboard-page-chart-empty"]').text()).toBe(
            'No data available',
        );

        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_visits"]')
            .trigger('click');
        await flushPromises();

        // The default block shows the DEFAULT series, not the previously
        // fetched unique-visitors one.
        expect(
            wrapper
                .findAll('[data-testid="analytics-dashboard-page-chart-svg"] text')
                .map((node) => node.text()),
        ).toEqual(['100', '2026-08-19', '150', '2026-08-20']);
    });

    it('re-seeds the default time-on-page rows when the default block is re-clicked after another ranking fetch', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ rows: [{ id: 1, title: 'Ledger', value: 24 }] }),
        );
        const wrapper = mountPage();

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-projects"]').trigger('click');
        await flushPromises();
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-total_views"]')
            .trigger('click');
        await flushPromises();
        expect(wrapper.get('tbody').text()).toContain('Ledger');

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-overview"]').trigger('click');
        await flushPromises();
        await wrapper
            .get('[data-testid="analytics-dashboard-page-blocks-block-avg_time_on_page"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.get('tbody').text()).toContain('/about');
        expect(wrapper.get('tbody').text()).toContain('1m 15s');
    });

    it('dispatches report generation and reloads the dashboard on success', async () => {
        fetchMock.mockResolvedValue({ ok: true, json: async () => ({ dispatched: true }) });
        document.cookie = 'XSRF-TOKEN=abc123';

        const wrapper = mountPage();
        await wrapper.get('[data-testid="analytics-dashboard-page-generate-now"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/generate-reports',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': 'abc123',
                }),
            }),
        );
        expect(reloadMock).toHaveBeenCalledTimes(1);
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Reports generation started.',
            variant: 'success',
        });
    });

    it('toasts an error when report generation fails', async () => {
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });

        const wrapper = mountPage();
        await wrapper.get('[data-testid="analytics-dashboard-page-generate-now"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(reloadMock).not.toHaveBeenCalled();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            message: 'Could not start report generation.',
            variant: 'error',
        });
    });
});
