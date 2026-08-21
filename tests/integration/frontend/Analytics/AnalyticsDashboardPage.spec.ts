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

// Wire fixtures mirroring the 11.1 backend payloads
// (AnalyticsReportGenerationTest / AnalyticsDashboardTest).
const OVERALL_REPORT = {
    report_type: 'overall',
    period_start: '2026-08-10',
    period_end: '2026-08-16',
    data: {
        total_page_views: { value: 12847, previous: 11420 },
        unique_visitors: { value: 890, previous: 812 },
        avg_time_on_page: { value: 75, previous: 70 },
        cta_clicks: {
            columns: ['target', 'count'],
            rows: [
                { target: 'github', count: 5 },
                { target: 'demo', count: 3 },
                { target: 'cv', count: 9 },
                { target: 'feedback', count: 2 },
                { target: 'language', count: 4 },
                { target: 'theme', count: 1 },
            ],
        },
    },
};

const PREVIOUS_OVERALL_REPORT = {
    report_type: 'overall',
    period_start: '2026-08-03',
    period_end: '2026-08-09',
    data: {
        total_page_views: { value: 10000, previous: null },
        unique_visitors: { value: 700, previous: null },
        avg_time_on_page: { value: 60, previous: null },
        cta_clicks: {
            columns: ['target', 'count'],
            rows: [
                { target: 'github', count: 1 },
                { target: 'demo', count: 0 },
                { target: 'cv', count: 2 },
                { target: 'feedback', count: 0 },
                { target: 'language', count: 0 },
                { target: 'theme', count: 0 },
            ],
        },
    },
};

const BLOG_REPORT = {
    report_type: 'blog',
    period_start: '2026-08-10',
    period_end: '2026-08-16',
    data: {
        total_views: { value: 4820, previous: 4100 },
        unique_visitors: { value: 300, previous: 280 },
        top_articles: {
            columns: ['id', 'title', 'views', 'avg_read_time', 'completion_rate'],
            rows: [
                { id: 1, title: 'Payments Gateway', views: 12, avg_read_time: 60, completion_rate: 75 },
                { id: 2, title: 'Ledger Architecture', views: 8, avg_read_time: null, completion_rate: null },
            ],
        },
        reactions: {
            points: [
                { label: 'likes', value: 42 },
                { label: 'dislikes', value: 7 },
            ],
        },
    },
};

const PROJECTS_REPORT = {
    report_type: 'projects',
    period_start: '2026-08-10',
    period_end: '2026-08-16',
    data: {
        project_views: {
            columns: ['id', 'title', 'views'],
            rows: [{ id: 1, title: 'Ledger', views: 24 }],
        },
    },
};

const FEEDBACK_REPORT = {
    report_type: 'feedback',
    period_start: '2026-08-10',
    period_end: '2026-08-16',
    data: {
        total_submissions: { value: 7, previous: 3 },
        submissions_per_day: {
            points: [
                { label: '2026-08-10', value: 2 },
                { label: '2026-08-11', value: 5 },
            ],
        },
    },
};

// Backend order: `period_start` desc, `report_type` asc — the newest
// period first (EloquentAnalyticsReportRepository).
const REPORTS = [OVERALL_REPORT, PREVIOUS_OVERALL_REPORT, BLOG_REPORT, PROJECTS_REPORT, FEEDBACK_REPORT];

function mountPage(props: Record<string, unknown> = {}) {
    // The OpenAPI contract types `data` as opaque (`Record<string, never>`),
    // so the wire fixtures are cast at the mount boundary.
    const wrapper = mount(AnalyticsDashboardPage, {
        props: {
            reports: REPORTS,
            ...props,
        } as unknown as InstanceType<typeof AnalyticsDashboardPage>['$props'],
    });
    mountedWrappers.push(wrapper);

    return wrapper;
}

// The lazy AnalyticsChart (defineAsyncComponent) resolves across several
// microtask/macrotask turns (LandingPage.spec precedent — the first
// dynamic import pays the SFC transform time, so poll instead of sleeping).
async function flushUntilChart(
    wrapper: ReturnType<typeof mountPage>,
    testId: string,
): Promise<void> {
    const deadline = Date.now() + 10_000;
    while (!wrapper.find(`[data-testid="${testId}-svg"]`).exists()) {
        if (Date.now() > deadline) {
            throw new Error('AnalyticsChart did not render within 10s');
        }

        await new Promise((resolve) => setTimeout(resolve, 100));
        await flushPromises();
    }
}

let mountedWrappers: Array<ReturnType<typeof mount>> = [];

