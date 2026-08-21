import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed, onUnmounted, type Ref, reactive, ref } from 'vue';

/**
 * Analytics dashboard data wiring (admin-section-components.md:1295-1334,
 * admin-section-ui.md:441-452, analytics.md:221-232) — maps the Inertia
 * props (the whole-time metric blocks per tab, typed
 * `AnalyticsBlock`) into per-tab clickable block view models and owns the
 * chart/rankings fetch state for the JSON endpoints (`series` /
 * `rankings`, openapi/inertia/analytics.yaml). Lives in the Analytics
 * feature (not the Shared/CrossSurface tracker — `useAnalytics` there
 * owns public-site event tracking, B74).
 *
 * The props are read through the props object (reactive — `useLandingPage`
 * precedent) so a later Inertia visit with fresh blocks re-computes the
 * views. The block-key → endpoint mapping is hard-coded here per the
 * OpenAPI `AnalyticsBlock` schema description (11.3.4 decision): the page
 * looks a clicked block up via `blockConfigFor(tab, key)` and calls the
 * matching fetch helper (`fetchSeries` for chart metrics — the CTA clicks
 * block uses `fetchMultiSeries` for its six per-target series —
 * `fetchRankings` for the ranking metrics). Every fetch family aborts the
 * previous request of its kind via AbortController (the last request
 * wins), exposes its own loading/error refs and ignores aborted
 * responses. Selecting a block (or aborting via `abortFetches`) cancels
 * every in-flight request and clears the detail-area error/loading refs
 * so a stale response can never overwrite state seeded later — even when
 * the new selection fetches nothing (default-seed clicks, tab switches).
 */
export type AnalyticsTab = 'overview' | 'blog' | 'projects';
export type AnalyticsSpan = 'day' | 'week' | 'month' | 'all';
export type AnalyticsVariant = 'top' | 'bottom' | 'liked' | 'disliked';
export type AnalyticsScope = 'page' | 'article' | 'project';
export type BlockKind = 'chart' | 'chart-multi' | 'rankings';

export type SeriesPoint = components['schemas']['AnalyticsSeriesPoint'];
export type ChartSeries = { name: string; points: Array<SeriesPoint> };
export type RankingRow = components['schemas']['AnalyticsRankingResponse']['rows'][number];
export type AnalyticsSeriesResponse = components['schemas']['AnalyticsSeriesResponse'];
export type AnalyticsRankingResponse = components['schemas']['AnalyticsRankingResponse'];

/** The whole-time block view model fed to `AnalyticsMetricBlocks`. */
export interface BlockView {
    key: string;
    label: string;
    /** Locale-formatted value (durations/percent for the avg blocks). */
    value: string;
    min?: string;
    max?: string;
    selected: boolean;
}

/** What a block click fetches (openapi/inertia/analytics.yaml — the
 *  `AnalyticsBlock` schema table). */
export interface BlockConfig {
    kind: BlockKind;
    metric: string;
    /** Single `cta_clicks` target (the language-switches block). */
    dimension?: string;
    /** The six CTA targets (the multi-series clicks block). */
    dimensions?: Array<string>;
    scope?: AnalyticsScope;
    /** The ranking variants a block accepts (reactions only). */
    variants?: Array<AnalyticsVariant>;
    /** The metric to pair with a variant — reactions: liked →
     *  `reactions_likes`, disliked → `reactions_dislikes`. */
    variantMetric?: Partial<Record<AnalyticsVariant, string>>;
}

/** The page's own prop slice (mirrors `useLandingPage(props)` — the
 *  composable receives the props object, not a snapshot). */
export type AnalyticsDashboardProps = Pick<
    NonNullable<components['schemas']['AnalyticsDashboardPageProps']['props']>,
    'overview_blocks' | 'blog_blocks' | 'projects_blocks'
>;

