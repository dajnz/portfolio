import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import AnalyticsMetricBlocks from '@analytics-admin/AnalyticsMetricBlocks.vue';

interface Block {
    key: string;
    label: string;
    value: number | string;
    min?: number | string;
    max?: number | string;
    selected: boolean;
}

const BLOCKS: Array<Block> = [
    { key: 'total_visits', label: 'Total Visits', value: '12,847', selected: true },
    { key: 'unique_visitors', label: 'Unique Visitors', value: '890', selected: false },
    {
        key: 'avg_time_on_page',
        label: 'Avg Time on Page',
        value: '1m 15s',
        min: '3s',
        max: '4m 00s',
        selected: false,
    },
];

function mountBlocks(props: Record<string, unknown> = {}) {
    return mount(AnalyticsMetricBlocks, {
        props: { blocks: BLOCKS, ...props },
    });
}

describe('AnalyticsMetricBlocks', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders every block with its label and whole-time value', () => {
        const wrapper = mountBlocks();

        const blocks = wrapper.findAll('[data-testid^="analytics-metric-blocks-block-"]');
        expect(blocks).toHaveLength(3);
        expect(blocks[0].text()).toContain('Total Visits');
        expect(blocks[0].text()).toContain('12,847');
        expect(blocks[1].text()).toContain('Unique Visitors');
        expect(blocks[1].text()).toContain('890');
        expect(blocks[2].text()).toContain('Avg Time on Page');
        expect(blocks[2].text()).toContain('1m 15s');
    });

    it('renders the min/max line only for the avg blocks that carry it', () => {
        const wrapper = mountBlocks();

        const avgBlock = wrapper.get(
            '[data-testid="analytics-metric-blocks-block-avg_time_on_page"]',
        );
        expect(avgBlock.text()).toContain('Min 3s');
        expect(avgBlock.text()).toContain('Max 4m 00s');

        const plainBlock = wrapper.get(
            '[data-testid="analytics-metric-blocks-block-total_visits"]',
        );
        expect(plainBlock.text()).not.toContain('Min');
        expect(plainBlock.text()).not.toContain('Max');
    });

    it('highlights the selected block and marks it pressed', () => {
        const wrapper = mountBlocks();

        const selected = wrapper.get(
            '[data-testid="analytics-metric-blocks-block-total_visits"]',
        );
        expect(selected.attributes('data-state')).toBe('selected');
        expect(selected.attributes('aria-pressed')).toBe('true');

        const other = wrapper.get(
            '[data-testid="analytics-metric-blocks-block-unique_visitors"]',
        );
        expect(other.attributes('data-state')).toBe('default');
        expect(other.attributes('aria-pressed')).toBe('false');
    });

    it('emits select with the block key on click', async () => {
        const wrapper = mountBlocks();

        await wrapper
            .get('[data-testid="analytics-metric-blocks-block-unique_visitors"]')
            .trigger('click');

        expect(wrapper.emitted('select')).toEqual([['unique_visitors']]);
    });

    it('applies the test_id prop to the container and the blocks', () => {
        const wrapper = mountBlocks({ test_id: 'overview-blocks' });

        expect(wrapper.find('[data-testid="overview-blocks"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="overview-blocks-block-total_visits"]').exists()).toBe(
            true,
        );
    });
});
