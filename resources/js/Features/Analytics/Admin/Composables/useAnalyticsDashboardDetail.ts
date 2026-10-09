import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

import {
    type AnalyticsSpan,
    type AnalyticsTab,
    type AnalyticsVariant,
    type BlockConfig,
    type BlockView,
    blockConfigFor,
    type ChartSeries,
    type RankingRow,
    type SeriesPoint,
    type UseAnalyticsApi,
} from './useAnalytics';

/**
 * The dashboard page's detail-area wiring: owns the active tab + the
 * selected block, the fetch orchestration (default-seed states render
 * from the props without fetching; block clicks and the span/variant
 * dropdowns fetch through `useAnalytics` — AbortController cancellation
 * + per-fetch loading/error refs) and the chart/rankings view models
 * for the detail area. The page keeps only the orchestration (header,
 * tabs, blocks, empty state) and renders `AnalyticsDetailArea` with
 * this API. The default overview chart (total visits, daily) and the
 * default time-on-page top rankings render from the
 * `default_chart`/`default_rankings` props without fetching.
 */
export type AnalyticsDetailProps = Pick<
    NonNullable<components['schemas']['AnalyticsDashboardPageProps']['props']>,
    'default_chart' | 'default_rankings'
>;

export interface UseAnalyticsDashboardDetail {
    tabs: Array<AnalyticsTab>;
    activeTab: Ref<AnalyticsTab>;
    activeBlocks: ComputedRef<Array<BlockView>>;
    activeConfig: ComputedRef<BlockConfig | undefined>;
    /** The active chart span (the span dropdown v-model). */
    span: Ref<AnalyticsSpan>;
    /** The active rankings variant (the variant dropdown v-model). */
    variant: Ref<AnalyticsVariant>;
    rankingsLoading: Ref<boolean>;
    rankingsError: Ref<boolean>;
    selectTab: (tab: AnalyticsTab) => void;
    onBlockSelect: (key: string) => void;
    onSpanChange: (value: string | string[]) => void;
    onVariantChange: (value: AnalyticsVariant) => void;
    onRankingsRetry: () => void;
    /** The dropdown labels resolve at render time — module-level `t()`
     *  calls would bake the pre-hydration locale (en) and never update
     *  after a language switch. */
    spanOptions: () => Array<{ value: string; label: string }>;
    chartTitle: ComputedRef<string>;
    chartType: ComputedRef<'bar' | 'line'>;
    chartIsLoading: ComputedRef<boolean>;
    chartData: ComputedRef<Array<SeriesPoint> | null>;
    chartSeriesProp: ComputedRef<Array<ChartSeries> | null | undefined>;
    rankingColumns: ComputedRef<Array<{ key: string; label: string }>>;
    rankingRowKey: ComputedRef<string>;
    rankingRowsView: ComputedRef<Array<Record<string, unknown>>>;
    rankingVariantOptions: ComputedRef<Array<{ value: AnalyticsVariant; label: string }>>;
}

type RankingColumns = Array<{ key: string; label: string }>;
type RankingRowsView = Array<Record<string, unknown>>;

const TABS: Array<AnalyticsTab> = ['overview', 'blog', 'projects'];
const TOP_BOTTOM_VARIANTS: Array<AnalyticsVariant> = ['top', 'bottom'];

// The chart blocks keyed to their chart-card titles (the selected block
// drives the detail area; the block-key map mirrors the OpenAPI
// AnalyticsBlock table).
const CHART_TITLE_KEYS: Record<string, string> = {
    total_visits: 'admin.analytics.visits_over_time',
    unique_visitors: 'admin.analytics.unique_visitors_over_time',
    cta_clicks: 'admin.analytics.cta_clicks_over_time',
    feedback_submissions: 'admin.analytics.submissions_over_time',
    language_switches: 'admin.analytics.language_switches_over_time',
};