export interface UseAnalyticsApi {
    /** Overview blocks (total visits, ..., avg time on page). */
    overviewBlocks: ComputedRef<Array<BlockView>>;
    /** Blog blocks (total views, ..., reactions). */
    blogBlocks: ComputedRef<Array<BlockView>>;
    /** Projects blocks (total views, unique views). */
    projectsBlocks: ComputedRef<Array<BlockView>>;
    /** Marks the block of the given tab selected (deselects the rest).
     *  Cancels every in-flight chart/rankings fetch and clears the
     *  detail-area error/loading refs — a block click starts the detail
     *  area fresh, so a stale response can never overwrite the state
     *  seeded for the new selection (default-seed clicks fetch nothing). */
    selectBlock: (tab: AnalyticsTab, key: string) => void;
    /** Cancels every in-flight chart/rankings fetch and clears the
     *  detail-area loading refs (tab re-seeding, unmount). */
    abortFetches: () => void;
    /** The active chart span (the span dropdown v-model, default daily). */
    span: Ref<AnalyticsSpan>;
    /** The active rankings variant (the variant dropdown v-model,
     *  default top). */
    variant: Ref<AnalyticsVariant>;
    chartPoints: Ref<Array<SeriesPoint>>;
    chartLoading: Ref<boolean>;
    chartError: Ref<boolean>;
    /** The CTA multi-chart series (one entry per requested target). */
    multiChartSeries: Ref<Array<ChartSeries>>;
    multiChartLoading: Ref<boolean>;
    multiChartError: Ref<boolean>;
    rankingRows: Ref<Array<RankingRow>>;
    rankingsLoading: Ref<boolean>;
    rankingsError: Ref<boolean>;
    /** Fetches the chart points of a metric at a span (cancels the
     *  previous series request; `span` defaults to the current state). */
    fetchSeries: (
        metric: string,
        requestedSpan?: AnalyticsSpan,
        dimension?: string,
    ) => Promise<void>;
    /** Fetches one series per dimension — the CTA multi-chart (one batch
     *  abort signal for all six requests). */
    fetchMultiSeries: (
        metric: string,
        dimensions: Array<string>,
        requestedSpan?: AnalyticsSpan,
    ) => Promise<void>;
    /** Fetches the top/bottom/liked/disliked rows of a metric (cancels
     *  the previous rankings request; `variant` defaults to the current
     *  state). */
    fetchRankings: (
        metric: string,
        requestedVariant?: AnalyticsVariant,
        scope?: AnalyticsScope,
    ) => Promise<void>;
    /** Locale-formatted number ("12,847"). */
    formatNumber: (value: number) => string;
    /** Seconds → "1m 05s" (avg time on page / read time). */
    formatDuration: (seconds: number | null) => string;
    /** Percent points → "87%" (completion rate). */
    formatPercent: (value: number) => string;
}

export const CTA_TARGETS = ['github', 'demo', 'cv', 'feedback', 'language', 'theme'] as const;

const BLOCK_PROP_KEYS: Record<AnalyticsTab, keyof AnalyticsDashboardProps> = {
    overview: 'overview_blocks',
    blog: 'blog_blocks',
    projects: 'projects_blocks',
};

/** The hard-coded block-key → endpoint map (openapi/inertia/analytics.yaml
 *  — the `AnalyticsBlock` schema description table, 11.3.4). */
const BLOCK_CONFIGS: Record<AnalyticsTab, Record<string, BlockConfig>> = {
    overview: {
        total_visits: { kind: 'chart', metric: 'page_views' },
        unique_visitors: { kind: 'chart', metric: 'unique_visitors' },
        cta_clicks: { kind: 'chart-multi', metric: 'cta_clicks', dimensions: [...CTA_TARGETS] },
        feedback_submissions: { kind: 'chart', metric: 'feedback_submissions' },
        language_switches: { kind: 'chart', metric: 'cta_clicks', dimension: 'language' },
        avg_time_on_page: { kind: 'rankings', metric: 'time_on_page_seconds', scope: 'page' },
    },
    blog: {
        total_views: { kind: 'rankings', metric: 'views', scope: 'article' },
        unique_visitors: { kind: 'rankings', metric: 'unique_visitors', scope: 'article' },
        avg_read_time: { kind: 'rankings', metric: 'read_time_seconds', scope: 'article' },
        completion_rate: { kind: 'rankings', metric: 'completion_rate_sum', scope: 'article' },
        reactions: {
            kind: 'rankings',
            metric: 'reactions_likes',
            scope: 'article',
            variants: ['liked', 'disliked'],
            variantMetric: { liked: 'reactions_likes', disliked: 'reactions_dislikes' },
        },
    },
    projects: {
        total_views: { kind: 'rankings', metric: 'views', scope: 'project' },
        unique_views: { kind: 'rankings', metric: 'unique_visitors', scope: 'project' },
    },
};

