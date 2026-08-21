import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick } from 'vue';

import {
    blockConfigFor,
    useAnalytics,
    type AnalyticsDashboardProps,
} from '@analytics/Admin/Composables/useAnalytics';

const PROPS: AnalyticsDashboardProps = {
    overview_blocks: [
        { key: 'total_visits', value: 12847 },
        { key: 'cta_clicks', value: 24 },
        { key: 'avg_time_on_page', value: 75, min: 3, max: 240 },
    ],
    blog_blocks: [
        { key: 'total_views', value: 4820 },
        { key: 'completion_rate', value: 87.4, min: 40, max: 100 },
        { key: 'reactions', value: 49 },
    ],
    projects_blocks: [
        { key: 'total_views', value: 9 },
        { key: 'unique_views', value: 5 },
    ],
};

const Host = defineComponent({
    setup() {
        return { analytics: useAnalytics(PROPS) };
    },
    template: '<div />',
});

interface FetchInit {
    signal?: AbortSignal;
}

function jsonResponse(payload: unknown) {
    return { ok: true, json: async () => payload };
}

let wrappers: Array<ReturnType<typeof mount>> = [];

function mountHost() {
    const wrapper = mount(Host);
    wrappers.push(wrapper);

    return wrapper.vm.analytics;
}

