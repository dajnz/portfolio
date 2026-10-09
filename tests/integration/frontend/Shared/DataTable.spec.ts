import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import DataTable from '@shared-admin/DataTable.vue';

const COLUMNS = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'role', label: 'Role' },
];

const ROWS = [
    { id: 1, name: 'Alex', role: 'Admin' },
    { id: 2, name: 'Jane', role: 'Editor' },
];

function mountTable(props: Record<string, unknown> = {}, slots: Record<string, string> = {}) {
    return mount(DataTable, {
        props: { columns: COLUMNS, rows: ROWS, selected_ids: [], ...props },
        slots,
    });
}

describe('DataTable', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the column headers and every row', () => {
        const wrapper = mountTable();

        expect(wrapper.text()).toContain('Name');
        expect(wrapper.text()).toContain('Role');
        expect(wrapper.text()).toContain('Alex');
        expect(wrapper.text()).toContain('Jane');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('passes the row and value to cell-{key} scoped slots', () => {
        const wrapper = mountTable({}, {
            'cell-name': `
                <template #cell-name="{ row, value }">
                    <span
                        data-testid="custom-name"
                        :data-row-id="row.id"
                    >{{ value }}</span>
                </template>
            `,
        });

        const cells = wrapper.findAll('[data-testid="custom-name"]');

        expect(cells).toHaveLength(2);
        expect(cells[0].text()).toBe('Alex');
        expect(cells[0].attributes('data-row-id')).toBe('1');
        expect(cells[1].text()).toBe('Jane');
    });

    it('renders the row-actions slot for each row', () => {
        const wrapper = mountTable({}, {
            'row-actions': '<button data-testid="row-action">Edit</button>',
        });

        expect(wrapper.findAll('[data-testid="row-action"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('Actions');
    });

    it('cycles a sortable column through asc and desc only (ADR-0024)', async () => {
        const wrapper = mountTable();
        const header = wrapper.get('th button');

        await header.trigger('click');
        expect(wrapper.emitted('update:sort_column')).toEqual([['name']]);
        expect(wrapper.emitted('update:sort_direction')).toEqual([['asc']]);

        await wrapper.setProps({ sort_column: 'name', sort_direction: 'asc' });
        await header.trigger('click');
        expect(wrapper.emitted('update:sort_direction')).toEqual([['asc'], ['desc']]);
        expect(wrapper.emitted('update:sort_column')).toHaveLength(1);

        // desc → asc — there is no unsorted state anymore.
        await wrapper.setProps({ sort_column: 'name', sort_direction: 'desc' });
        await header.trigger('click');
        expect(wrapper.emitted('update:sort_column')).toEqual([['name']]);
        expect(wrapper.emitted('update:sort_direction')).toEqual([['asc'], ['desc'], ['asc']]);
    });

    it('sets aria-sort on sortable headers only', async () => {
        const wrapper = mountTable();
        const headers = wrapper.findAll('thead th');

        expect(headers[0].attributes('aria-sort')).toBe('none');
        expect(headers[1].attributes('aria-sort')).toBeUndefined();

        await wrapper.setProps({ sort_column: 'name', sort_direction: 'asc' });
        expect(headers[0].attributes('aria-sort')).toBe('ascending');

        await wrapper.setProps({ sort_direction: 'desc' });
        expect(headers[0].attributes('aria-sort')).toBe('descending');
    });

    it('shows the sort indicator icon only on the active sort column', async () => {
        const wrapper = mountTable();

        expect(wrapper.find('thead th svg').exists()).toBe(false);

        await wrapper.setProps({ sort_column: 'name', sort_direction: 'asc' });
        expect(wrapper.find('thead th svg').exists()).toBe(true);

        await wrapper.setProps({ sort_direction: 'desc' });
        expect(wrapper.find('thead th svg').exists()).toBe(true);
    });

    it('selects all visible rows through the select-all checkbox', async () => {
        const wrapper = mountTable({ selectable: true });

        await wrapper.get('[data-testid="data-table-select-all"]').setValue(true);

        expect(wrapper.emitted('update:selected_ids')).toEqual([[[1, 2]]]);
    });

    it('shows an indeterminate select-all when only some rows are selected', () => {
        const wrapper = mountTable({ selectable: true, selected_ids: [1] });

        const input = wrapper.get('[data-testid="data-table-select-all"]')
            .element as HTMLInputElement;

        expect(input.indeterminate).toBe(true);
    });

    it('toggles a single row in selected_ids via its checkbox', async () => {
        const wrapper = mountTable({ selectable: true, selected_ids: [1] });

        await wrapper.get('[data-testid="data-table-row-2"]').setValue(true);
        expect(wrapper.emitted('update:selected_ids')).toEqual([[[1, 2]]]);

        await wrapper.setProps({ selected_ids: [1, 2] });
        await wrapper.get('[data-testid="data-table-row-1"]').setValue(false);
        expect(wrapper.emitted('update:selected_ids')).toEqual([[[1, 2]], [[2]]]);
    });

    it('emits row-click when a data cell is clicked', async () => {
        const wrapper = mountTable();

        await wrapper.findAll('tbody tr')[0].trigger('click');

        expect(wrapper.emitted('row-click')).toEqual([[ROWS[0]]]);
    });

    it('does not emit row-click when the checkbox cell is clicked', async () => {
        const wrapper = mountTable({ selectable: true });

        await wrapper.get('[data-testid="data-table-row-1"]').trigger('click');

        expect(wrapper.emitted('row-click')).toBeUndefined();
    });

    it('adds the zebra class to odd rows', () => {
        const wrapper = mountTable();

        const firstRow = wrapper.findAll('tbody tr')[0];

        expect(firstRow.classes()).toContain('odd:bg-surface-secondary');
        expect(firstRow.classes()).toContain('dark:odd:bg-surface-dark-secondary');
    });

    it('styles trashed rows with reduced opacity and italics', () => {
        const wrapper = mountTable({ trashed_ids: [2] });

        const rows = wrapper.findAll('tbody tr');

        expect(rows[1].classes()).toContain('opacity-50');
        expect(rows[1].classes()).toContain('italic');
        expect(rows[0].classes()).not.toContain('opacity-50');
    });

    it('renders skeleton rows while loading and keeps the headers visible', () => {
        const wrapper = mountTable({ is_loading: true });

        expect(wrapper.findAll('[data-testid^="data-table-skeleton-row-"]')).toHaveLength(4);
        expect(wrapper.find('[data-testid="data-table-skeleton-row-1"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Name');
        expect(wrapper.text()).toContain('Role');
    });

    it('shows the empty state with forwarded props and emits empty-action', async () => {
        const wrapper = mountTable({
            rows: [],
            empty_mode: 'no-results',
            empty_message: 'No posts yet',
            empty_action_label: 'Create post',
        });

        const empty = wrapper.get('[data-testid="data-table-empty"]');

        expect(empty.text()).toContain('No posts yet');
        expect(empty.text()).toContain('Create post');

        await wrapper.get('[data-testid="data-table-empty-action"]').trigger('click');

        expect(wrapper.emitted('empty-action')).toHaveLength(1);
    });

    it('renders the empty action as a link when empty_action_url is set', () => {
        const wrapper = mountTable({
            rows: [],
            empty_action_label: 'Create post',
            empty_action_url: '/admin/posts/create',
        });

        const action = wrapper.get('[data-testid="data-table-empty-action"]');

        expect(action.element.tagName).toBe('A');
        expect(action.attributes('href')).toBe('/admin/posts/create');
    });

    it('renders the bulk-actions slot only when selectable and selection is non-empty', async () => {
        const wrapper = mountTable({ selectable: true }, {
            'bulk-actions': '<div data-testid="bulk-bar">Bulk bar</div>',
        });

        expect(wrapper.find('[data-testid="bulk-bar"]').exists()).toBe(false);

        await wrapper.setProps({ selected_ids: [1] });
        expect(wrapper.find('[data-testid="bulk-bar"]').exists()).toBe(true);

        await wrapper.setProps({ selectable: false });
        expect(wrapper.find('[data-testid="bulk-bar"]').exists()).toBe(false);
    });
});
