import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed } from 'vue';

/**
 * Analytics dashboard data wiring (architecture.md:1002-1008, analytics.md:196-202,
 * admin-section-components.md:1295-1334) — maps the Inertia `reports` prop
 * (typed `AnalyticsReportData`; the per-type `data` payloads are opaque in the
 * OpenAPI contract) into per-tab view models: stat cards, chart series and
 * data tables. Lives in the Analytics feature (not the Shared/CrossSurface
 * tracker — `useAnalytics` there owns public-site event tracking, B74).
 *
 * The payload shapes mirror the report entities (`OverallReport`,
 * `BlogReport`, `ProjectsReport`, `FeedbackReport`): scalars are
 * `{ value, previous }` (previous = prior-period value for the trend),
 * series are `{ points: [{label, value}] }` and tables are
 * `{ columns, rows }`. `visits_over_time` is derived across ALL `overall`
 * reports (one point per period), since the report payloads carry no
 * intra-period series. The `reports` array is read through the props
 * object (reactive — useLandingPage precedent) so a later Inertia visit
 * with fresh reports re-computes the views.
 */
export type AnalyticsReport = components['schemas']['AnalyticsReportData'];
export type ReportType = 'overall' | 'blog' | 'projects' | 'feedback';

/** The page's own prop slice (mirrors `useLandingPage(props)` — the
 *  composable receives the props object, not a snapshot, so the computed
 *  views stay reactive across Inertia visits with updated props). */
export type AnalyticsDashboardProps = Pick<
    NonNullable<components['schemas']['AnalyticsDashboardPageProps']['props']>,
    'reports'
>;

export interface ScalarData {
    value?: number | null;
    previous?: number | null;
}

export interface SeriesPoint {
    label: string;
    value: number;
}

export interface TableRow {
    [key: string]: unknown;
}

export interface StatView {
    label: string;
    value: number | string;
    trend?: string;
    isLoading: boolean;
}

export interface TableView {
    columns: Array<{ key: string; label: string }>;
    rows: Array<TableRow>;
}

export interface UseAnalyticsApi {
    /** The newest report of the given type — `undefined` when absent. */
    reportFor: (type: ReportType) => AnalyticsReport | undefined;
    /** Scalar result of a report — `null` when the report or key is missing. */
    scalar: (type: ReportType, key: string) => ScalarData | null;
    /** Table rows of a report result — `[]` when missing. */
    tableRows: (type: ReportType, key: string) => Array<TableRow>;
    /** Series points of a report result — `[]` when missing. */
    series: (type: ReportType, key: string) => Array<SeriesPoint>;
    /** Percent change vs the previous period — `undefined` when unknown. */
    changePercent: (scalar: ScalarData | null) => string | undefined;
    /** Locale-formatted number ("12,847" — mockup analytics.html). */
    formatNumber: (value: number) => string;
    /** Seconds → "1m 05s" (avg read time / time on page). */
    formatDuration: (seconds: number | null) => string;
    /** "2026-07-06" → "Jul 06" chart x-label. */
    formatPeriodLabel: (date: string) => string;
    /** Overview stat cards (admin-section-components.md:1304). */
    overviewStats: ComputedRef<Array<StatView>>;
    /** Total page views per overall report period, oldest first. */
    visitsSeries: ComputedRef<Array<SeriesPoint>>;
    /** Blog stat cards: total views, unique visitors, avg read time. */
    blogStats: ComputedRef<Array<StatView>>;
    /** Most-viewed articles table rows (views, avg read time, completion). */
    topArticlesTable: ComputedRef<TableView>;
    /** Reactions summary series (likes/dislikes, localized labels). */
    reactionsSeries: ComputedRef<Array<SeriesPoint>>;
    /** Project views table rows. */
    projectViewsTable: ComputedRef<TableView>;
    /** Feedback submissions per day, oldest first. */
    submissionsSeries: ComputedRef<Array<SeriesPoint>>;
}

