<!-- AnalyticsDashboardPage (admin-section-components.md:1295-1352, admin-section-ui.md:441-452, mockup analytics.html) — the admin analytics dashboard: three tabs (Overview/Blog/Projects — the Feedback tab is gone, its submissions chart moved under the overview feedback-submissions block), a whole-time note on the Overview and Blog tabs, clickable whole-time metric blocks per tab and a detail area below them. Block clicks and the span/variant dropdowns fetch from the JSON endpoints through `useAnalytics` (11.3.6 — AbortController cancellation + per-fetch loading/error refs); the default overview chart (total visits, daily) and the default time-on-page top rankings render from the `default_chart`/`default_rankings` props without fetching. The CTA clicks block renders the six per-target series as a multi-series chart (AnalyticsChart `series` prop — the page maps the target keys to i18n labels before passing them in). "Generate Now" (A9/B284) dispatches report generation via POST /admin/analytics/generate-reports; the job runs async and the dashboard reloads (202 → toast + reload, existing). Chart data is set via textContent exclusively (analytics.md:230). The chart component is code-split (defineAsyncComponent — architecture.md:1250 "dashboard charts async"). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import type { components } from '@shared-cross/Types/inertia';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { type ComputedRef, computed, defineAsyncComponent, ref } from 'vue';

import AnalyticsMetricBlocks from '../Components/AnalyticsMetricBlocks.vue';
import RankingsTable from '../Components/RankingsTable.vue';
import {
    type AnalyticsSpan,
    type AnalyticsTab,
    type AnalyticsVariant,
    type BlockConfig,
    type BlockView,
    blockConfigFor,
    type RankingRow,
    useAnalytics,
} from '../Composables/useAnalytics';

const AnalyticsChart = defineAsyncComponent(() => import('@analytics-admin/AnalyticsChart.vue'));

type DashboardPageProps = NonNullable<
    components['schemas']['AnalyticsDashboardPageProps']['props']
>;
type RankingColumns = Array<{ key: string; label: string }>;
type RankingRowsView = Array<Record<string, unknown>>;

const props = withDefaults(
    defineProps<{
        overview_blocks: DashboardPageProps['overview_blocks'];
        blog_blocks: DashboardPageProps['blog_blocks'];
        projects_blocks: DashboardPageProps['projects_blocks'];
        default_chart: DashboardPageProps['default_chart'];
        default_rankings: DashboardPageProps['default_rankings'];
        test_id?: string;
    }>(),
    {
        test_id: 'analytics-dashboard-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();
const { post } = useJsonApi();

const analytics = useAnalytics(props);

const {
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
} = analytics;

const TAB_BLOCKS: Record<AnalyticsTab, ComputedRef<Array<BlockView>>> = {
    overview: overviewBlocks,
    blog: blogBlocks,
    projects: projectsBlocks,
};

const TABS: Array<AnalyticsTab> = ['overview', 'blog', 'projects'];
const TOP_BOTTOM_VARIANTS: Array<AnalyticsVariant> = ['top', 'bottom'];

const SPAN_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'day', label: t('admin.analytics.span_day') },
    { value: 'week', label: t('admin.analytics.span_week') },
    { value: 'month', label: t('admin.analytics.span_month') },
    { value: 'all', label: t('admin.analytics.span_all') },
];

// The chart blocks keyed to their chart-card titles (the selected block
// drives the detail area; the block-key map mirrors the OpenAPI
// AnalyticsBlock table, 11.3.4).
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
const CTA_TARGET_LABELS: Record<string, string> = {
    github: t('admin.analytics.cta_github'),
    demo: t('admin.analytics.cta_demo'),
    cv: t('admin.analytics.cta_cv'),
    feedback: t('admin.analytics.feedback'),
    language: t('admin.analytics.cta_language'),
    theme: t('admin.analytics.cta_theme'),
};

const activeTab = ref<AnalyticsTab>('overview');
const isGenerating = ref(false);

const activeBlocks = computed(() => TAB_BLOCKS[activeTab.value].value);
const selectedBlockKey = computed(() => activeBlocks.value.find((block) => block.selected)?.key);
const activeConfig = computed(() => {
    const key = selectedBlockKey.value;

    return key ? blockConfigFor(activeTab.value, key) : undefined;
});

// The default overview detail comes from the props (OpenAPI
// AnalyticsDefaultChart/AnalyticsDefaultRankings): total visits daily +
// the time-on-page top 10. No fetch until a span/variant change or a
// different block click.
chartPoints.value = props.default_chart.spans.day.points;
rankingRows.value = props.default_rankings.rows.top;

