import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PublicSearchAutocomplete from '@shared-public/PublicSearchAutocomplete.vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
}));

const RESULTS = {
    data: [
        { id: 1, title: 'Laravel Tips', slug: 'laravel-tips', published_at: '2026-06-15T00:00:00Z' },
        { id: 2, title: 'Vue Patterns', slug: 'vue-patterns', published_at: '2026-05-02T00:00:00Z' },
    ],
};

describe('PublicSearchAutocomplete', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.stubGlobal('fetch', vi.fn());
        vi.mocked(router.visit).mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('shows the skeleton immediately on the first keystroke', async () => {
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');

        expect(wrapper.findAll('.skeleton').length).toBeGreaterThan(0);
    });

    it('fetches after the debounce with the query and result limit', async () => {
        const fetchMock = vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify(RESULTS), { status: 200 }),
        );
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toContain('/api/search?q=lar');
        expect(fetchMock.mock.calls[0][0]).toContain('per_page=8');

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });
    });

    it('highlights the first result after fetch', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(
                wrapper.get('[data-testid="search-autocomplete-result-0"]').classes(),
            ).toContain('search-autocomplete__result--highlighted');
        });
    });

    it('navigates to the detail URL when Enter hits a highlighted result', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search', result_url_prefix: '/blog/' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'Enter',
        });

        expect(router.visit).toHaveBeenCalledWith('/blog/laravel-tips');
    });

    it('submits the query when Enter has no highlighted result', async () => {
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('vue');
        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'Enter',
        });

        expect(router.visit).not.toHaveBeenCalled();
        expect(wrapper.emitted('submit')).toEqual([['vue']]);
    });

    it('renders the publication meta as "published: dd.mm.yyyy"', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });

        // The fixed lowercase label + numeric date carry no month names
        // and no translation keys (user rework 2026-08-16).
        expect(wrapper.get('.search-autocomplete__result-meta').text()).toBe(
            'published: 15.06.2026',
        );
    });

    it('moves the highlight with the arrow keys', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });

        expect(
            wrapper.get('[data-testid="search-autocomplete-result-1"]').classes(),
        ).toContain('search-autocomplete__result--highlighted');
    });

    it('shows the no-results message for an empty payload', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: [] }), { status: 200 }),
        );
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('zzz');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.get('.search-autocomplete__empty').text()).toBe('No matches found.');
        });
    });

    it('shows the error state on a failed fetch and retries', async () => {
        vi.mocked(fetch)
            .mockResolvedValueOnce(new Response('nope', { status: 500 }))
            .mockResolvedValueOnce(new Response(JSON.stringify(RESULTS), { status: 200 }));

        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.get('.search-autocomplete__error').text()).toContain(
                'Search unavailable.',
            );
        });

        await wrapper.get('[data-testid="search-autocomplete-retry"]').trigger('click');
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });
    });

    it('closes the dropdown on Escape', async () => {
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        expect(wrapper.find('[data-testid="search-autocomplete-dropdown"]').exists()).toBe(true);

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'Escape',
        });

        expect(wrapper.find('[data-testid="search-autocomplete-dropdown"]').exists()).toBe(false);
    });

    it('renders custom result rows through the result slot', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mount(PublicSearchAutocomplete, {
            props: { fetch_url: '/api/search' },
            slots: {
                result: `
                    <template #result="{ item, highlighted }">
                        <span data-testid="custom-result" :data-highlighted="highlighted">{{ item.title }}</span>
                    </template>
                `,
            },
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid="custom-result"]')).toHaveLength(2);
        });
        expect(wrapper.get('[data-testid="custom-result"]').text()).toBe('Laravel Tips');
    });
});
