import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AdminSearchAutocomplete from '@shared-admin/AdminSearchAutocomplete.vue';

const { router: mockedRouter } = vi.hoisted(() => ({
    router: { visit: vi.fn() },
}));

vi.mock('@inertiajs/vue3', () => ({ router: mockedRouter }));

const RESULTS = {
    data: [
        { id: 1, title: 'Laravel Tips', published_at: '2026-06-15T00:00:00Z' },
        { id: 2, title: 'Vue Patterns', published_at: '2026-05-02T00:00:00Z' },
    ],
};

function mountSearchAutocomplete(props = {}, slots = {}, options = {}) {
    return mount(AdminSearchAutocomplete, {
        props: { fetch_url: '/api/search', ...props },
        slots,
        ...options,
    });
}

describe('AdminSearchAutocomplete', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.stubGlobal('fetch', vi.fn());
        mockedRouter.visit.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('opens the dropdown state on focus', async () => {
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('focus');

        expect(wrapper.get('[data-testid="search-autocomplete-input"]').attributes('aria-expanded')).toBe('true');
    });

    it('shows the skeleton immediately on the first keystroke before the debounce fires', async () => {
        const fetchMock = vi.mocked(fetch);
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(1);

        expect(wrapper.findAll('[data-testid="base-skeleton"]').length).toBeGreaterThan(0);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('fetches after the debounce with the encoded query, the result limit and JSON accept header', async () => {
        const fetchMock = vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify(RESULTS), { status: 200 }),
        );
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/search?q=lar&per_page=8');
        expect(fetchMock.mock.calls[0][1]?.headers).toEqual({ Accept: 'application/json' });

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });
    });

    it('renders the result label with the formatted published_at meta text', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });
        expect(wrapper.get('[data-testid="search-autocomplete-result-0"]').text()).toContain('Laravel Tips');
        expect(wrapper.get('[data-testid="search-autocomplete-result-0"]').text()).toContain('15.06.2026');
        expect(wrapper.get('[data-testid="search-autocomplete-result-0"]').text()).not.toContain('2026-06-15');
    });

    it('omits the meta suffix for rows without the meta value', async () => {
        const drafts = {
            data: [
                { id: 1, title: 'Laravel Tips', published_at: null },
                { id: 2, title: 'Vue Patterns', published_at: null },
            ],
        };
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(drafts), { status: 200 }));
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });
        expect(wrapper.get('[data-testid="search-autocomplete-result-0"]').text()).toContain('Laravel Tips');
        expect(wrapper.get('[data-testid="search-autocomplete-result-0"]').text()).not.toContain('.');
    });

    it('navigates to the edit URL when Enter hits a highlighted result', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mountSearchAutocomplete({ result_url_prefix: '/admin/blog/posts/' });

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'Enter',
        });

        expect(mockedRouter.visit).toHaveBeenCalledWith('/admin/blog/posts/1/edit');
    });

    it('submits the query when Enter has no highlighted result', async () => {
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('vue');
        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'Enter',
        });

        expect(mockedRouter.visit).not.toHaveBeenCalled();
        expect(wrapper.emitted('submit')).toEqual([['vue']]);
    });

    it('cycles the highlight with the arrow keys', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'ArrowUp',
        });
        expect(
            wrapper.get('[data-testid="search-autocomplete-result-1"]').classes(),
        ).toContain('bg-primary-50');

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'ArrowUp',
        });
        expect(
            wrapper.get('[data-testid="search-autocomplete-result-0"]').classes(),
        ).toContain('bg-primary-50');
    });

    it('closes the dropdown and blurs the input on Escape', async () => {
        const wrapper = mountSearchAutocomplete({}, {}, { attachTo: document.body });
        const input = wrapper.get('[data-testid="search-autocomplete-input"]');
        (input.element as HTMLElement).focus();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        expect(wrapper.find('[data-testid="search-autocomplete-dropdown"]').exists()).toBe(true);

        await input.trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('[data-testid="search-autocomplete-dropdown"]').exists()).toBe(false);
        expect(document.activeElement).not.toBe(input.element);
        wrapper.unmount();
    });

    it('closes the dropdown after the blur delay', async () => {
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        expect(wrapper.find('[data-testid="search-autocomplete-dropdown"]').exists()).toBe(true);

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('blur');
        await vi.advanceTimersByTimeAsync(150);

        expect(wrapper.find('[data-testid="search-autocomplete-dropdown"]').exists()).toBe(false);
    });

    it('shows the no-matches message for an empty payload', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: [] }), { status: 200 }),
        );
        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('zzz');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('No matches found.');
        });
    });

    it('shows the error state on a failed fetch and retries', async () => {
        vi.mocked(fetch)
            .mockResolvedValueOnce(new Response('nope', { status: 500 }))
            .mockResolvedValueOnce(new Response(JSON.stringify(RESULTS), { status: 200 }));

        const wrapper = mountSearchAutocomplete();

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('Search unavailable.');
        });

        await wrapper.get('[data-testid="search-autocomplete-retry"]').trigger('click');

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="search-autocomplete-result-"]')).toHaveLength(2);
        });
    });

    it('renders custom result rows through the result slot', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mountSearchAutocomplete(
            {},
            {
                result: `
                    <template #result="{ item, highlighted }">
                        <span data-testid="custom-result" :data-highlighted="highlighted">{{ item.title }}</span>
                    </template>
                `,
            },
        );

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid="custom-result"]')).toHaveLength(2);
        });
        expect(wrapper.get('[data-testid="custom-result"]').text()).toBe('Laravel Tips');
    });

    it('passes the highlighted flag to the result slot', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(RESULTS), { status: 200 }));
        const wrapper = mountSearchAutocomplete(
            {},
            {
                result: `
                    <template #result="{ item, highlighted }">
                        <span data-testid="custom-result" :data-highlighted="highlighted">{{ item.title }}</span>
                    </template>
                `,
            },
        );

        await wrapper.get('[data-testid="search-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid="custom-result"]')).toHaveLength(2);
        });

        expect(wrapper.findAll('[data-testid="custom-result"]')[0].attributes('data-highlighted')).toBe('true');

        await wrapper.get('[data-testid="search-autocomplete-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });

        expect(wrapper.findAll('[data-testid="custom-result"]')[1].attributes('data-highlighted')).toBe('true');
        expect(wrapper.findAll('[data-testid="custom-result"]')[0].attributes('data-highlighted')).toBe('false');
    });
});
