import StatsCards, { type StatCardData } from '@portfolio-admin/StatsCards.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

function stats(): Array<StatCardData> {
    return [
        { label: 'Total Views', value: 12847, isLoading: false },
        { label: 'Blog Views', value: 4820, isLoading: false },
        { label: 'Projects', value: 12, isLoading: false },
        { label: 'Articles', value: 34, isLoading: false },
    ];
}

describe('StatsCards', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders a card per stat with label, value and optional trend', () => {
        const wrapper = mount(StatsCards, {
            props: { stats: stats() },
        });

        expect(wrapper.findAll('[data-testid^="stats-cards-card-"]')).toHaveLength(4);

        const card = wrapper.get('[data-testid="stats-cards-card-0"]');
        expect(card.text()).toContain('Total Views');
        expect(card.text()).toContain('12847');
        expect(card.classes()).toContain('border-l-primary-600');
    });

    it('renders the trend line when provided', () => {
        const withTrend = stats();
        withTrend[0].trend = '+12.5%';

        const wrapper = mount(StatsCards, { props: { stats: withTrend } });

        expect(wrapper.get('[data-testid="stats-cards-card-0"]').text()).toContain('+12.5%');
    });

    it('renders skeleton cards while a stat is loading', () => {
        const loading = stats();
        loading[1].isLoading = true;

        const wrapper = mount(StatsCards, { props: { stats: loading } });

        expect(wrapper.find('[data-testid="stats-cards-skeleton-label"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="stats-cards-skeleton-value"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="stats-cards-card-0"]').text()).toContain('12847');
    });

    it('renders the per-card error state and emits retry when the value is absent', async () => {
        const broken = stats();
        broken[2] = { label: 'Projects', isLoading: false };

        const wrapper = mount(StatsCards, { props: { stats: broken } });

        expect(wrapper.find('[data-testid="stats-cards-error-2"]').exists()).toBe(true);

        await wrapper.get('[data-testid="stats-cards-error-2-action"]').trigger('click');

        expect(wrapper.emitted('retry')).toEqual([[broken[2]]]);
    });
});