describe('useAnalytics', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setActivePinia(createPinia());
        fetchMock = vi.fn().mockResolvedValue(jsonResponse({ points: [] }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        for (const wrapper of wrappers) {
            wrapper.unmount();
        }
        wrappers = [];
        vi.unstubAllGlobals();
    });

    it('derives the per-tab block view models with localized labels and formatted values', () => {
        const analytics = mountHost();

        expect(analytics.overviewBlocks.value[0]).toEqual({
            key: 'total_visits',
            label: 'Total Visits',
            value: '12,847',
            selected: true,
        });
        expect(analytics.overviewBlocks.value[2]).toEqual({
            key: 'avg_time_on_page',
            label: 'Avg Time on Page',
            value: '1m 15s',
            min: '0m 03s',
            max: '4m 00s',
            selected: false,
        });
        expect(analytics.blogBlocks.value[1]).toEqual({
            key: 'completion_rate',
            label: 'Completion Rate',
            value: '87%',
            min: '40%',
            max: '100%',
            selected: false,
        });
        expect(analytics.projectsBlocks.value[1]).toEqual({
            key: 'unique_views',
            label: 'Unique Views',
            value: '5',
            selected: false,
        });
    });

    it('defaults the selected block per tab to the first (default) metric', () => {
        const analytics = mountHost();

        expect(analytics.overviewBlocks.value.find((block) => block.selected)?.key).toBe(
            'total_visits',
        );
        expect(analytics.blogBlocks.value.find((block) => block.selected)?.key).toBe(
            'total_views',
        );
        expect(analytics.projectsBlocks.value.find((block) => block.selected)?.key).toBe(
            'total_views',
        );
    });

    it('selectBlock marks the chosen block selected and deselects the rest', () => {
        const analytics = mountHost();

        analytics.selectBlock('overview', 'cta_clicks');

        expect(analytics.overviewBlocks.value.find((block) => block.selected)?.key).toBe(
            'cta_clicks',
        );
    });

    it('defaults the span and variant state to daily and top', () => {
        const analytics = mountHost();

        expect(analytics.span.value).toBe('day');
        expect(analytics.variant.value).toBe('top');
    });

    it('fetches the metric series at the given span and fills the chart state', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue(
            jsonResponse({ points: [{ label: '2026-08-19', value: 10 }] }),
        );

        const request = analytics.fetchSeries('page_views', 'day');

        expect(analytics.chartLoading.value).toBe(true);
        expect(analytics.chartError.value).toBe(false);

        await request;

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/series?metric=page_views&span=day',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(analytics.chartPoints.value).toEqual([{ label: '2026-08-19', value: 10 }]);
        expect(analytics.chartLoading.value).toBe(false);
    });

    it('appends the dimension parameter and falls back to the current span', async () => {
        const analytics = mountHost();
        analytics.span.value = 'all';

        await analytics.fetchSeries('cta_clicks', undefined, 'language');

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/series?metric=cta_clicks&span=all&dimension=language',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
    });

    it('cancels the previous series request when a new one starts', async () => {
        const analytics = mountHost();
        let firstSignal: AbortSignal | undefined;
        fetchMock.mockImplementation((url: string, init?: FetchInit) => {
            if (url === '/admin/analytics/series?metric=page_views&span=day') {
                firstSignal = init?.signal;

                return new Promise(() => {});
            }

            return Promise.resolve(jsonResponse({ points: [] }));
        });

        void analytics.fetchSeries('page_views', 'day');
        await analytics.fetchSeries('page_views', 'week');

        expect(firstSignal?.aborted).toBe(true);
        expect(analytics.chartLoading.value).toBe(false);
        expect(analytics.chartError.value).toBe(false);
    });

    it('records the error state when a series request fails', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });

        await analytics.fetchSeries('page_views', 'day');

        expect(analytics.chartError.value).toBe(true);
        expect(analytics.chartLoading.value).toBe(false);
        expect(analytics.chartPoints.value).toEqual([]);
    });

    it('fetches one series per CTA target and names each series by its target', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue(
            jsonResponse({ points: [{ label: '2026-08-19', value: 1 }] }),
        );

        await analytics.fetchMultiSeries('cta_clicks', ['github', 'demo']);

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/series?metric=cta_clicks&span=day&dimension=github',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/series?metric=cta_clicks&span=day&dimension=demo',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(analytics.multiChartSeries.value).toEqual([
            { name: 'github', points: [{ label: '2026-08-19', value: 1 }] },
            { name: 'demo', points: [{ label: '2026-08-19', value: 1 }] },
        ]);
        expect(analytics.multiChartLoading.value).toBe(false);
        expect(analytics.multiChartError.value).toBe(false);
    });

    it('cancels the whole previous batch when a new multi-series request starts', async () => {
        const analytics = mountHost();
        let batchSignal: AbortSignal | undefined;
        fetchMock.mockImplementation((url: string, init?: FetchInit) => {
            if (url.includes('dimension=github')) {
                batchSignal = init?.signal;

                return new Promise(() => {});
            }

            return Promise.resolve(jsonResponse({ points: [] }));
        });

        void analytics.fetchMultiSeries('cta_clicks', ['github']);
        await analytics.fetchMultiSeries('cta_clicks', ['demo']);

        expect(batchSignal?.aborted).toBe(true);
    });

    it('records the error state when any multi-series request fails', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });

        await analytics.fetchMultiSeries('cta_clicks', ['github', 'demo']);

        expect(analytics.multiChartError.value).toBe(true);
        expect(analytics.multiChartLoading.value).toBe(false);
    });

    it('fetches the rankings at the given variant and scope', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue(
            jsonResponse({ rows: [{ id: 1, title: 'Ledger', value: 50 }] }),
        );

        await analytics.fetchRankings('views', 'top', 'article');

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/analytics/rankings?metric=views&variant=top&scope=article',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(analytics.rankingRows.value).toEqual([{ id: 1, title: 'Ledger', value: 50 }]);
        expect(analytics.rankingsLoading.value).toBe(false);
        expect(analytics.rankingsError.value).toBe(false);
    });

    it('cancels the previous rankings request and records failures', async () => {
        const analytics = mountHost();
        let firstSignal: AbortSignal | undefined;
        fetchMock.mockImplementation((url: string, init?: FetchInit) => {
            if (url.includes('variant=top')) {
                firstSignal = init?.signal;

                return new Promise(() => {});
            }

            return Promise.resolve({ ok: false, json: async () => ({}) });
        });

        void analytics.fetchRankings('views', 'top', 'article');
        await analytics.fetchRankings('views', 'bottom', 'article');

        expect(firstSignal?.aborted).toBe(true);
        expect(analytics.rankingsError.value).toBe(true);
        expect(analytics.rankingsLoading.value).toBe(false);
    });

    it('aborts every in-flight request when the host component unmounts', async () => {
        const wrapper = mount(Host);
        const signals: Array<AbortSignal | undefined> = [];
        fetchMock.mockImplementation((_url: string, init?: FetchInit) => {
            signals.push(init?.signal);

            return new Promise(() => {});
        });

        wrapper.vm.analytics.fetchSeries('page_views', 'day');
        wrapper.vm.analytics.fetchRankings('views', 'top', 'article');
        await nextTick();

        wrapper.unmount();

        expect(signals).toHaveLength(2);
        expect(signals[0]?.aborted).toBe(true);
        expect(signals[1]?.aborted).toBe(true);
    });

    it('selectBlock aborts every in-flight fetch and clears the detail-area loading refs', async () => {
        const analytics = mountHost();
        const signals: Array<AbortSignal | undefined> = [];
        fetchMock.mockImplementation((_url: string, init?: FetchInit) => {
            signals.push(init?.signal);

            return new Promise(() => {});
        });

        void analytics.fetchSeries('page_views', 'day');
        void analytics.fetchMultiSeries('cta_clicks', ['github']);
        void analytics.fetchRankings('views', 'top', 'article');
        await nextTick();

        analytics.selectBlock('overview', 'total_visits');

        expect(signals).toHaveLength(3);
        expect(signals.every((signal) => signal?.aborted)).toBe(true);
        expect(analytics.chartLoading.value).toBe(false);
        expect(analytics.multiChartLoading.value).toBe(false);
        expect(analytics.rankingsLoading.value).toBe(false);
    });

    it('clears the single-series error state when a multi-series fetch succeeds', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });

        await analytics.fetchSeries('page_views', 'day');
        expect(analytics.chartError.value).toBe(true);

        fetchMock.mockResolvedValue(jsonResponse({ points: [{ label: '2026-08-19', value: 1 }] }));
        await analytics.fetchMultiSeries('cta_clicks', ['github']);

        expect(analytics.chartError.value).toBe(false);
        expect(analytics.multiChartError.value).toBe(false);
        expect(analytics.multiChartSeries.value).toEqual([
            { name: 'github', points: [{ label: '2026-08-19', value: 1 }] },
        ]);
    });

    it('clears the multi-series error state when a single-series fetch succeeds', async () => {
        const analytics = mountHost();
        fetchMock.mockResolvedValue({ ok: false, json: async () => ({}) });

        await analytics.fetchMultiSeries('cta_clicks', ['github']);
        expect(analytics.multiChartError.value).toBe(true);

        fetchMock.mockResolvedValue(jsonResponse({ points: [{ label: '2026-08-19', value: 5 }] }));
        await analytics.fetchSeries('page_views', 'day');

        expect(analytics.multiChartError.value).toBe(false);
        expect(analytics.chartError.value).toBe(false);
    });

    it('does not overwrite the chart points with a response that lands after abort', async () => {
        const analytics = mountHost();
        let resolveFetch: (value: unknown) => void = () => {};
        fetchMock.mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveFetch = resolve;
                }),
        );

        const request = analytics.fetchSeries('page_views', 'day');
        analytics.selectBlock('overview', 'total_visits');
        resolveFetch(jsonResponse({ points: [{ label: '2026-08-20', value: 999 }] }));
        await request;

        expect(analytics.chartPoints.value).toEqual([]);
    });

    it('hard-codes the block-key to endpoint mapping from the OpenAPI AnalyticsBlock schema', () => {
        expect(blockConfigFor('overview', 'total_visits')).toEqual({
            kind: 'chart',
            metric: 'page_views',
        });
        expect(blockConfigFor('overview', 'cta_clicks')).toEqual({
            kind: 'chart-multi',
            metric: 'cta_clicks',
            dimensions: ['github', 'demo', 'cv', 'feedback', 'language', 'theme'],
        });
        expect(blockConfigFor('overview', 'language_switches')).toEqual({
            kind: 'chart',
            metric: 'cta_clicks',
            dimension: 'language',
        });
        expect(blockConfigFor('overview', 'avg_time_on_page')).toEqual({
            kind: 'rankings',
            metric: 'time_on_page_seconds',
            scope: 'page',
        });
        expect(blockConfigFor('blog', 'total_views')).toEqual({
            kind: 'rankings',
            metric: 'views',
            scope: 'article',
        });
        expect(blockConfigFor('blog', 'reactions')).toMatchObject({
            kind: 'rankings',
            metric: 'reactions_likes',
            scope: 'article',
            variants: ['liked', 'disliked'],
            variantMetric: { liked: 'reactions_likes', disliked: 'reactions_dislikes' },
        });
        expect(blockConfigFor('projects', 'unique_views')).toEqual({
            kind: 'rankings',
            metric: 'unique_visitors',
            scope: 'project',
        });
        expect(blockConfigFor('overview', 'unknown_key')).toBeUndefined();
    });
});
