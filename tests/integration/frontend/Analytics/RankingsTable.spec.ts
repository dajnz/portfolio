import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import RankingsTable from '@analytics-admin/RankingsTable.vue';

const COLUMNS = [
    { key: 'page_url', label: 'Page' },
    { key: 'avg', label: 'Avg' },
    { key: 'min', label: 'Min' },
    { key: 'max', label: 'Max' },
];

const ROWS = [
    { page_url: '/about', avg: '1m 15s', min: '5s', max: '4m 00s' },
    { page_url: '/blog', avg: '45s', min: '2s', max: '2m 10s' },
];

const VARIANTS = [
    { value: 'top', label: 'Top' },
    { value: 'bottom', label: 'Bottom' },
];

function mountTable(props: Record<string, unknown> = {}) {
    return mount(RankingsTable, {
        props: {
            columns: COLUMNS,
            rows: ROWS,
            variant: 'top',
            variant_options: VARIANTS,
            ...props,
        },
    });
}

describe('RankingsTable', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the column headers and the ranking rows', () => {
        const wrapper = mountTable();

        expect(
            wrapper.findAll('thead th').map((th) => th.text()),
        ).toEqual(['Page', 'Avg', 'Min', 'Max']);

        const rows = wrapper.findAll('tbody tr');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('/about');
        expect(rows[0].text()).toContain('1m 15s');
        expect(rows[1].text()).toContain('/blog');
        expect(rows[1].text()).toContain('2m 10s');
    });

    it('shows the current variant in the dropdown and emits update:variant on change', async () => {
        const wrapper = mountTable();

        const trigger = wrapper.get('[data-testid="rankings-table-variant"]');
        expect(trigger.text()).toBe('Top');

        await trigger.trigger('click');
        await wrapper.get('[data-testid="rankings-table-variant-option-bottom"]').trigger('click');

        expect(wrapper.emitted('update:variant')).toEqual([['bottom']]);
    });

    it('renders skeleton rows while loading', () => {
        const wrapper = mountTable({ is_loading: true });

        expect(
            wrapper.findAll('[data-testid^="rankings-table-table-skeleton-row-"]'),
        ).toHaveLength(4);
        expect(wrapper.find('[data-testid="rankings-table-table-empty"]').exists()).toBe(false);
    });

    it('shows the empty state when there are no rows', () => {
        const wrapper = mountTable({ rows: [] });

        expect(wrapper.get('[data-testid="rankings-table-table-empty"]').text()).toBe(
            'No data available',
        );
    });

    it('shows the error state with a retry button that emits retry', async () => {
        const wrapper = mountTable({ rows: [], error: true });

        const errorState = wrapper.get('[data-testid="rankings-table-table-empty"]');
        expect(errorState.text()).toContain('Could not load rankings');

        await wrapper.get('[data-testid="rankings-table-table-empty-action"]').trigger('click');
        expect(wrapper.emitted('retry')).toHaveLength(1);
    });

    it('applies the test_id prop to the container, dropdown and table', () => {
        const wrapper = mountTable({ test_id: 'top-pages' });

        expect(wrapper.find('[data-testid="top-pages"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="top-pages-variant"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="top-pages-table"]').exists()).toBe(true);
    });
});