const PERIOD_RE = /^(\d{4})-(\d{2})-(\d{2})/;

export function useAnalytics(props: AnalyticsDashboardProps): UseAnalyticsApi {
    const { t, locale } = useI18n();
    const reports = props.reports ?? [];

    // The backend orders by `period_start` desc, `report_type` asc
    // (EloquentAnalyticsReportRepository) — the newest period is first.
    function reportFor(type: ReportType): AnalyticsReport | undefined {
        return reports.find((report) => report.report_type === type);
    }

    function scalar(type: ReportType, key: string): ScalarData | null {
        const data = reportFor(type)?.data;

        if (data === undefined || !(key in data)) {
            return null;
        }

        const entry = data[key];

        return typeof entry === 'object' && entry !== null && !Array.isArray(entry)
            ? (entry as ScalarData)
            : null;
    }

    function tableRows(type: ReportType, key: string): Array<TableRow> {
        const data = reportFor(type)?.data;

        if (data === undefined || !(key in data)) {
            return [];
        }

        const entry = data[key] as { rows?: Array<TableRow> } | null;

        return Array.isArray(entry?.rows) ? (entry.rows as Array<TableRow>) : [];
    }

    function series(type: ReportType, key: string): Array<SeriesPoint> {
        const data = reportFor(type)?.data;

        if (data === undefined || !(key in data)) {
            return [];
        }

        const entry = data[key] as { points?: Array<SeriesPoint> } | null;

        return Array.isArray(entry?.points) ? (entry.points as Array<SeriesPoint>) : [];
    }

    function changePercent(current: ScalarData | null): string | undefined {
        const value = current?.value ?? null;
        const previous = current?.previous ?? null;

        if (value === null || previous === null || previous === 0) {
            return undefined;
        }

        const change = ((value - previous) / previous) * 100;
        const sign = change >= 0 ? '+' : '';

        return `${sign}${change.toFixed(1)}%`;
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

    function formatPeriodLabel(date: string): string {
        const match = PERIOD_RE.exec(date);

        if (match === null) {
            return date;
        }

        const parsed = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

        return new Intl.DateTimeFormat(locale.value, { month: 'short', day: '2-digit' }).format(
            parsed,
        );
    }

    const overviewStats = computed<Array<StatView>>(() => {
        const totalPageViews = scalar('overall', 'total_page_views');
        const uniqueVisitors = scalar('overall', 'unique_visitors');
        const avgTimeOnPage = scalar('overall', 'avg_time_on_page');
        const ctaRows = tableRows('overall', 'cta_clicks');
        const ctaCount = (target: string): number =>
            ctaRows.reduce((sum, row) => {
                const rowTarget = row.target;

                return rowTarget === target ? sum + (Number(row.count) || 0) : sum;
            }, 0);
        const ctaTotal = ctaRows.reduce((sum, row) => sum + (Number(row.count) || 0), 0);

        return [
            {
                label: t('admin.analytics.total_visits'),
                value: formatNumber(totalPageViews?.value ?? 0),
                trend: changePercent(totalPageViews),
                isLoading: false,
            },
            {
                label: t('admin.analytics.unique_visitors'),
                value: formatNumber(uniqueVisitors?.value ?? 0),
                trend: changePercent(uniqueVisitors),
                isLoading: false,
            },
            {
                label: t('admin.analytics.avg_time_on_page'),
                value: formatDuration(avgTimeOnPage?.value ?? null),
                trend: changePercent(avgTimeOnPage),
                isLoading: false,
            },
            {
                label: t('admin.analytics.cta_clicks'),
                value: formatNumber(ctaTotal),
                isLoading: false,
            },
            {
                label: t('admin.analytics.feedback_submissions'),
                value: formatNumber(ctaCount('feedback')),
                isLoading: false,
            },
            {
                label: t('admin.analytics.theme_language_switches'),
                value: formatNumber(ctaCount('language') + ctaCount('theme')),
                isLoading: false,
            },
        ];
    });

    const visitsSeries = computed<Array<SeriesPoint>>(() =>
        reports
            .filter((report) => report.report_type === 'overall')
            .sort((a, b) => (a.period_start ?? '').localeCompare(b.period_start ?? ''))
            .map((report) => {
                const entry = report.data?.total_page_views as ScalarData | undefined;

                return {
                    label: formatPeriodLabel(report.period_start ?? ''),
                    value: entry?.value ?? 0,
                };
            }),
    );

    const blogStats = computed<Array<StatView>>(() => {
        const totalViews = scalar('blog', 'total_views');
        const uniqueVisitors = scalar('blog', 'unique_visitors');
        const readTimes = tableRows('blog', 'top_articles')
            .map((row) => row.avg_read_time)
            .filter(
                (seconds): seconds is number =>
                    seconds !== null && seconds !== undefined && Number.isFinite(Number(seconds)),
            )
            .map((seconds) => Number(seconds));
        const averageReadTime =
            readTimes.length === 0 ? null : readTimes.reduce((a, b) => a + b, 0) / readTimes.length;

        return [
            {
                label: t('admin.analytics.total_views'),
                value: formatNumber(totalViews?.value ?? 0),
                trend: changePercent(totalViews),
                isLoading: false,
            },
            {
                label: t('admin.analytics.unique_visitors'),
                value: formatNumber(uniqueVisitors?.value ?? 0),
                trend: changePercent(uniqueVisitors),
                isLoading: false,
            },
            {
                label: t('admin.analytics.avg_read_time'),
                value: formatDuration(averageReadTime),
                isLoading: false,
            },
        ];
    });

    const topArticlesTable = computed<TableView>(() => ({
        columns: [
            { key: 'title', label: t('common.title') },
            { key: 'views', label: t('admin.analytics.views') },
            { key: 'avg_read_time', label: t('admin.analytics.avg_read_time') },
            { key: 'completion_rate', label: t('admin.analytics.completion_rate') },
        ],
        rows: tableRows('blog', 'top_articles').map((row) => ({
            id: row.id,
            title: row.title ?? '',
            views: formatNumber(Number(row.views) || 0),
            avg_read_time: formatDuration(
                row.avg_read_time === null ? null : Number(row.avg_read_time),
            ),
            completion_rate:
                row.completion_rate === null || row.completion_rate === undefined
                    ? '—'
                    : `${Math.round(Number(row.completion_rate))}%`,
        })),
    }));

    const reactionsSeries = computed<Array<SeriesPoint>>(() =>
        series('blog', 'reactions').map((point) => ({
            label:
                point.label === 'likes'
                    ? t('admin.analytics.likes')
                    : point.label === 'dislikes'
                      ? t('admin.analytics.dislikes')
                      : point.label,
            value: point.value,
        })),
    );

    const projectViewsTable = computed<TableView>(() => ({
        columns: [
            { key: 'title', label: t('common.title') },
            { key: 'views', label: t('admin.analytics.views') },
        ],
        rows: tableRows('projects', 'project_views').map((row) => ({
            id: row.id,
            title: row.title ?? '',
            views: formatNumber(Number(row.views) || 0),
        })),
    }));

    const submissionsSeries = computed<Array<SeriesPoint>>(() =>
        series('feedback', 'submissions_per_day').map((point) => ({
            label: formatPeriodLabel(point.label),
            value: point.value,
        })),
    );

    return {
        reportFor,
        scalar,
        tableRows,
        series,
        changePercent,
        formatNumber,
        formatDuration,
        formatPeriodLabel,
        overviewStats,
        visitsSeries,
        blogStats,
        topArticlesTable,
        reactionsSeries,
        projectViewsTable,
        submissionsSeries,
    };
}
