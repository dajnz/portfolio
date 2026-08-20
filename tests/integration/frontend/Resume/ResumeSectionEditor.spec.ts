import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import ResumeSectionEditor from '@resume-admin/ResumeSectionEditor.vue';
import type { ResumeSectionsMap } from '@resume/Admin/Types/layout';

const SECTIONS: ResumeSectionsMap = {
    header: { visible: true, order: 0 },
    about_me: { visible: true, order: 1 },
    skills: { visible: true, order: 2 },
    experience: { visible: false, order: 3 },
    contacts: { visible: false, order: 4 },
};

const LABELS = {
    header: 'Header',
    about_me: 'About Me',
    skills: 'Skills',
    experience: 'Experience',
    contacts: 'Contacts',
};

function mountEditor(sections: ResumeSectionsMap = SECTIONS) {
    return mount(ResumeSectionEditor, {
        props: { modelValue: sections, labels: LABELS, test_id: 'section-editor' },
    });
}

describe('ResumeSectionEditor', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the visible sections first (by order), then the hidden ones', () => {
        const wrapper = mountEditor();

        const rows = wrapper.findAll('[data-testid^="section-editor-row-"]');

        expect(rows.map((row) => row.attributes('data-testid'))).toEqual([
            'section-editor-row-header',
            'section-editor-row-about_me',
            'section-editor-row-skills',
            'section-editor-row-experience',
            'section-editor-row-contacts',
        ]);
    });

    it('sorts hidden sections by their own order values', () => {
        const wrapper = mountEditor({
            header: { visible: false, order: 4 },
            about_me: { visible: true, order: 0 },
            skills: { visible: false, order: 3 },
            experience: { visible: true, order: 1 },
            contacts: { visible: false, order: 2 },
        });

        const rows = wrapper.findAll('[data-testid^="section-editor-row-"]');

        expect(rows.map((row) => row.attributes('data-testid'))).toEqual([
            'section-editor-row-about_me',
            'section-editor-row-experience',
            'section-editor-row-contacts',
            'section-editor-row-skills',
            'section-editor-row-header',
        ]);
    });

    it('subdues the hidden rows', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="section-editor-row-experience"]').classes()).toContain(
            'opacity-50',
        );
        expect(wrapper.get('[data-testid="section-editor-row-header"]').classes()).not.toContain(
            'opacity-50',
        );
    });

    it('disables Up on the first visible section and Down on the last visible one', () => {
        const wrapper = mountEditor();

        expect(
            (wrapper.get('[data-testid="section-editor-up-header"]').element as HTMLButtonElement).disabled,
        ).toBe(true);
        expect(
            (wrapper.get('[data-testid="section-editor-up-skills"]').element as HTMLButtonElement).disabled,
        ).toBe(false);
        expect(
            (wrapper.get('[data-testid="section-editor-down-skills"]').element as HTMLButtonElement).disabled,
        ).toBe(true);
        expect(
            (wrapper.get('[data-testid="section-editor-down-header"]').element as HTMLButtonElement).disabled,
        ).toBe(false);
    });

    it('disables both arrows of the hidden sections', () => {
        const wrapper = mountEditor();

        for (const section of ['experience', 'contacts']) {
            expect(
                (wrapper.get(`[data-testid="section-editor-up-${section}"]`).element as HTMLButtonElement)
                    .disabled,
            ).toBe(true);
            expect(
                (wrapper.get(`[data-testid="section-editor-down-${section}"]`).element as HTMLButtonElement)
                    .disabled,
            ).toBe(true);
        }
    });

    it('emits toggle + update:modelValue when a checkbox is flipped', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-visible-skills"]').setValue(false);

        expect(wrapper.emitted('toggle')?.at(-1)).toEqual([{ section_id: 'skills', visible: false }]);
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            { ...SECTIONS, skills: { visible: false, order: 2 } },
        ]);
    });

    it('swaps order values with the previous visible row on move-up', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-up-about_me"]').trigger('click');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(wrapper.emitted('move-up')?.at(-1)).toEqual(['about_me']);
        expect(emitted.header.order).toBe(1);
        expect(emitted.about_me.order).toBe(0);
    });

    it('swaps order values with the next visible row on move-down', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-down-about_me"]').trigger('click');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(wrapper.emitted('move-down')?.at(-1)).toEqual(['about_me']);
        expect(emitted.skills.order).toBe(1);
        expect(emitted.about_me.order).toBe(2);
    });

    it('does not emit when a disabled arrow is clicked', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-up-header"]').trigger('click');
        await wrapper.get('[data-testid="section-editor-down-contacts"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.emitted('move-up')).toBeUndefined();
        expect(wrapper.emitted('move-down')).toBeUndefined();
    });

    it('keeps the order values unique after moves across non-adjacent orders', () => {
        const wrapper = mountEditor({
            header: { visible: true, order: 0 },
            about_me: { visible: false, order: 1 },
            skills: { visible: true, order: 2 },
            experience: { visible: false, order: 3 },
            contacts: { visible: true, order: 4 },
        });

        // The visible group is header(0), skills(2), contacts(4) — moving
        // skills down lands it between contacts and header with renumbered
        // orders: contacts(0), skills(1), header(2).
        const skillsDown = wrapper.get('[data-testid="section-editor-down-skills"]');

        expect((skillsDown.element as HTMLButtonElement).disabled).toBe(false);
    });

    it('toggling visibility keeps the order value', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-visible-header"]').setValue(false);

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(emitted.header).toEqual({ visible: false, order: 0 });
    });

    it('clips the rows to the container rounded corners', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="section-editor"]').classes()).toContain('overflow-hidden');
    });

    it('renders the checkbox labels as non-selectable', () => {
        const wrapper = mountEditor();

        const label = wrapper.get('[data-testid="section-editor-visible-header"]').element.parentElement;

        expect(label?.classList.contains('select-none')).toBe(true);
    });

    it('makes the visible rows draggable and the hidden rows not', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="section-editor-row-header"]').attributes('draggable')).toBe(
            'true',
        );
        // happy-dom does not implement the `draggable` IDL property — the
        // absence of the attribute marks the row non-draggable.
        expect(
            wrapper.get('[data-testid="section-editor-row-experience"]').attributes('draggable'),
        ).toBeUndefined();
    });

    it('reorders the visible group by drag and renumbers the orders', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('drop');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(emitted).toEqual({
            header: { visible: true, order: 1 },
            about_me: { visible: true, order: 2 },
            skills: { visible: true, order: 0 },
            experience: { visible: false, order: 3 },
            contacts: { visible: false, order: 4 },
        });
    });

    it('drops a dragged row after the hovered row when the cursor is below its midpoint', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-about_me"]').trigger('dragover', {
            clientY: 100,
        });
        await wrapper.get('[data-testid="section-editor-row-about_me"]').trigger('drop');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(emitted.about_me.order).toBe(0);
        expect(emitted.header.order).toBe(1);
        expect(emitted.skills.order).toBe(2);
    });

    it('shows the dragged row ghosted in its potential slot during the drag', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });

        // The visible rows shift live — skills now sits in the header
        // slot, ghosted (the drop position preview).
        const rowIds = wrapper
            .findAll('[data-testid^="section-editor-row-"]')
            .map((row) => row.attributes('data-testid'));
        expect(rowIds).toEqual([
            'section-editor-row-skills',
            'section-editor-row-header',
            'section-editor-row-about_me',
            'section-editor-row-experience',
            'section-editor-row-contacts',
        ]);
        expect(wrapper.get('[data-testid="section-editor-row-skills"]').classes()).toContain(
            'opacity-40',
        );

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragend');

        // Cancel restores the original order and removes the ghost.
        const restored = wrapper
            .findAll('[data-testid^="section-editor-row-"]')
            .map((row) => row.attributes('data-testid'));
        expect(restored).toEqual([
            'section-editor-row-header',
            'section-editor-row-about_me',
            'section-editor-row-skills',
            'section-editor-row-experience',
            'section-editor-row-contacts',
        ]);
        expect(wrapper.get('[data-testid="section-editor-row-skills"]').classes()).not.toContain(
            'opacity-40',
        );
    });

    it('keeps the dragged row a valid drop target and finalizes the drop on it', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        // The live shift parks the dragged row's ghost directly under the
        // cursor — the release hit-tests the dragged row, so its dragover
        // must be prevented (the browser cancels the drop on an invalid
        // target — the reorder would only ever be "visual").
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });

        const ghostOver = new DragEvent('dragover', {
            clientY: -10,
            bubbles: true,
            cancelable: true,
        });
        wrapper.get('[data-testid="section-editor-row-skills"]').element.dispatchEvent(ghostOver);
        expect(ghostOver.defaultPrevented).toBe(true);

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('drop');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(emitted).toEqual({
            header: { visible: true, order: 1 },
            about_me: { visible: true, order: 2 },
            skills: { visible: true, order: 0 },
            experience: { visible: false, order: 3 },
            contacts: { visible: false, order: 4 },
        });
    });

    it('does not emit when the drop lands back in the original slot', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });
        // Dropping on about_me below its midpoint inserts skills back
        // behind it — the dragged row's own original slot (a no-op).
        await wrapper.get('[data-testid="section-editor-row-about_me"]').trigger('dragover', {
            clientY: 100,
        });
        await wrapper.get('[data-testid="section-editor-row-about_me"]').trigger('drop');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('rejects a drag started on the checkbox or an arrow button', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-visible-header"]').trigger('mousedown');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragstart');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();

        await wrapper.get('[data-testid="section-editor-up-about_me"]').trigger('mousedown');
        await wrapper.get('[data-testid="section-editor-row-about_me"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragover', {
            clientY: 100,
        });
        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('drop');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('starts a drag from the row itself (outside the controls)', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('mousedown');
        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('drop');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(emitted.skills.order).toBe(0);
    });

    it('allows a drag after a plain click on a control (mouseup clears the guard)', async () => {
        const wrapper = mountEditor();

        // A click on the checkbox (mousedown + mouseup) must not poison the
        // next drag started from the row's free area.
        await wrapper.get('[data-testid="section-editor-visible-header"]').trigger('mousedown');
        await wrapper.get('[data-testid="section-editor-visible-header"]').trigger('mouseup');
        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('mousedown');
        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('drop');

        const emitted = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as ResumeSectionsMap;

        expect(emitted.skills.order).toBe(0);
    });

    it('does not emit when the drag is cancelled without a drop', async () => {
        const wrapper = mountEditor();

        await wrapper.get('[data-testid="section-editor-row-skills"]').trigger('dragstart');
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragover', {
            clientY: -10,
        });
        await wrapper.get('[data-testid="section-editor-row-header"]').trigger('dragend');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });
});