function selectTab(tab: AnalyticsTab): void {
    // Re-seeding must cancel in-flight fetches — a stale response can
    // never overwrite the fresh default state of the new tab.
    abortFetches();
    activeTab.value = tab;
    span.value = 'day';
    variant.value = 'top';
    chartError.value = false;
    multiChartError.value = false;
    rankingsError.value = false;
    multiChartSeries.value = [];
    chartPoints.value = tab === 'overview' ? props.default_chart.spans.day.points : [];
    rankingRows.value = tab === 'overview' ? props.default_rankings.rows.top : [];
}

function onBlockSelect(key: string): void {
    const tab = activeTab.value;
    selectBlock(tab, key);

    const config = blockConfigFor(tab, key);
    if (config === undefined) {
        return;
    }

    if (config.kind === 'chart') {
        if (!isDefaultChartState(config)) {
            fetchSeries(config.metric, span.value, config.dimension);
        }
    } else if (config.kind === 'chart-multi') {
        fetchMultiSeries(config.metric, config.dimensions ?? [], span.value);
    } else if (!isDefaultRankingsState(config)) {
        const options = config.variants ?? TOP_BOTTOM_VARIANTS;

        if (!options.includes(variant.value)) {
            variant.value = options[0];
        }

        fetchRankings(rankingsMetricFor(config, variant.value), variant.value, config.scope);
    }
}

// The seeded default states render from the props — re-clicking them
// before any span/variant change must not refetch.
function isDefaultChartState(config: BlockConfig): boolean {
    return config.metric === 'page_views' && config.dimension === undefined && span.value === 'day';
}

function isDefaultRankingsState(config: BlockConfig): boolean {
    return config.metric === 'time_on_page_seconds' && variant.value === 'top';
}

function rankingsMetricFor(config: BlockConfig, requestedVariant: AnalyticsVariant): string {
    return config.variantMetric?.[requestedVariant] ?? config.metric;
}

function onSpanChange(value: string | string[]): void {
    span.value = value as AnalyticsSpan;
    refetchChart();
}

function onVariantChange(value: AnalyticsVariant): void {
    variant.value = value;
    refetchRankings();
}

function onRankingsRetry(): void {
    refetchRankings();
}

function refetchChart(): void {
    const config = activeConfig.value;

    if (config?.kind === 'chart') {
        fetchSeries(config.metric, span.value, config.dimension);
    } else if (config?.kind === 'chart-multi') {
        fetchMultiSeries(config.metric, config.dimensions ?? [], span.value);
    }
}

function refetchRankings(): void {
    const config = activeConfig.value;

    if (config?.kind === 'rankings') {
        fetchRankings(rankingsMetricFor(config, variant.value), variant.value, config.scope);
    }
}

const chartTitle = computed(() => {
    const key = selectedBlockKey.value;

    return key ? t(CHART_TITLE_KEYS[key] ?? 'admin.analytics.visits_over_time') : '';
});

const chartType = computed<'bar' | 'line'>(() =>
    activeConfig.value?.kind === 'chart-multi' || selectedBlockKey.value === 'feedback_submissions'
        ? 'line'
        : 'bar',
);

const chartIsLoading = computed(() =>
    activeConfig.value?.kind === 'chart-multi' ? multiChartLoading.value : chartLoading.value,
);

// A fetch failure passes a non-array data/series — the chart contract's
// error signal (admin-section-components.md:1351).
const chartData = computed(() => (chartError.value ? null : chartPoints.value));

const chartSeries = computed(() =>
    multiChartSeries.value.map((series) => ({
        name: CTA_TARGET_LABELS[series.name] ?? series.name,
        points: series.points,
    })),
);