// The CTA multi-chart legend: the backend dimension targets mapped to
// i18n labels BEFORE the series reach AnalyticsChart (the chart renders
// the names verbatim into text nodes).
const CTA_TARGET_LABEL_KEYS: Record<string, string> = {
    github: 'admin.analytics.cta_github',
    demo: 'admin.analytics.cta_demo',
    cv: 'admin.analytics.cta_cv',
    feedback: 'admin.analytics.feedback',
    projects: 'admin.analytics.cta_projects',
    language: 'admin.analytics.cta_language',
    theme: 'admin.analytics.cta_theme',
};

export function useAnalyticsDashboardDetail(
    analytics: UseAnalyticsApi,
    props: AnalyticsDetailProps,
): UseAnalyticsDashboardDetail {
    const { t } = useI18n();

    const activeTab = ref<AnalyticsTab>('overview');

    const TAB_BLOCKS: Record<AnalyticsTab, ComputedRef<Array<BlockView>>> = {
        overview: analytics.overviewBlocks,
        blog: analytics.blogBlocks,
        projects: analytics.projectsBlocks,
    };

    const activeBlocks = computed(() => TAB_BLOCKS[activeTab.value].value);
    const selectedBlockKey = computed(
        () => activeBlocks.value.find((block) => block.selected)?.key,
    );
    const activeConfig = computed(() => {
        const key = selectedBlockKey.value;

        return key ? blockConfigFor(activeTab.value, key) : undefined;
    });

    // The default overview detail comes from the props (OpenAPI
    // AnalyticsDefaultChart/AnalyticsDefaultRankings): total visits
    // daily + the time-on-page top 10. No fetch until a span/variant
    // change or a different block click.
    analytics.chartPoints.value = props.default_chart.spans.day.points;
    analytics.rankingRows.value = props.default_rankings.rows.top;

    function selectTab(tab: AnalyticsTab): void {
        // Re-seeding must cancel in-flight fetches — a stale response
        // can never overwrite the fresh default state of the new tab.
        analytics.abortFetches();
        activeTab.value = tab;
        analytics.span.value = 'day';
        analytics.variant.value = 'top';
        analytics.chartError.value = false;
        analytics.multiChartError.value = false;
        analytics.rankingsError.value = false;
        analytics.multiChartSeries.value = [];
        seedDefaultState();
    }

    // Seeds the prop defaults only when the selected block really is a
    // default state (total visits daily / time-on-page top); any other
    // selection clears the detail area — a stale fetch result (or the
    // default series) must never render under a different block's
    // title.
    function seedDefaultState(): void {
        const key = selectedBlockKey.value;
        const config = key ? blockConfigFor(activeTab.value, key) : undefined;

        if (config?.kind === 'chart' && isDefaultChartState(config)) {
            analytics.chartPoints.value = props.default_chart.spans.day.points;
        } else {
            analytics.chartPoints.value = [];
        }

        if (config?.kind === 'rankings' && isDefaultRankingsState(config)) {
            analytics.rankingRows.value = props.default_rankings.rows.top;
        } else {
            analytics.rankingRows.value = [];
        }
    }

    function onBlockSelect(key: string): void {
        const tab = activeTab.value;
        analytics.selectBlock(tab, key);

        const config = blockConfigFor(tab, key);
        if (config === undefined) {
            return;
        }

        if (config.kind === 'chart') {
            if (isDefaultChartState(config)) {
                analytics.chartPoints.value = props.default_chart.spans.day.points;
            } else {
                analytics.fetchSeries(config.metric, analytics.span.value, config.dimension);
            }
        } else if (config.kind === 'chart-multi') {
            analytics.fetchMultiSeries(
                config.metric,
                config.dimensions ?? [],
                analytics.span.value,
            );
        } else if (isDefaultRankingsState(config)) {
            analytics.rankingRows.value = props.default_rankings.rows.top;
        } else {
            const options = config.variants ?? TOP_BOTTOM_VARIANTS;

            if (!options.includes(analytics.variant.value)) {
                analytics.variant.value = options[0];
            }

            analytics.fetchRankings(
                rankingsMetricFor(config, analytics.variant.value),
                analytics.variant.value,
                config.scope,
            );
        }
    }

    // The seeded default states render from the props — re-clicking
    // them before any span/variant change must not refetch.
    function isDefaultChartState(config: BlockConfig): boolean {
        return (
            config.metric === 'page_views' &&
            config.dimension === undefined &&
            analytics.span.value === 'day'
        );
    }

    function isDefaultRankingsState(config: BlockConfig): boolean {
        return config.metric === 'time_on_page_seconds' && analytics.variant.value === 'top';
    }

    function rankingsMetricFor(config: BlockConfig, requestedVariant: AnalyticsVariant): string {
        return config.variantMetric?.[requestedVariant] ?? config.metric;
    }

    function onSpanChange(value: string | string[]): void {
        analytics.span.value = value as AnalyticsSpan;
        refetchChart();
    }

    function onVariantChange(value: AnalyticsVariant): void {
        analytics.variant.value = value;
        refetchRankings();
    }

    function onRankingsRetry(): void {
        refetchRankings();
    }

    function refetchChart(): void {
        const config = activeConfig.value;

        if (config?.kind === 'chart') {
            analytics.fetchSeries(config.metric, analytics.span.value, config.dimension);
        } else if (config?.kind === 'chart-multi') {
            analytics.fetchMultiSeries(
                config.metric,
                config.dimensions ?? [],
                analytics.span.value,
            );
        }
    }

    function refetchRankings(): void {
        const config = activeConfig.value;

        if (config?.kind === 'rankings') {
            analytics.fetchRankings(
                rankingsMetricFor(config, analytics.variant.value),
                analytics.variant.value,
                config.scope,
            );
        }
    }

    function spanOptions(): Array<{ value: string; label: string }> {
        return [
            { value: 'day', label: t('admin.analytics.span_day') },
            { value: 'week', label: t('admin.analytics.span_week') },
            { value: 'month', label: t('admin.analytics.span_month') },
            { value: 'all', label: t('admin.analytics.span_all') },
        ];
    }

    function ctaTargetLabel(target: string): string {
        const key = CTA_TARGET_LABEL_KEYS[target];

        return key === undefined ? target : t(key);
    }

    const chartTitle = computed(() => {
        const key = selectedBlockKey.value;

        return key ? t(CHART_TITLE_KEYS[key] ?? 'admin.analytics.visits_over_time') : '';
    });

    const chartType = computed<'bar' | 'line'>(() =>
        activeConfig.value?.kind === 'chart-multi' ||
        selectedBlockKey.value === 'feedback_submissions'
            ? 'line'
            : 'bar',
    );

    const chartIsLoading = computed(() =>
        activeConfig.value?.kind === 'chart-multi'
            ? analytics.multiChartLoading.value
            : analytics.chartLoading.value,
    );

    // A fetch failure passes a non-array data/series — the chart
    // contract's error signal.
    const chartData = computed(() =>
        analytics.chartError.value ? null : analytics.chartPoints.value,
    );

    const chartSeries = computed(() =>
        analytics.multiChartSeries.value.map((series) => ({
            name: ctaTargetLabel(series.name),
            points: series.points,
        })),
    );

    const chartSeriesProp = computed(() => {
        if (activeConfig.value?.kind !== 'chart-multi') {
            return undefined;
        }

        return analytics.multiChartError.value ? null : chartSeries.value;
    });

    const rankingVariantOptions = computed(() => {
        const options = activeConfig.value?.variants ?? TOP_BOTTOM_VARIANTS;

        return options.map((value) => ({ value, label: t(`admin.analytics.variant_${value}`) }));
    });

    const rankingColumns = computed<RankingColumns>(() => {
        const config = activeConfig.value;

        if (config?.kind !== 'rankings') {
            return [];
        }

        if (config.scope === 'page') {
            return [
                { key: 'page_url', label: t('admin.analytics.page') },
                { key: 'avg', label: t('admin.analytics.avg') },
                { key: 'min', label: t('admin.analytics.min') },
                { key: 'max', label: t('admin.analytics.max') },
            ];
        }

        const titleLabel = t(
            config.scope === 'project' ? 'admin.analytics.project' : 'admin.analytics.article',
        );

        if (config.metric === 'read_time_seconds' || config.metric === 'completion_rate_sum') {
            return [
                { key: 'title', label: titleLabel },
                { key: 'avg', label: t('admin.analytics.avg') },
                { key: 'min', label: t('admin.analytics.min') },
                { key: 'max', label: t('admin.analytics.max') },
            ];
        }

        const valueLabel =
            analytics.variant.value === 'liked'
                ? t('admin.analytics.likes')
                : analytics.variant.value === 'disliked'
                  ? t('admin.analytics.dislikes')
                  : t(
                        config.metric === 'unique_visitors' && config.scope === 'project'
                            ? 'admin.analytics.unique_views'
                            : config.metric === 'unique_visitors'
                              ? 'admin.analytics.unique_visitors'
                              : 'admin.analytics.views',
                    );

        return [
            { key: 'title', label: titleLabel },
            { key: 'value', label: valueLabel },
        ];
    });

    const rankingRowKey = computed(() =>
        activeConfig.value?.scope === 'page' ? 'page_url' : 'id',
    );

    // The raw ranking rows (OpenAPI oneOf — shape follows the scope)
    // are mapped to display rows with locale-formatted values; the
    // scope/metric drives the shape, so the type guards pick the
    // matching member.
    function isPageRow(row: RankingRow): row is components['schemas']['AnalyticsPageRankingRow'] {
        return 'page_url' in row;
    }

    function isArticleAvgRow(
        row: RankingRow,
    ): row is components['schemas']['AnalyticsArticleAvgRankingRow'] {
        return !('page_url' in row) && 'avg' in row;
    }

    function isValueRow(
        row: RankingRow,
    ): row is
        | components['schemas']['AnalyticsArticleValueRankingRow']
        | components['schemas']['AnalyticsProjectRankingRow'] {
        return 'value' in row;
    }

    const rankingRowsView = computed<RankingRowsView>(() => {
        const config = activeConfig.value;

        if (config?.kind !== 'rankings') {
            return [];
        }

        if (config.scope === 'page') {
            return analytics.rankingRows.value.filter(isPageRow).map((row) => ({
                page_url: row.page_url,
                avg: analytics.formatDuration(row.avg),
                min: analytics.formatDuration(row.min),
                max: analytics.formatDuration(row.max),
            }));
        }

        if (config.metric === 'read_time_seconds') {
            return analytics.rankingRows.value.filter(isArticleAvgRow).map((row) => ({
                id: row.id,
                title: row.title,
                avg: analytics.formatDuration(row.avg),
                min: analytics.formatDuration(row.min),
                max: analytics.formatDuration(row.max),
            }));
        }

        if (config.metric === 'completion_rate_sum') {
            return analytics.rankingRows.value.filter(isArticleAvgRow).map((row) => ({
                id: row.id,
                title: row.title,
                avg: analytics.formatPercent(row.avg),
                min: analytics.formatPercent(row.min),
                max: analytics.formatPercent(row.max),
            }));
        }

        return analytics.rankingRows.value.filter(isValueRow).map((row) => ({
            id: row.id,
            title: row.title,
            value: analytics.formatNumber(row.value),
        }));
    });

    return {
        tabs: TABS,
        activeTab,
        activeBlocks,
        activeConfig,
        // The dropdown v-models + the rankings fetch state (the detail
        // area's read-only pass-through of the analytics refs).
        span: analytics.span,
        variant: analytics.variant,
        rankingsLoading: analytics.rankingsLoading,
        rankingsError: analytics.rankingsError,
        selectTab,
        onBlockSelect,
        onSpanChange,
        onVariantChange,
        onRankingsRetry,
        spanOptions,
        chartTitle,
        chartType,
        chartIsLoading,
        chartData,
        chartSeriesProp,
        rankingColumns,
        rankingRowKey,
        rankingRowsView,
        rankingVariantOptions,
    };
}