describe('AnalyticsDashboardPage', () => {
    let toastStore: ReturnType<typeof useToastStore>;

    beforeEach(() => {
        setActivePinia(createPinia());
        toastStore = useToastStore();
        reloadMock.mockClear();
        pageProps.locale = 'en';
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        for (const wrapper of mountedWrappers) {
            wrapper.unmount();
        }
        mountedWrappers = [];
        vi.unstubAllGlobals();
    });

    it('renders within the admin layout with the title, four tabs and Generate Now', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="analytics-dashboard-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="analytics-dashboard-page"] h1').text()).toBe('Analytics');
        expect(
            wrapper.findAll('[data-testid^="analytics-dashboard-page-tab-"]').map((tab) => tab.text()),
        ).toEqual(['Overview', 'Blog', 'Projects', 'Feedback']);
        expect(wrapper.get('[data-testid="analytics-dashboard-page-generate-now"]').text()).toBe(
            'Generate Now',
        );
    });

    it('renders the overview stats cards from the overall report', () => {
        const wrapper = mountPage();

        const cards = wrapper.findAll('[data-testid^="analytics-dashboard-page-stats-card-"]');
        expect(cards).toHaveLength(6);
        expect(cards[0].text()).toContain('Total Visits');
        expect(cards[0].text()).toContain('12,847');
        expect(cards[0].text()).toContain('+12.5%');
        expect(cards[1].text()).toContain('Unique Visitors');
        expect(cards[1].text()).toContain('890');
        expect(cards[2].text()).toContain('Avg Time on Page');
        expect(cards[2].text()).toContain('1m 15s');
        expect(cards[3].text()).toContain('CTA Clicks');
        expect(cards[3].text()).toContain('24');
        expect(cards[4].text()).toContain('Feedback Submissions');
        expect(cards[4].text()).toContain('2');
        expect(cards[5].text()).toContain('Theme/Language Switches');
        expect(cards[5].text()).toContain('5');
    });

    it('renders the visits-over-time bar chart from the overall reports across periods', async () => {
        const wrapper = mountPage();
        await flushUntilChart(wrapper, 'analytics-dashboard-page-visits-chart');

        const svg = wrapper.get(
            '[data-testid="analytics-dashboard-page-visits-chart-svg"]',
        );
        expect(svg.attributes('role')).toBe('img');
        expect(svg.attributes('aria-label')).toBe('Bar chart: Visits over time');
        // One bar per overall report, oldest period first.
        const labels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-visits-chart-svg"] text')
            .map((node) => node.text());
        expect(labels).toContain('Aug 03');
        expect(labels).toContain('Aug 10');
        expect(labels).toContain('10000');
        expect(labels).toContain('12847');
    });

    it('switches to the blog tab: top-articles table, stats and reactions chart', async () => {
        const wrapper = mountPage();
        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushUntilChart(wrapper, 'analytics-dashboard-page-reactions-chart');

        const rows = wrapper
            .get('[data-testid="analytics-dashboard-page-top-articles"]')
            .findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Payments Gateway');
        expect(rows[0].text()).toContain('12');
        expect(rows[0].text()).toContain('1m 00s');
        expect(rows[0].text()).toContain('75%');
        expect(rows[1].text()).toContain('Ledger Architecture');
        expect(rows[1].text()).toContain('—');

        const cards = wrapper.findAll('[data-testid^="analytics-dashboard-page-blog-stats-card-"]');
        expect(cards).toHaveLength(3);
        expect(cards[0].text()).toContain('Total Views');
        expect(cards[0].text()).toContain('4,820');
        expect(cards[2].text()).toContain('Avg Read Time');
        expect(cards[2].text()).toContain('1m 00s');

        const svgLabels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-reactions-chart-svg"] text')
            .map((node) => node.text());
        expect(svgLabels).toContain('Likes');
        expect(svgLabels).toContain('42');
        expect(svgLabels).toContain('Dislikes');
        expect(svgLabels).toContain('7');
    });

    it('switches to the projects tab with the project views table', async () => {
        const wrapper = mountPage();
        await wrapper.get('[data-testid="analytics-dashboard-page-tab-projects"]').trigger('click');

        const rows = wrapper
            .get('[data-testid="analytics-dashboard-page-project-views"]')
            .findAll('tbody tr');
        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain('Ledger');
        expect(rows[0].text()).toContain('24');
    });

    it('switches to the feedback tab with the submissions line chart', async () => {
        const wrapper = mountPage();
        await wrapper.get('[data-testid="analytics-dashboard-page-tab-feedback"]').trigger('click');
        await flushUntilChart(wrapper, 'analytics-dashboard-page-submissions-chart');

        const svg = wrapper.get(
            '[data-testid="analytics-dashboard-page-submissions-chart-svg"]',
        );
        expect(svg.attributes('aria-label')).toBe('Line chart: Submissions over time');
        expect(svg.find('polyline').exists()).toBe(true);
        const labels = wrapper
            .findAll('[data-testid="analytics-dashboard-page-submissions-chart-svg"] text')
            .map((node) => node.text());
        expect(labels).toContain('Aug 10');
        expect(labels).toContain('Aug 11');
        expect(labels).toContain('5');
    });

    it('shows the empty state when a tab has no report of its type', async () => {
        const wrapper = mountPage({ reports: [OVERALL_REPORT] });

        expect(wrapper.find('[data-testid="analytics-dashboard-page-stats-card-0"]').exists()).toBe(
            true,
        );

        await wrapper.get('[data-testid="analytics-dashboard-page-tab-blog"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-testid="analytics-dashboard-page-empty"]').text()).toBe(
            'No data available',
        );
    });

    it('shows the empty state on every tab when no reports exist', () => {
        const wrapper = mountPage({ reports: [] });

        expect(wrapper.get('[data-testid="analytics-dashboard-page-empty"]').text()).toBe(
            'No data available',
        );
    });

    it('dispatches report generation and reloads the dashboard on success', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ dispatched: true }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);
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
        const fetchMock = vi.fn().mockResolvedValue({ ok: false, json: async () => ({}) });
        vi.mocked(fetch).mockImplementation(fetchMock);

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
