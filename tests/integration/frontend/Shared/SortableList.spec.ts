import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import SortableList from '@shared-cross/Components/SortableList/SortableList.vue';

const ITEMS = [
    { id: 1, label: 'First' },
    { id: 2, label: 'Second' },
    { id: 3, label: 'Third' },
];

function mountList(
    props: Record<string, unknown> = {},
    slots: Record<string, unknown> = {},
    items: Array<Record<string, unknown>> = ITEMS,
) {
    return mount(SortableList, {
        props: { modelValue: items, ...props },
        slots: {
            default: `
                <template #default="{ item, index }">
                    <span data-testid="slot-item">{{ index }}:{{ item.label }}</span>
                </template>
            `,
            ...slots,
        },
    });
}

function itemEl(
    wrapper: ReturnType<typeof mountList>,
    id: number,
    prefix = 'sortable-list',
): ReturnType<typeof wrapper.get> {
    return wrapper.get(`[data-testid="${prefix}-item-${id}"]`);
}

describe('SortableList', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the items keyed by the item_key prop with the scoped slot', () => {
        const wrapper = mountList();

        expect(wrapper.findAll('[data-testid^="sortable-list-item-"]')).toHaveLength(3);
        expect(wrapper.get('[data-testid="slot-item"]').text()).toBe('0:First');
    });

    it('emits drag-start with the dragged item', async () => {
        const wrapper = mountList();

        await itemEl(wrapper, 2).trigger('dragstart');

        expect(wrapper.emitted('drag-start')).toEqual([[ITEMS[1]]]);
    });

    it('reorders live on dragover and emits the new order on drop', async () => {
        const wrapper = mountList();

        // Drag First (index 0) onto Second with the cursor in its lower
        // half (clientY > midpoint) — insertion lands AFTER Second.
        await itemEl(wrapper, 1).trigger('dragstart');
        await itemEl(wrapper, 2).trigger('dragover', { clientY: 10 });

        const labels = wrapper.findAll('[data-testid="slot-item"]').map((el) => el.text());
        expect(labels).toEqual(['0:Second', '1:First', '2:Third']);

        await wrapper.get('[data-testid="sortable-list"]').trigger('drop');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[ITEMS[1], ITEMS[0], ITEMS[2]]],
        ]);
        // Same-list drops carry no cross-drag info (second argument null).
        expect(wrapper.emitted('drag-end')).toEqual([
            [[ITEMS[1], ITEMS[0], ITEMS[2]], null],
        ]);
    });

    it('restores the original order when the drag ends without a drop', async () => {
        const wrapper = mountList();

        await itemEl(wrapper, 1).trigger('dragstart');
        await itemEl(wrapper, 2).trigger('dragover', { clientY: 10 });
        await wrapper.get('[data-testid="sortable-list"]').trigger('dragend');

        const labels = wrapper.findAll('[data-testid="slot-item"]').map((el) => el.text());
        expect(labels).toEqual(['0:First', '1:Second', '2:Third']);
        expect(wrapper.emitted('update:modelValue')).toEqual([[ITEMS]]);
    });

    it('only starts dragging after a mousedown on the handle in handle mode', async () => {
        const wrapper = mountList(
            { handle: '.drag-handle' },
            {
                default: `
                    <template #default="{ item }">
                        <button type="button" class="drag-handle" data-testid="handle-${''}">{{ item.label }}</button>
                    </template>
                `,
            },
        );

        // The browser fires dragstart at the drag source (the item), so a
        // dragstart without a preceding handle mousedown is rejected.
        await wrapper.get('[data-testid^="sortable-list-item-"]').trigger('dragstart');

        expect(wrapper.emitted('drag-start')).toBeUndefined();

        // A press on the handle arms the gesture — the subsequent
        // dragstart (target = the item element) is allowed.
        await wrapper.get('.drag-handle').trigger('mousedown');
        await wrapper.get('[data-testid^="sortable-list-item-"]').trigger('dragstart');

        expect(wrapper.emitted('drag-start')).toHaveLength(1);
    });

    it('clears the armed handle gesture when the press is released without a drag', async () => {
        const wrapper = mountList(
            { handle: '.drag-handle' },
            {
                default: `
                    <template #default="{ item }">
                        <button type="button" class="drag-handle" data-testid="handle-${''}">{{ item.label }}</button>
                    </template>
                `,
            },
        );

        await wrapper.get('.drag-handle').trigger('mousedown');
        await wrapper.get('[data-testid^="sortable-list-item-"]').trigger('mouseup');
        await wrapper.get('[data-testid^="sortable-list-item-"]').trigger('dragstart');

        expect(wrapper.emitted('drag-start')).toBeUndefined();
    });

    it('does not reorder when disabled', async () => {
        const wrapper = mountList({ disabled: true });

        expect(itemEl(wrapper, 1).attributes('draggable')).toBe('false');

        await itemEl(wrapper, 1).trigger('dragstart');
        await itemEl(wrapper, 3).trigger('dragover', { clientY: 0 });
        await wrapper.get('[data-testid="sortable-list"]').trigger('drop');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('uses the horizontal axis class for axis="x"', () => {
        const wrapper = mountList({ axis: 'x' });

        expect(wrapper.get('[data-testid="sortable-list"]').classes()).toContain(
            'sortable-list--horizontal',
        );
    });

    it('applies the classes prop overrides in admin context', () => {
        const wrapper = mountList({
            classes: { container: 'divide-y', item: 'py-2' },
        });

        expect(wrapper.get('[data-testid="sortable-list"]').classes()).toContain('divide-y');
        expect(itemEl(wrapper, 1).classes()).toContain('py-2');
    });

    it('accepts a cross-list drop from a list with the same cross scope', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        // The target holds its own items — the cross guard rejects
        // inserting an item the list already contains.
        const targetItems = [
            { id: 4, label: 'Fourth' },
            { id: 5, label: 'Fifth' },
        ];
        const target = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-list',
            },
            {},
            targetItems,
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });
        await target.get('[data-testid="target-list"]').trigger('drop');

        // The target inserts the dragged item (shared reference) and
        // reports the source list in the second drag-end argument.
        expect(target.emitted('update:modelValue')).toEqual([[[...targetItems, ITEMS[0]]]]);
        expect(target.emitted('drag-end')).toEqual([
            [[...targetItems, ITEMS[0]], { item: ITEMS[0], from_list_id: 'list-a' }],
        ]);
    });

    it('keeps the source list silent after a cross-list drop lands', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-list',
            },
            {},
            [{ id: 4, label: 'Fourth' }],
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('drop');
        await source.get('[data-testid="source-list"]').trigger('dragend');

        // The source restores the server order and emits nothing — its
        // own order never changed (the parent moves the item out of it).
        expect(source.emitted('drag-end')).toBeUndefined();
        expect(source.emitted('update:modelValue')).toBeUndefined();
    });

    it('does not accept drops from lists with a different cross scope', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList({
            list_id: 'list-b',
            cross_scope: 'groups',
            test_id: 'target-list',
        });

        await itemEl(source, 1, "source-list").trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });
        await target.get('[data-testid="target-list"]').trigger('drop');

        expect(target.emitted('update:modelValue')).toBeUndefined();
        expect(target.emitted('drag-end')).toBeUndefined();
    });

    it('does not accept cross-list drops without a cross scope', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList({ test_id: 'target-list' });

        await itemEl(source, 1, "source-list").trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });
        await target.get('[data-testid="target-list"]').trigger('drop');

        expect(target.emitted('update:modelValue')).toBeUndefined();
    });

    it('drags from anywhere on the item without the handle prop (whole-row drag)', async () => {
        const wrapper = mountList({ test_id: 'row-list' });

        // A dragstart without any handle mousedown is accepted.
        await itemEl(wrapper, 1, 'row-list').trigger('dragstart');
        await itemEl(wrapper, 2, 'row-list').trigger('dragover', { clientY: 10 });
        await wrapper.get('[data-testid="row-list"]').trigger('drop');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[ITEMS[1], ITEMS[0], ITEMS[2]]],
        ]);
    });

    it('rejects drags started on an interactive control (5.2 rework)', async () => {
        const wrapper = mountList(
            {
                interactive_selector: '.interactive',
                test_id: 'interactive-list',
            },
            {
                default: `
                    <template #default="{ item }">
                        <button type="button" class="interactive" data-testid="interactive-${''}">
                            {{ item.label }}
                        </button>
                    </template>
                `,
            },
        );

        // Mousedown on the control, then dragstart — the control wins.
        await wrapper.get('.interactive').trigger('mousedown');
        await wrapper.get('[data-testid^="interactive-list-item-"]').trigger('dragstart');

        expect(wrapper.emitted('drag-start')).toBeUndefined();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();

        // A mousedown on the item body (outside the control) still drags.
        await itemEl(wrapper, 2, 'interactive-list').trigger('mousedown');
        await itemEl(wrapper, 2, 'interactive-list').trigger('dragstart');

        expect(wrapper.emitted('drag-start')).toHaveLength(1);
    });

    it('keeps the handle-mode guard when the handle prop is set', async () => {
        const wrapper = mountList(
            {
                handle: '.drag-handle',
                test_id: 'handle-list',
            },
            {
                default: `
                    <template #default="{ item }">
                        <button type="button" class="drag-handle" data-testid="handle-${''}">
                            {{ item.label }}
                        </button>
                    </template>
                `,
            },
        );

        await wrapper.get('[data-testid^="handle-list-item-"]').trigger('dragstart');
        expect(wrapper.emitted('drag-start')).toBeUndefined();

        await wrapper.get('.drag-handle').trigger('mousedown');
        await wrapper.get('[data-testid^="handle-list-item-"]').trigger('dragstart');
        expect(wrapper.emitted('drag-start')).toHaveLength(1);
    });

    it('renders the empty slot inside the container (drop target)', () => {
        const wrapper = mount(SortableList, {
            props: { modelValue: [], test_id: 'empty-list' },
            slots: {
                empty: '<p data-testid="empty-msg">No items</p>',
            },
        });

        expect(wrapper.get('[data-testid="empty-list"]').find('[data-testid="empty-msg"]').exists()).toBe(
            true,
        );
    });

    it('shows the cross-list preview in the target list and finalizes it on drop (5.2 rework)', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-list',
            },
            {},
            [{ id: 4, label: 'Fourth' }],
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });

        // The dragged item appears (semi-transparent) at the hovered
        // position (the dragover targets the container → appended).
        expect(target.findAll('[data-testid^="target-list-item-"]')).toHaveLength(2);
        expect(target.get('[data-testid="target-list-item-1"]').classes()).toContain('is-preview');

        // The drop finalizes the preview position.
        await target.get('[data-testid="target-list"]').trigger('drop');
        await source.get('[data-testid="source-list"]').trigger('dragend');

        expect(target.emitted('update:modelValue')).toEqual([
            [[{ id: 4, label: 'Fourth' }, ITEMS[0]]],
        ]);
        expect(target.get('[data-testid="target-list-item-1"]').classes()).not.toContain('is-preview');
    });

    it('removes the cross-list preview when the drag leaves the target (5.2 rework)', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-list',
            },
            {},
            [{ id: 4, label: 'Fourth' }],
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });
        expect(target.findAll('[data-testid^="target-list-item-"]')).toHaveLength(2);

        // The drag leaves the list entirely (relatedTarget outside).
        await target
            .get('[data-testid="target-list"]')
            .trigger('dragleave', { relatedTarget: document.body });

        expect(target.findAll('[data-testid^="target-list-item-"]')).toHaveLength(1);
        expect(target.emitted('update:modelValue')).toBeUndefined();
    });

    it('moves the preview with the cursor and clears it on dragend (5.2 rework)', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const targetOne = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-one',
            },
            {},
            [{ id: 4, label: 'Fourth' }],
        );
        const targetTwo = mountList(
            {
                list_id: 'list-c',
                cross_scope: 'skills',
                test_id: 'target-two',
            },
            {},
            [{ id: 5, label: 'Fifth' }],
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        // Hover targetOne — it shows the preview.
        await targetOne.get('[data-testid="target-one"]').trigger('dragover', { clientY: 0 });
        expect(targetOne.findAll('[data-testid^="target-one-item-"]')).toHaveLength(2);

        // The preview FOLLOWS the cursor: entering targetTwo removes
        // targetOne's copy (no ghost previews in the groups the cursor
        // left behind), and only targetTwo keeps it.
        await targetTwo.get('[data-testid="target-two"]').trigger('dragover', { clientY: 0 });
        expect(targetOne.findAll('[data-testid^="target-one-item-"]')).toHaveLength(1);
        expect(targetTwo.findAll('[data-testid^="target-two-item-"]')).toHaveLength(2);

        // The drag ends without a drop — every preview disappears.
        await source.get('[data-testid="source-list"]').trigger('dragend');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(targetOne.findAll('[data-testid^="target-one-item-"]')).toHaveLength(1);
        expect(targetTwo.findAll('[data-testid^="target-two-item-"]')).toHaveLength(1);
    });

    it('deduplicates the preview on insert (5.2 rework)', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-list',
            },
            {},
            [{ id: 4, label: 'Fourth' }],
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        // Hover repeatedly — the preview must never duplicate.
        for (let i = 0; i < 5; i++) {
            await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });
        }

        const items = target.findAll('[data-testid^="target-list-item-"]');
        expect(items).toHaveLength(2);
        expect(target.findAll('[data-testid="target-list-item-1"]')).toHaveLength(1);
    });

    it('resets the preview state when the modelValue changes mid-drag (5.2 rework)', async () => {
        const source = mountList({
            list_id: 'list-a',
            cross_scope: 'skills',
            test_id: 'source-list',
        });
        const target = mountList(
            {
                list_id: 'list-b',
                cross_scope: 'skills',
                test_id: 'target-list',
            },
            {},
            [{ id: 4, label: 'Fourth' }],
        );

        await itemEl(source, 1, 'source-list').trigger('dragstart');
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });
        expect(target.findAll('[data-testid^="target-list-item-"]')).toHaveLength(2);

        // A server re-render lands mid-drag (props reset): the list drops
        // the preview and its flags — the next dragover re-inserts a
        // single copy WITHOUT treating a real row as the preview slot
        // (the reported duplication/ghost items).
        await target.setProps({ modelValue: [{ id: 4, label: 'Fourth' }] });
        await target.get('[data-testid="target-list"]').trigger('dragover', { clientY: 0 });

        expect(target.findAll('[data-testid^="target-list-item-"]')).toHaveLength(2);
        expect(target.findAll('[data-testid="target-list-item-4"]')).toHaveLength(1);
        expect(target.findAll('[data-testid="target-list-item-1"]')).toHaveLength(1);
    });
});
