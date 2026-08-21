import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AnalyticsChart from '@analytics-admin/AnalyticsChart.vue';

const DATA = [
    { label: 'Jun 01', value: 842 },
    { label: 'Jun 08', value: 1200 },
    { label: 'Jun 15', value: 500 },
];

const SERIES = [
    {
        name: 'GitHub',
        points: [
            { label: 'Jun 01', value: 5 },
            { label: 'Jun 08', value: 8 },
        ],
    },
    {
        name: 'Demo',
        points: [
            { label: 'Jun 01', value: 3 },
            { label: 'Jun 08', value: 2 },
        ],
    },
];

function mountChart(props: Record<string, unknown> = {}) {
    return mount(AnalyticsChart, {
        props: { data: DATA, ...props },
    });
}

function xsOf(polyline: { attributes: (name: string) => string | undefined }): Array<number> {
    return (polyline.attributes('points') ?? '')
        .trim()
        .split(/\s+/)
        .map((pair) => Number(pair.split(',')[0]));
}

describe('AnalyticsChart', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a bar chart with the data values as text nodes', () => {
        const wrapper = mountChart({ type: 'bar', title: 'Visits over time' });

        expect(wrapper.get('[data-testid="analytics-chart"] h2').text()).toBe('Visits over time');
        const svg = wrapper.get('[data-testid="analytics-chart-svg"]');
        expect(svg.attributes('role')).toBe('img');
        expect(svg.attributes('aria-label')).toBe('Bar chart: Visits over time');
        expect(svg.findAll('rect')).toHaveLength(3);
        expect(
            svg.findAll('text').map((node) => node.text()),
        ).toEqual(['842', 'Jun 01', '1200', 'Jun 08', '500', 'Jun 15']);
    });

    it('renders a line chart with the polyline and point markers', () => {
        const wrapper = mountChart({ type: 'line', title: 'Submissions over time' });

        const svg = wrapper.get('[data-testid="analytics-chart-svg"]');
        expect(svg.attributes('aria-label')).toBe('Line chart: Submissions over time');
        expect(svg.find('polyline').exists()).toBe(true);
        expect(svg.findAll('circle')).toHaveLength(3);
    });

    it('shows the skeleton while loading', () => {
        const wrapper = mountChart({ is_loading: true });

        expect(wrapper.find('[data-testid="analytics-chart-loading"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="analytics-chart-svg"]').exists()).toBe(false);
    });

    it('shows the empty state for an empty data array', () => {
        const wrapper = mountChart({ data: [] });

        expect(wrapper.get('[data-testid="analytics-chart-empty"]').text()).toBe(
            'No data available',
        );
    });

    it('shows the error state when the data prop is not an array', () => {
        // The runtime type check warns on the deliberate type violation —
        // silence it so the assertion output stays clean.
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const wrapper = mountChart({ data: 'oops' });
        warnSpy.mockRestore();

        expect(wrapper.get('[data-testid="analytics-chart-error"]').text()).toBe(
            'Could not load chart. [Retry]',
        );
    });

    it('renders hostile labels as plain text nodes, never as markup (XSS)', () => {
        const wrapper = mountChart({
            data: [{ label: '<img src=x onerror="window.__xss=1">', value: 1 }],
        });

        const svg = wrapper.get('[data-testid="analytics-chart-svg"]');
        expect(svg.find('img').exists()).toBe(false);
        expect(svg.get('text').text()).toBe('1');
        expect(svg.findAll('text')[1].text()).toBe('<img src=x onerror="window.__xss=1">');
        // Vue escaped the payload into text nodes — no raw markup in the DOM.
        expect(wrapper.html()).not.toContain('<img src=x');
        expect((window as unknown as Record<string, unknown>).__xss).toBeUndefined();
    });

    it('renders one line per series plus a legend with the series names', () => {
        const wrapper = mountChart({ series: SERIES, title: 'CTA clicks over time' });

        const svg = wrapper.get('[data-testid="analytics-chart-svg"]');
        expect(svg.attributes('aria-label')).toBe('Line chart: CTA clicks over time');
        expect(svg.findAll('polyline')).toHaveLength(2);

        const legend = wrapper.get('[data-testid="analytics-chart-legend"]');
        expect(legend.findAll('li').map((item) => item.text())).toEqual(['GitHub', 'Demo']);
    });

    it('prefers the series prop when both series and data are given (mutual exclusivity guard)', () => {
        const wrapper = mountChart({ series: SERIES, data: DATA });

        expect(wrapper.get('[data-testid="analytics-chart-svg"]').findAll('polyline')).toHaveLength(
            2,
        );
        expect(wrapper.find('[data-testid="analytics-chart-legend"]').exists()).toBe(true);
        expect(wrapper.findAll('rect')).toHaveLength(0);
    });

    it('shows the empty state when the series array is empty or every series has no points', () => {
        const emptyWrapper = mountChart({ series: [] });
        expect(emptyWrapper.get('[data-testid="analytics-chart-empty"]').text()).toBe(
            'No data available',
        );

        const pointlessWrapper = mountChart({ series: [{ name: 'GitHub', points: [] }] });
        expect(pointlessWrapper.get('[data-testid="analytics-chart-empty"]').text()).toBe(
            'No data available',
        );
    });

    it('aligns series with different date ranges on the union of the x-axis labels', () => {
        const wrapper = mountChart({
            series: [
                {
                    name: 'GitHub',
                    points: [
                        { label: 'Jun 01', value: 5 },
                        { label: 'Jun 08', value: 8 },
                    ],
                },
                {
                    name: 'Demo',
                    points: [
                        { label: 'Jun 01', value: 3 },
                        { label: 'Jun 08', value: 2 },
                        { label: 'Jun 15', value: 9 },
                    ],
                },
            ],
        });

        const svg = wrapper.get('[data-testid="analytics-chart-svg"]');
        // The x-axis carries the union of the labels, not just series[0]'s.
        expect(svg.findAll('text').map((node) => node.text())).toEqual([
            'Jun 01',
            'Jun 08',
            'Jun 15',
        ]);

        // Each series plots at the union slot centers ((640 - 2*20)/3 = 200
        // px apart): the short series keeps its two points on slots 0-1 and
        // skips the missing slot 2, the full series spans all three slots.
        const polylines = svg.findAll('polyline');
        expect(polylines).toHaveLength(2);
        expect(xsOf(polylines[0])).toEqual([120, 320]);
        expect(xsOf(polylines[1])).toEqual([120, 320, 520]);
    });

    it('renders hostile series names as plain text nodes in the legend (XSS)', () => {
        const wrapper = mountChart({
            series: [
                { name: '<img src=x onerror="window.__xss=1">', points: [{ label: 'a', value: 1 }] },
            ],
        });

        const legend = wrapper.get('[data-testid="analytics-chart-legend"]');
        expect(legend.text()).toContain('<img src=x onerror="window.__xss=1">');
        expect(wrapper.html()).not.toContain('<img src=x');
        expect((window as unknown as Record<string, unknown>).__xss).toBeUndefined();
    });
});
