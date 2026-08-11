import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';

import AdminDatePicker from '@shared-admin/AdminDatePicker.vue';

function mountPicker(props: Record<string, unknown> = {}) {
    return mount(AdminDatePicker, {
        props: { modelValue: '', test_id: 'date', ...props },
        attachTo: document.body,
    });
}

describe('AdminDatePicker', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.body.innerHTML = '';
    });

    it('always displays the value in the RU dd.mm.yyyy format (B175)', () => {
        const wrapper = mountPicker({ modelValue: '2023-01-15' });

        expect(wrapper.get('[data-testid="date-value"]').text()).toBe('15.01.2023');
    });

    it('shows a translated placeholder for an empty value and a clear button for a set one', () => {
        const empty = mountPicker();
        expect(empty.find('[data-testid="date-value"]').exists()).toBe(false);
        expect(empty.text()).toContain('dd.mm.yyyy');
        expect(empty.find('[data-testid="date-clear"]').exists()).toBe(false);

        const set = mountPicker({ modelValue: '2023-01-15' });
        expect(set.find('[data-testid="date-clear"]').exists()).toBe(true);
    });

    it('shows the month name with its 1-based number in the heading (B175)', async () => {
        const wrapper = mountPicker({ modelValue: '2026-04-10' });

        await wrapper.get('[data-testid="date-field"]').trigger('click');

        expect(wrapper.get('[data-testid="date-month-title"]').text()).toContain('(4)');
        expect(wrapper.get('[data-testid="date-month-title"]').text()).toMatch(/April/);
        expect(wrapper.get('[data-testid="date-month-title"]').text()).toContain('2026');
    });

    it('opens the calendar on a field click and picks a day as ISO', async () => {
        const wrapper = mountPicker();

        await wrapper.get('[data-testid="date-field"]').trigger('click');
        expect(wrapper.find('[data-testid="date-calendar"]').exists()).toBe(true);

        const today = new Date();
        const day = String(today.getDate()).padStart(2, '0');
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const year = today.getFullYear();

        await wrapper.get(`[data-testid="date-day-${day}-${month}-${year}"]`).trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            `${year}-${String(today.getMonth() + 1).padStart(2, '0')}-${day}`,
        ]);
        expect(wrapper.find('[data-testid="date-calendar"]').exists()).toBe(false);
    });

    it('clears the value via the clear button', async () => {
        const wrapper = mountPicker({ modelValue: '2023-01-15' });

        await wrapper.get('[data-testid="date-clear"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['']);
    });

    it('navigates months and picks from another month', async () => {
        const wrapper = mountPicker();

        await wrapper.get('[data-testid="date-field"]').trigger('click');
        await wrapper.get('[data-testid="date-prev-month"]').trigger('click');
        await wrapper.get('[data-testid="date-prev-month"]').trigger('click');

        const today = new Date();
        const target = new Date(today.getFullYear(), today.getMonth() - 2, 1);
        const day = '01';
        const month = String(target.getMonth() + 1).padStart(2, '0');
        const year = target.getFullYear();

        await wrapper.get(`[data-testid="date-day-${day}-${month}-${year}"]`).trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            `${year}-${month}-${day}`,
        ]);
    });

    it('closes the calendar on an outside click', async () => {
        const wrapper = mountPicker();

        await wrapper.get('[data-testid="date-field"]').trigger('click');
        expect(wrapper.find('[data-testid="date-calendar"]').exists()).toBe(true);

        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-testid="date-calendar"]').exists()).toBe(false);
    });

    it('opens the year grid from the heading and picks a year (B175)', async () => {
        const wrapper = mountPicker({ modelValue: '2026-04-10' });

        await wrapper.get('[data-testid="date-field"]').trigger('click');
        await wrapper.get('[data-testid="date-month-title"]').trigger('click');

        expect(wrapper.find('[data-testid="date-years"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-year-2020"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-year-2029"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-day-10-04-2026"]').exists()).toBe(false);

        // Picking a year returns to the day grid of that year — the same
        // month (April) — so a day pick emits the new year.
        await wrapper.get('[data-testid="date-year-2020"]').trigger('click');

        expect(wrapper.find('[data-testid="date-years"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="date-day-10-04-2020"]').exists()).toBe(true);

        await wrapper.get('[data-testid="date-day-10-04-2020"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['2020-04-10']);
    });

    it('shifts the decade with the prev/next buttons in the year grid (B175)', async () => {
        const wrapper = mountPicker({ modelValue: '2026-04-10' });

        await wrapper.get('[data-testid="date-field"]').trigger('click');
        await wrapper.get('[data-testid="date-month-title"]').trigger('click');
        await wrapper.get('[data-testid="date-prev-month"]').trigger('click');

        expect(wrapper.find('[data-testid="date-year-2010"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-year-2019"]').exists()).toBe(true);

        await wrapper.get('[data-testid="date-next-month"]').trigger('click');

        expect(wrapper.find('[data-testid="date-year-2020"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-year-2029"]').exists()).toBe(true);
    });

    it('keeps the calendar open when a year click removes the button mid-dispatch (B175)', async () => {
        const wrapper = mountPicker({ modelValue: '2026-04-10' });

        await wrapper.get('[data-testid="date-field"]').trigger('click');
        await wrapper.get('[data-testid="date-month-title"]').trigger('click');

        const yearButton = wrapper.get('[data-testid="date-year-2020"]');

        // Simulate the real-browser interleaving: the year click
        // re-renders the grid — detaching the clicked button — before
        // the click finishes bubbling to the document-level
        // outside-click handler. A bubble-phase `contains(event.target)`
        // check would see the detached button as an outside click and
        // close the calendar right after the year was picked.
        yearButton.element.addEventListener('click', () => yearButton.element.remove(), {
            once: true,
        });
        yearButton.element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-testid="date-calendar"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-day-10-04-2020"]').exists()).toBe(true);
    });
});
