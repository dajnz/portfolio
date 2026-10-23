import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';

// The icon subsets (resources/js/iconSubsets — generated from the strict
// icon manifests by `pnpm run icons:build`) are loaded dynamically on
// every panel open. They are small enough to load as-is in vitest.
// These tests verify picker behavior, not the vendored data.

function mountIconPicker(props = {}) {
    return mount(AdminIconPicker, {
        props: { modelValue: null, ...props },
        attachTo: document.body,
        global: {
            stubs: { teleport: true },
        },
    });
}

async function openPanel(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('[data-testid="icon-picker"]').trigger('click');
    await flushPromises();
}

describe('AdminIconPicker', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('shows the select placeholder when no icon is selected', () => {
        const wrapper = mountIconPicker();

        expect(wrapper.text()).toContain('Select an icon...');
    });

    it('shows the selected icon label on the trigger', () => {
        const wrapper = mountIconPicker({ modelValue: 'heroicons:bolt' });

        expect(wrapper.text()).toContain('bolt');
    });

    it('opens the panel on click and auto-focuses the search input', async () => {
        const wrapper = mountIconPicker();

        await openPanel(wrapper);

        expect(wrapper.find('[data-testid="icon-picker-panel"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="icon-picker-search"]').attributes('placeholder')).toBe(
            'Search icons...',
        );
        expect(document.activeElement).toBe(wrapper.get('[data-testid="icon-picker-search"]').element);
    });

    it('shows the mixed technical set (heroicons + simple-icons) in a flat list', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical' });

        await openPanel(wrapper);

        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:server"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );
        expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:bolt"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="icon-picker-icon-simple-icons:github"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Outline');
        expect(wrapper.find('[data-testid^="icon-picker-icon-devicon:"]').exists()).toBe(false);
    });

    it('shows only devicon icons in the brands set (skills)', async () => {
        const wrapper = mountIconPicker({ icon_set: 'brands' });

        await openPanel(wrapper);

        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-devicon:laravel"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );
        expect(wrapper.find('[data-testid="icon-picker-icon-devicon:php"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid^="icon-picker-icon-heroicons:"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid^="icon-picker-icon-simple-icons:"]').exists()).toBe(false);
    });

    it('filters the grid with the search query', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical' });

        await openPanel(wrapper);
        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:bolt"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );

        await wrapper.get('[data-testid="icon-picker-search"]').setValue('server');

        expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:server"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:bolt"]').exists()).toBe(false);
    });

    it('shows the no-icons-match message for an empty search', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical' });

        await openPanel(wrapper);
        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:server"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );

        await wrapper.get('[data-testid="icon-picker-search"]').setValue('zzz');

        expect(wrapper.text()).toContain('No icons match');
    });

    it('highlights the selected icon in the grid', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical', modelValue: 'heroicons:bolt' });

        await openPanel(wrapper);
        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:bolt"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );

        expect(
            wrapper.get('[data-testid="icon-picker-icon-heroicons:bolt"]').classes(),
        ).toContain('ring-primary-500');
    });

    it('emits update:modelValue and closes the panel on selection', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical' });

        await openPanel(wrapper);
        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:sparkles"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );

        await wrapper.get('[data-testid="icon-picker-icon-heroicons:sparkles"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['heroicons:sparkles']]);
        expect(wrapper.find('[data-testid="icon-picker-panel"]').exists()).toBe(false);
    });

    it('ignores a click on the already-selected icon', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical', modelValue: 'heroicons:bolt' });

        await openPanel(wrapper);
        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:bolt"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );

        await wrapper.get('[data-testid="icon-picker-icon-heroicons:bolt"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.find('[data-testid="icon-picker-panel"]').exists()).toBe(true);
    });

    it('closes the panel on an outside click', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical' });

        await openPanel(wrapper);
        await vi.waitFor(
            () => {
                expect(wrapper.find('[data-testid="icon-picker-icon-heroicons:server"]').exists()).toBe(true);
            },
            { timeout: 5000 },
        );

        const outside = document.createElement('div');
        document.body.appendChild(outside);
        outside.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        outside.remove();
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(wrapper.find('[data-testid="icon-picker-panel"]').exists()).toBe(false);
    });

    it('closes the panel when the trigger is clicked again', async () => {
        const wrapper = mountIconPicker({ icon_set: 'technical' });

        await openPanel(wrapper);
        expect(wrapper.find('[data-testid="icon-picker-panel"]').exists()).toBe(true);

        await wrapper.get('[data-testid="icon-picker"]').trigger('click');

        expect(wrapper.find('[data-testid="icon-picker-panel"]').exists()).toBe(false);
    });
});
