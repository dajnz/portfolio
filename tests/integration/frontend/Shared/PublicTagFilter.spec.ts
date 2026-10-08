import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PublicTagFilter from '@shared-public/PublicTagFilter.vue';

const SUGGESTIONS = [
    { id: 1, name: 'Laravel', type: 'tag' },
    { id: 2, name: 'PHP', type: 'skill' },
    { id: 3, name: 'Laravel 12', type: 'skill' },
];

describe('PublicTagFilter', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('renders the selected tags as removable pills', () => {
        const wrapper = mount(PublicTagFilter, {
            props: {
                modelValue: [
                    { id: 1, name: 'Laravel', type: 'tag' },
                    { id: 2, name: 'PHP', type: 'skill' },
                ],
                fetch_url: '/api/blog-tags/search',
            },
        });

        expect(wrapper.findAll('[data-testid^="tag-filter-pill-"].badge')).toHaveLength(2);
        expect(wrapper.text()).toContain('Laravel');
        expect(wrapper.text()).toContain('PHP');
    });

    it('emits update without the removed pill', async () => {
        const wrapper = mount(PublicTagFilter, {
            props: {
                modelValue: [
                    { id: 1, name: 'Laravel', type: 'tag' },
                    { id: 2, name: 'PHP', type: 'skill' },
                ],
                fetch_url: '/api/blog-tags/search',
            },
        });

        await wrapper.get('[data-testid="tag-filter-pill-0-remove"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[{ id: 2, name: 'PHP', type: 'skill' }]],
        ]);
    });

    it('labels each remove button with the tag name and announces the removal', async () => {
        const wrapper = mount(PublicTagFilter, {
            props: {
                modelValue: [{ id: 1, name: 'Laravel', type: 'tag' }],
                fetch_url: '/api/blog-tags/search',
            },
        });

        const remove = wrapper.get('[data-testid="tag-filter-pill-0-remove"]');
        expect(remove.attributes('aria-label')).toBe('Remove Laravel');

        await remove.trigger('click');

        expect(wrapper.get('[data-testid="tag-filter-status"]').text()).toBe(
            'Laravel filter removed',
        );
    });

    it('fetches suggestions after the debounce and filters out selected tags', async () => {
        const fetchMock = vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify(SUGGESTIONS), { status: 200 }),
        );
        const wrapper = mount(PublicTagFilter, {
            props: {
                modelValue: [{ id: 1, name: 'Laravel', type: 'tag' }],
                fetch_url: '/api/blog-tags/search',
            },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledWith('/api/blog-tags/search?q=lar', expect.anything());

        // The selected Laravel tag is excluded, the skill one stays.
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tag-filter-suggestion-"]')).toHaveLength(2);
        });
        expect(wrapper.text()).toContain('PHP');
        expect(wrapper.text()).toContain('Laravel 12');
    });

    it('keeps suggestions with the same id but different type', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(PublicTagFilter, {
            props: {
                modelValue: [{ id: 1, name: 'Laravel', type: 'skill' }],
                fetch_url: '/api/blog-tags/search',
            },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        // The tag-typed Laravel (id 1) is still offered because the selected
        // one has type=skill.
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tag-filter-suggestion-"]')).toHaveLength(3);
        });
    });

    it('adds a tag on suggestion click, clears the input and keeps focus', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(PublicTagFilter, {
            props: { modelValue: [], fetch_url: '/api/blog-tags/search' },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tag-filter-suggestion-"]')).toHaveLength(3);
        });

        await wrapper.get('[data-testid="tag-filter-suggestion-0"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[{ id: 1, name: 'Laravel', type: 'tag' }]],
        ]);
        expect((wrapper.get('[data-testid="tag-filter-input"]').element as HTMLInputElement).value).toBe('');
    });

    it('selects a highlighted suggestion with Enter', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(PublicTagFilter, {
            props: { modelValue: [], fetch_url: '/api/blog-tags/search' },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tag-filter-suggestion-"]')).toHaveLength(3);
        });

        await wrapper.get('[data-testid="tag-filter-input"]').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.get('[data-testid="tag-filter-input"]').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.get('[data-testid="tag-filter-input"]').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[{ id: 2, name: 'PHP', type: 'skill' }]],
        ]);
    });

    it('removes the last tag on Backspace with an empty input', async () => {
        const wrapper = mount(PublicTagFilter, {
            props: {
                modelValue: [{ id: 1, name: 'Laravel', type: 'tag' }],
                fetch_url: '/api/blog-tags/search',
            },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').trigger('keydown', {
            key: 'Backspace',
        });

        expect(wrapper.emitted('update:modelValue')).toEqual([[[]]]);
    });

    it('does not fetch below min_chars_for_search', async () => {
        const fetchMock = vi.mocked(fetch);
        const wrapper = mount(PublicTagFilter, {
            props: { modelValue: [], fetch_url: '/api/skills/search', min_chars_for_search: 2 },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('p');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.find('[data-testid="tag-filter-dropdown"]').exists()).toBe(false);
    });

    it('shows the error state and retries', async () => {
        vi.mocked(fetch)
            .mockResolvedValueOnce(new Response('nope', { status: 500 }))
            .mockResolvedValueOnce(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(PublicTagFilter, {
            props: { modelValue: [], fetch_url: '/api/blog-tags/search' },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.get('.tag-filter__error').text()).toContain('Could not load tags.');
        });

        await wrapper.get('[data-testid="tag-filter-retry"]').trigger('click');
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tag-filter-suggestion-"]')).toHaveLength(3);
        });
    });

    it('shows the no-tags message when the response is empty', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify([]), { status: 200 }));
        const wrapper = mount(PublicTagFilter, {
            props: { modelValue: [], fetch_url: '/api/blog-tags/search' },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('zzz');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.get('.tag-filter__empty').text()).toBe('No tags found.');
        });
    });

    it('closes the dropdown on Escape', async () => {
        const wrapper = mount(PublicTagFilter, {
            props: { modelValue: [], fetch_url: '/api/skills/search' },
        });

        await wrapper.get('[data-testid="tag-filter-input"]').setValue('php');
        expect(wrapper.find('[data-testid="tag-filter-dropdown"]').exists()).toBe(true);

        await wrapper.get('[data-testid="tag-filter-input"]').trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('[data-testid="tag-filter-dropdown"]').exists()).toBe(false);
    });
});
