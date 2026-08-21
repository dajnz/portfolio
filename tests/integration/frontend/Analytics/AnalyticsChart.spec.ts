import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import AnalyticsChart from '@analytics-admin/AnalyticsChart.vue';

const DATA = [
    { label: 'Jun 01', value: 842 },
    { label: 'Jun 08', value: 1200 },
    { label: 'Jun 15', value: 500 },
];

function mountChart(props: Record<string, unknown> = {}) {
    return mount(AnalyticsChart, {
        props: { data: DATA, ...props },
    });
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
});
