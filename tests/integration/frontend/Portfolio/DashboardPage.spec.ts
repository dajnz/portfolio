import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { pageProps } = vi.hoisted(() => ({
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
    router: { visit: vi.fn() },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import DashboardPage from '@portfolio/Admin/Pages/DashboardPage.vue';

const STATS = {
    total_views: 12847,
    blog_views: 4820,
    projects_count: 12,
    articles_count: 34,
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(DashboardPage, {
        props: { stats: STATS, ...props },
    });
}

describe('DashboardPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        window.localStorage.clear();
    });

    it('renders within the admin layout with the dashboard heading', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="dashboard-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="dashboard-page"] h1').text()).toBe('Dashboard Overview');
    });

    it('loads the four stat cards with the backend values', () => {
        const wrapper = mountPage();

        const cards = wrapper.findAll('[data-testid^="dashboard-page-stats-card-"]');
        expect(cards).toHaveLength(4);
        expect(cards[0].text()).toContain('Total Views');
        expect(cards[0].text()).toContain('12847');
        expect(cards[1].text()).toContain('Blog Views');
        expect(cards[1].text()).toContain('4820');
        expect(cards[2].text()).toContain('Projects');
        expect(cards[2].text()).toContain('12');
        expect(cards[3].text()).toContain('Articles');
        expect(cards[3].text()).toContain('34');
    });

    it('defaults the stat values to zero when the prop is absent', () => {
        const wrapper = mountPage({ stats: undefined });

        const cards = wrapper.findAll('[data-testid^="dashboard-page-stats-card-"]');
        for (const card of cards) {
            expect(card.text()).toContain('0');
        }
    });

    it('renders the quick links with their target sections', () => {
        const wrapper = mountPage();

        const links = wrapper.findAll('[data-testid^="dashboard-page-quick-links-link-"]');
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/admin/projects/create',
            '/admin/blog/create',
            '/admin/feedback',
            '/admin/backups',
        ]);
        expect(links[0].text()).toBe('New Project');
        expect(links[2].text()).toBe('Unread Feedback');
    });
});
