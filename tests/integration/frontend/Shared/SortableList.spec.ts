import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import SortableList from '@shared-cross/Components/SortableList.vue';

const ITEMS = [
    { id: 1, label: 'First' },
    { id: 2, label: 'Second' },
    { id: 3, label: 'Third' },
];

function mountList(props: Record<string, unknown> = {}, slots: Record<string, unknown> = {}) {
    return mount(SortableList, {
        props: { modelValue: ITEMS, ...props },
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

function itemEl(wrapper: ReturnType<typeof mountList>, id: number): ReturnType<typeof wrapper.get> {
    return wrapper.get(`[data-testid="sortable-list-item-${id}"]`);
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
        expect(wrapper.emitted('drag-end')).toEqual([[[ITEMS[1], ITEMS[0], ITEMS[2]]]]);
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
});