export function blockConfigFor(tab: AnalyticsTab, key: string): BlockConfig | undefined {
    return BLOCK_CONFIGS[tab][key];
}

const DEFAULT_SELECTED: Record<AnalyticsTab, string> = {
    overview: 'total_visits',
    blog: 'total_views',
    projects: 'total_views',
};

export function useAnalytics(props: AnalyticsDashboardProps): UseAnalyticsApi {
    const { t, locale } = useI18n();
    const { get } = useJsonApi();

    const selectedKeys = reactive<Record<AnalyticsTab, string>>({ ...DEFAULT_SELECTED });
    const span = ref<AnalyticsSpan>('day');
    const variant = ref<AnalyticsVariant>('top');

    const chartPoints = ref<Array<SeriesPoint>>([]);
    const chartLoading = ref(false);
    const chartError = ref(false);

    const multiChartSeries = ref<Array<ChartSeries>>([]);
    const multiChartLoading = ref(false);
    const multiChartError = ref(false);

    const rankingRows = ref<Array<RankingRow>>([]);
    const rankingsLoading = ref(false);
    const rankingsError = ref(false);

    let seriesController: AbortController | null = null;
    let multiSeriesController: AbortController | null = null;
    let rankingsController: AbortController | null = null;

    function abortFetches(): void {
        seriesController?.abort();
        multiSeriesController?.abort();
        rankingsController?.abort();
        chartLoading.value = false;
        multiChartLoading.value = false;
        rankingsLoading.value = false;
    }

    onUnmounted(abortFetches);

    function selectBlock(tab: AnalyticsTab, key: string): void {
        abortFetches();
        chartError.value = false;
        multiChartError.value = false;
        rankingsError.value = false;
        selectedKeys[tab] = key;
    }

    function blockViews(tab: AnalyticsTab): Array<BlockView> {
        const blocks = props[BLOCK_PROP_KEYS[tab]] ?? [];

        return blocks.map((block) => ({
            key: block.key,
            label: t(`admin.analytics.${block.key}`),
            value: formatBlockValue(block.key, block.value),
            min: block.min === undefined ? undefined : formatBlockValue(block.key, block.min),
            max: block.max === undefined ? undefined : formatBlockValue(block.key, block.max),
            selected: block.key === selectedKeys[tab],
        }));
    }

    const overviewBlocks = computed<Array<BlockView>>(() => blockViews('overview'));
    const blogBlocks = computed<Array<BlockView>>(() => blockViews('blog'));
    const projectsBlocks = computed<Array<BlockView>>(() => blockViews('projects'));

    function seriesUrl(metric: string, requestedSpan: AnalyticsSpan, dimension?: string): string {
        const params = new URLSearchParams({ metric, span: requestedSpan });

        if (dimension !== undefined) {
            params.set('dimension', dimension);
        }

        return `/admin/analytics/series?${params.toString()}`;
    }

    async function fetchSeries(
        metric: string,
        requestedSpan: AnalyticsSpan = span.value,
        dimension?: string,
    ): Promise<void> {
        const controller = new AbortController();
        seriesController?.abort();
        seriesController = controller;
        chartLoading.value = true;
        chartError.value = false;
        multiChartError.value = false;

        try {
            const response = await get<AnalyticsSeriesResponse>(
                seriesUrl(metric, requestedSpan, dimension),
                { signal: controller.signal },
            );

            if (controller.signal.aborted) {
                return;
            }

            chartPoints.value = response.points;
        } catch {
            if (controller.signal.aborted) {
                return;
            }
            chartError.value = true;
        } finally {
            if (!controller.signal.aborted) {
                chartLoading.value = false;
            }
        }
    }

    async function fetchMultiSeries(
        metric: string,
        dimensions: Array<string>,
        requestedSpan: AnalyticsSpan = span.value,
    ): Promise<void> {
        const controller = new AbortController();
        multiSeriesController?.abort();
        multiSeriesController = controller;
        multiChartLoading.value = true;
        chartError.value = false;
        multiChartError.value = false;

        try {
            const responses = await Promise.all(
                dimensions.map((dimension) =>
                    get<AnalyticsSeriesResponse>(seriesUrl(metric, requestedSpan, dimension), {
                        signal: controller.signal,
                    }),
                ),
            );

            if (controller.signal.aborted) {
                return;
            }

            multiChartSeries.value = dimensions.map((dimension, index) => ({
                name: dimension,
                points: responses[index].points,
            }));
        } catch {
            if (controller.signal.aborted) {
                return;
            }
            multiChartError.value = true;
        } finally {
            if (!controller.signal.aborted) {
                multiChartLoading.value = false;
            }
        }
    }

    function rankingsUrl(
        metric: string,
        requestedVariant: AnalyticsVariant,
        scope?: AnalyticsScope,
    ): string {
        const params = new URLSearchParams({ metric, variant: requestedVariant });

        if (scope !== undefined) {
            params.set('scope', scope);
        }

        return `/admin/analytics/rankings?${params.toString()}`;
    }

    async function fetchRankings(
        metric: string,
        requestedVariant: AnalyticsVariant = variant.value,
        scope?: AnalyticsScope,
    ): Promise<void> {
        const controller = new AbortController();
        rankingsController?.abort();
        rankingsController = controller;
        rankingsLoading.value = true;
        rankingsError.value = false;

        try {
            const response = await get<AnalyticsRankingResponse>(
                rankingsUrl(metric, requestedVariant, scope),
                { signal: controller.signal },
            );

            if (controller.signal.aborted) {
                return;
            }

            rankingRows.value = response.rows;
        } catch {
            if (controller.signal.aborted) {
                return;
            }
            rankingsError.value = true;
        } finally {
            if (!controller.signal.aborted) {
                rankingsLoading.value = false;
            }
        }
    }

    function formatNumber(value: number): string {
        return new Intl.NumberFormat(locale.value).format(value);
    }

    function formatDuration(seconds: number | null): string {
        if (seconds === null || !Number.isFinite(seconds)) {
            return '—';
        }

        const total = Math.round(seconds);
        const minutes = Math.floor(total / 60);
        const remainder = String(total % 60).padStart(2, '0');

        return `${minutes}m ${remainder}s`;
    }

    function formatPercent(value: number): string {
        return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 0 }).format(value)}%`;
    }

    // The avg blocks carry seconds (time on page / read time) or percent
    // points (completion rate); every other block is a plain count.
    function formatBlockValue(blockKey: string, value: number): string {
        if (blockKey === 'avg_time_on_page' || blockKey === 'avg_read_time') {
            return formatDuration(value);
        }

        if (blockKey === 'completion_rate') {
            return formatPercent(value);
        }

        return formatNumber(value);
    }

    return {
        overviewBlocks,
        blogBlocks,
        projectsBlocks,
        selectBlock,
        abortFetches,
        span,
        variant,
        chartPoints,
        chartLoading,
        chartError,
        multiChartSeries,
        multiChartLoading,
        multiChartError,
        rankingRows,
        rankingsLoading,
        rankingsError,
        fetchSeries,
        fetchMultiSeries,
        fetchRankings,
        formatNumber,
        formatDuration,
        formatPercent,
    };
}
