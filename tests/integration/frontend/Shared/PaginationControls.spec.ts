import { type VueWrapper, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PaginationControls from '@shared-admin/PaginationControls.vue';

const CURSORS = {
    first: { id: 1, sort_value: 'a' },
    last: { id: 2, sort_value: 'z' },
    page: 1,
};

function mountPagination(props: Record<string, unknown> = {}) {
    return mount(PaginationControls, {
        props: { total: 100, per_page: 10, current_page: 1, ...props },
    });
}

function pageNumbers(wrapper: VueWrapper): number[] {
    return wrapper
        .findAll('[data-testid^="pagination-page-"]')
        .map((item) => Number(item.text()));
}

describe('PaginationControls', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('is hidden when the data fits on a single page', () => {
        const wrapper = mountPagination({ total: 5, per_page: 10 });

        expect(wrapper.find('[data-testid="pagination"]').exists()).toBe(false);
    });

    it('lists every page without ellipsis when there are ten or fewer pages', () => {
        const wrapper = mountPagination({ total: 50, per_page: 10 });

        expect(pageNumbers(wrapper)).toEqual([1, 2, 3, 4, 5]);
        expect(wrapper.find('[data-testid^="pagination-ellipsis-"]').exists()).toBe(false);
    });

    it('shows the first pages, an ellipsis and the last pages on the first page', () => {
        const wrapper = mountPagination({ total: 100, per_page: 1, current_page: 1 });

        expect(pageNumbers(wrapper)).toEqual([1, 2, 3, 4, 5, 98, 99, 100]);
        expect(wrapper.get('[data-testid="pagination-ellipsis-5"]').text()).toBe('…');
    });

    it('shows the first pages, the current window and the last pages in the middle', () => {
        const wrapper = mountPagination({ total: 100, per_page: 1, current_page: 5 });

        expect(pageNumbers(wrapper)).toEqual([1, 2, 3, 4, 5, 6, 98, 99, 100]);
        expect(wrapper.get('[data-testid="pagination-ellipsis-3"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="pagination-ellipsis-7"]').exists()).toBe(true);
    });

    it('shows the first pages, an ellipsis and the tail pages near the end', () => {
        const wrapper = mountPagination({ total: 100, per_page: 1, current_page: 98 });

        expect(pageNumbers(wrapper)).toEqual([1, 2, 3, 96, 97, 98, 99, 100]);
        expect(wrapper.get('[data-testid="pagination-ellipsis-3"]').exists()).toBe(true);
    });

    it('disables the previous button on the first page', () => {
        const wrapper = mountPagination({ total: 50, per_page: 10, current_page: 1 });

        expect(wrapper.get('[data-testid="pagination-prev"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid="pagination-next"]').attributes('disabled')).toBeUndefined();
    });

    it('disables the next button on the last page', () => {
        const wrapper = mountPagination({ total: 50, per_page: 10, current_page: 5 });

        expect(wrapper.get('[data-testid="pagination-prev"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.get('[data-testid="pagination-next"]').attributes('disabled')).toBeDefined();
    });

    it('emits the first cursor from the previous button', async () => {
        const wrapper = mountPagination({
            total: 100,
            per_page: 1,
            current_page: 2,
            cursors: CURSORS,
        });

        await wrapper.get('[data-testid="pagination-prev"]').trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([[{ cursor: CURSORS.first }]]);
    });

    it('emits the last cursor from the next button', async () => {
        const wrapper = mountPagination({
            total: 100,
            per_page: 1,
            current_page: 2,
            cursors: CURSORS,
        });

        await wrapper.get('[data-testid="pagination-next"]').trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([[{ cursor: CURSORS.last }]]);
    });

    it('emits the page number when a page button is clicked', async () => {
        const wrapper = mountPagination({ total: 50, per_page: 10, current_page: 1 });

        await wrapper.get('[data-testid="pagination-page-3"]').trigger('click');

        expect(wrapper.emitted('navigate')).toEqual([[{ page: 3 }]]);
    });

    it('renders the current page as a highlighted span', () => {
        const wrapper = mountPagination({ total: 50, per_page: 10, current_page: 1 });

        const current = wrapper.get('[data-testid="pagination-page-1"]');

        expect(current.element.tagName).toBe('SPAN');
        expect(current.classes()).toContain('bg-primary-600');
        expect(wrapper.get('[data-testid="pagination-page-2"]').element.tagName).toBe('BUTTON');
    });

    it('labels the previous and next buttons for assistive technology', () => {
        const wrapper = mountPagination({ total: 50, per_page: 10, current_page: 1 });

        expect(wrapper.get('[data-testid="pagination-prev"]').attributes('aria-label')).toBe(
            'Previous',
        );
        expect(wrapper.get('[data-testid="pagination-next"]').attributes('aria-label')).toBe(
            'Next',
        );
    });
});