const chartSeriesProp = computed(() => {
    if (activeConfig.value?.kind !== 'chart-multi') {
        return undefined;
    }

    return multiChartError.value ? null : chartSeries.value;
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
        variant.value === 'liked'
            ? t('admin.analytics.likes')
            : variant.value === 'disliked'
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

const rankingRowKey = computed(() => (activeConfig.value?.scope === 'page' ? 'page_url' : 'id'));

// The raw ranking rows (OpenAPI oneOf — shape follows the scope) are
// mapped to display rows with locale-formatted values; the scope/metric
// drives the shape, so the type guards pick the matching member.
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
        return rankingRows.value.filter(isPageRow).map((row) => ({
            page_url: row.page_url,
            avg: formatDuration(row.avg),
            min: formatDuration(row.min),
            max: formatDuration(row.max),
        }));
    }

    if (config.metric === 'read_time_seconds') {
        return rankingRows.value.filter(isArticleAvgRow).map((row) => ({
            id: row.id,
            title: row.title,
            avg: formatDuration(row.avg),
            min: formatDuration(row.min),
            max: formatDuration(row.max),
        }));
    }

    if (config.metric === 'completion_rate_sum') {
        return rankingRows.value.filter(isArticleAvgRow).map((row) => ({
            id: row.id,
            title: row.title,
            avg: formatPercent(row.avg),
            min: formatPercent(row.min),
            max: formatPercent(row.max),
        }));
    }

    return rankingRows.value.filter(isValueRow).map((row) => ({
        id: row.id,
        title: row.title,
        value: formatNumber(row.value),
    }));
});

function generateNow(): void {
    if (isGenerating.value) {
        return;
    }

    isGenerating.value = true;

    post<{ dispatched: boolean }>('/admin/analytics/generate-reports')
        .then(() => {
            toast.show(t('admin.toasts.generate_reports_started'));
            router.reload();
        })
        .catch(() => {
            toast.show(t('admin.toasts.generate_reports_failed'), { variant: 'error' });
        })
        .finally(() => {
            isGenerating.value = false;
        });
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/analytics'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-6xl"
      :data-testid="props.test_id"
    >
      <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('admin.analytics.title') }}
        </h1>
        <AdminBaseButton
          variant="primary"
          icon="heroicons:arrow-path"
          icon_position="left"
          :loading="isGenerating"
          :test_id="`${props.test_id}-generate-now`"
          @click="generateNow"
        >
          {{ t('admin.analytics.generate_now') }}
        </AdminBaseButton>
      </div>

      <div
        class="mb-6 flex border-b border-slate-200 dark:border-slate-700"
        :data-testid="`${props.test_id}-tabs`"
      >
        <AdminBaseButton
          v-for="tab in TABS"
          :key="tab"
          :variant="'ghost'"
          :type="'button'"
          :class="
            activeTab === tab
              ? '-mb-px rounded-none border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
              : 'rounded-none'
          "
          :test_id="`${props.test_id}-tab-${tab}`"
          @click="selectTab(tab)"
        >
          {{ t(`admin.analytics.${tab}`) }}
        </AdminBaseButton>
      </div>

      <div
        v-for="tab in TABS"
        :key="tab"
      >
        <template v-if="activeTab === tab">
          <p
            v-if="tab !== 'projects'"
            class="mb-4 text-sm text-slate-500 dark:text-slate-400"
            :data-testid="`${props.test_id}-note`"
          >
            {{ t('admin.analytics.whole_time_note') }}
          </p>

          <template v-if="activeBlocks.length > 0">
            <AnalyticsMetricBlocks
              :blocks="activeBlocks"
              :test_id="`${props.test_id}-blocks`"
              @select="onBlockSelect"
            />

            <template v-if="activeConfig?.kind === 'chart' || activeConfig?.kind === 'chart-multi'">
              <div class="mb-3 flex items-center justify-end">
                <AdminBaseDropdown
                  :model-value="span"
                  :options="SPAN_OPTIONS"
                  :test_id="`${props.test_id}-span`"
                  @update:model-value="onSpanChange"
                />
              </div>
              <AnalyticsChart
                :type="chartType"
                :data="chartData"
                :series="chartSeriesProp"
                :is_loading="chartIsLoading"
                :title="chartTitle"
                :test_id="`${props.test_id}-chart`"
              />
            </template>

            <RankingsTable
              v-else-if="activeConfig?.kind === 'rankings'"
              :columns="rankingColumns"
              :rows="rankingRowsView"
              :variant="variant"
              :variant_options="rankingVariantOptions"
              :is_loading="rankingsLoading"
              :error="rankingsError"
              :row_key="rankingRowKey"
              :test_id="`${props.test_id}-rankings`"
              @update:variant="onVariantChange"
              @retry="onRankingsRetry"
            />
          </template>

          <AdminEmptyState
            v-else
            mode="empty"
            :message="t('admin.analytics.no_data')"
            :test_id="`${props.test_id}-empty`"
          />
        </template>
      </div>
    </section>
  </AdminPageLayout>
</template>
