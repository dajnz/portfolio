import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AdminBlogTagPicker from '@shared-admin/AdminBlogTagPicker.vue';

const TAGS = [
    { id: 1, name: 'Laravel', type: 'skill' },
    { id: 2, name: 'PHP', type: 'skill' },
    { id: 3, name: 'Laravel', type: 'tag' },
];

function mountTagPicker(props = {}, slots = {}, options = {}) {
    return mount(AdminBlogTagPicker, {
        props: { modelValue: [], ...props },
        slots,
        ...options,
    });
}

describe('AdminBlogTagPicker', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('opens the dropdown on focus with the start-typing hint', async () => {
        const wrapper = mountTagPicker();

        await wrapper.get('[data-testid="blog-tag-picker-input"]').trigger('focus');

        expect(wrapper.find('[data-testid="blog-tag-picker-dropdown"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Start typing to search');
    });

    it('renders the selected tags as pills with a type-based variant', () => {
        const wrapper = mountTagPicker({ modelValue: [TAGS[0], TAGS[2]] });

        const skillPill = wrapper.get('[data-testid="blog-tag-picker-pill-0"]');
        expect(skillPill.text()).toContain('Laravel');
        expect(skillPill.classes()).toContain('bg-primary-50');
        expect(wrapper.get('[data-testid="blog-tag-picker-pill-1"]').classes()).toContain(
            'bg-slate-100',
        );
    });

    it('removes a tag via the pill x button', async () => {
        const wrapper = mountTagPicker({ modelValue: [TAGS[0], TAGS[1]] });

        await wrapper.get('[data-testid="blog-tag-picker-pill-0-remove"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [TAGS[1]] ]]);
    });

    it('fetches suggestions after the debounce and renders a type badge per row', async () => {
        const fetchMock = vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: TAGS }), { status: 200 }),
        );
        const wrapper = mountTagPicker();

        await wrapper.get('[data-testid="blog-tag-picker-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/blog-tags/search?q=lar');

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="blog-tag-picker-suggestion-"]')).toHaveLength(3);
        });
        expect(wrapper.get('[data-testid="blog-tag-picker-suggestion-0"]').text()).toContain('skill');
        expect(wrapper.get('[data-testid="blog-tag-picker-suggestion-2"]').text()).toContain('tag');
    });

    it('offers the same name with a different type and selects the exact match', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: TAGS }), { status: 200 }),
        );
        const wrapper = mountTagPicker({ modelValue: [TAGS[0]] });

        await wrapper.get('[data-testid="blog-tag-picker-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="blog-tag-picker-suggestion-"]')).toHaveLength(2);
        });
        expect(wrapper.get('[data-testid="blog-tag-picker-suggestion-1"]').text()).toContain('Laravel');

        await wrapper.get('[data-testid="blog-tag-picker-suggestion-1"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [TAGS[0], TAGS[2]] ]]);
    });

    it('removes only the matching type when the same name exists twice', async () => {
        const wrapper = mountTagPicker({ modelValue: [TAGS[0], TAGS[2]] });

        await wrapper.get('[data-testid="blog-tag-picker-pill-0-remove"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [TAGS[2]] ]]);
    });

    it('selects a highlighted suggestion with Enter', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: TAGS }), { status: 200 }),
        );
        const wrapper = mountTagPicker();

        await wrapper.get('[data-testid="blog-tag-picker-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="blog-tag-picker-suggestion-"]')).toHaveLength(3);
        });

        await wrapper.get('[data-testid="blog-tag-picker-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });
        await wrapper.get('[data-testid="blog-tag-picker-input"]').trigger('keydown', {
            key: 'Enter',
        });

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [TAGS[0]] ]]);
    });

    it('removes the last tag on Backspace with an empty query', async () => {
        const wrapper = mountTagPicker({ modelValue: [TAGS[0], TAGS[1]] });

        await wrapper.get('[data-testid="blog-tag-picker-input"]').trigger('keydown', {
            key: 'Backspace',
        });

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [TAGS[0]] ]]);
    });

    it('shows the no-tags message when the response is empty', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: [] }), { status: 200 }),
        );
        const wrapper = mountTagPicker();

        await wrapper.get('[data-testid="blog-tag-picker-input"]').setValue('zzz');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('No tags found.');
        });
    });

    it('shows the error state and retries', async () => {
        vi.mocked(fetch)
            .mockResolvedValueOnce(new Response('nope', { status: 500 }))
            .mockResolvedValueOnce(new Response(JSON.stringify({ data: TAGS }), { status: 200 }));
        const wrapper = mountTagPicker();

        await wrapper.get('[data-testid="blog-tag-picker-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('Could not load tags.');
        });

        await wrapper.get('[data-testid="blog-tag-picker-retry"]').trigger('click');

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="blog-tag-picker-suggestion-"]')).toHaveLength(3);
        });
    });
});
