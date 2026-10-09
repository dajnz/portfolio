import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick, ref } from 'vue';

import AdminTechSkillPicker from '@shared-admin/AdminTechSkillPicker.vue';

const SKILLS = [
    { id: 1, name: 'PHP', icon: 'heroicons:bolt' },
    { id: 2, name: 'Laravel', icon: 'heroicons:globe-alt' },
    { id: 3, name: 'Vue', icon: 'heroicons:sparkles' },
];

function mountSkillPicker(props = {}, slots = {}, options = {}) {
    return mount(AdminTechSkillPicker, {
        props: { modelValue: [], ...props },
        slots,
        ...options,
    });
}

// The picker must be driven through a v-model parent for the click
// handler to update the prop and re-filter the suggestions — the
// real-browser interleaving the mid-dispatch tests exercise.
const PickerHarness = defineComponent({
    components: { AdminTechSkillPicker },
    setup() {
        const skills = ref<Array<(typeof SKILLS)[number]>>([]);

        return { skills };
    },
    template: '<AdminTechSkillPicker v-model="skills" test_id="tech-skill-picker" />',
});

function mountPickerWithParent() {
    return mount(PickerHarness, { attachTo: document.body });
}

async function openSuggestions(wrapper: VueWrapper) {
    vi.mocked(fetch).mockResolvedValue(
        new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
    );
    const input = wrapper.get('[data-testid="tech-skill-picker-input"]');
    (input.element as HTMLElement).focus();

    await input.setValue('php');
    await vi.advanceTimersByTimeAsync(300);
    await vi.waitFor(() => {
        expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
    });

    return input;
}

describe('AdminTechSkillPicker', () => {
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
        const wrapper = mountSkillPicker();

        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('focus');

        expect(wrapper.find('[data-testid="tech-skill-picker-dropdown"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Start typing to search');
    });

    it('renders the selected skills as removable pills with icon and name', () => {
        const wrapper = mountSkillPicker({ modelValue: [SKILLS[0], SKILLS[1]] });

        const pill = wrapper.get('[data-testid="tech-skill-picker-pill-0"]');
        expect(pill.text()).toContain('PHP');
        expect(pill.find('svg').exists()).toBe(true);
        expect(wrapper.get('[data-testid="tech-skill-picker-pill-1"]').text()).toContain('Laravel');
        expect(wrapper.find('[data-testid="tech-skill-picker-pill-0-remove"]').exists()).toBe(true);
    });

    it('removes a skill via the pill x button', async () => {
        const wrapper = mountSkillPicker({ modelValue: [SKILLS[0], SKILLS[1]] });

        await wrapper.get('[data-testid="tech-skill-picker-pill-0-remove"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [SKILLS[1]] ]]);
    });

    it('fetches suggestions after the debounce with the query', async () => {
        const fetchMock = vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker();

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe('/api/skills/search?q=php');

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
        });
    });

    it('excludes already-selected skills from the suggestions', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker({ modelValue: [SKILLS[0]] });

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(1);
        });
        expect(wrapper.get('[data-testid="tech-skill-picker-suggestion-0"]').text()).toContain('Laravel');
    });

    it('closes the dropdown and clears the query on an outside click (B174)', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker({ modelValue: [SKILLS[1]] }, {}, { attachTo: document.body });

        const input = wrapper.get('[data-testid="tech-skill-picker-input"]');
        await input.setValue('php');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.find('[data-testid="tech-skill-picker-dropdown"]').exists()).toBe(true);
        });

        // A click outside the picker root closes the panel and clears
        // the typed query — the selected pills stay.
        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-testid="tech-skill-picker-dropdown"]').exists()).toBe(false);
        expect((input.element as HTMLInputElement).value).toBe('');
        expect(wrapper.get('[data-testid="tech-skill-picker-pill-0"]')).toBeDefined();
    });

    it('adds a skill on suggestion click, clears the input and keeps focus', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker({}, {}, { attachTo: document.body });
        const input = wrapper.get('[data-testid="tech-skill-picker-input"]');
        (input.element as HTMLElement).focus();

        await input.setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="tech-skill-picker-suggestion-1"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [SKILLS[1]] ]]);
        expect((input.element as HTMLInputElement).value).toBe('');
        expect(document.activeElement).toBe(input.element);
        wrapper.unmount();
    });

    it('adds the clicked skill through a v-model parent (prop round-trip)', async () => {
        const wrapper = mountPickerWithParent();
        const input = await openSuggestions(wrapper);

        await wrapper.get('[data-testid="tech-skill-picker-suggestion-1"]').trigger('click');

        expect(wrapper.get('[data-testid="tech-skill-picker-pill-0"]').text()).toContain('Laravel');
        expect(wrapper.find('[data-testid="tech-skill-picker-pill-1"]').exists()).toBe(false);
        expect((input.element as HTMLInputElement).value).toBe('');
        wrapper.unmount();
    });

    it('keeps the dropdown open when adding a skill detaches the suggestion row mid-dispatch (B175)', async () => {
        const wrapper = mountPickerWithParent();
        await openSuggestions(wrapper);

        const suggestion = wrapper.get('[data-testid="tech-skill-picker-suggestion-1"]');
        suggestion.element.addEventListener('click', () => suggestion.element.remove(), {
            once: true,
        });
        suggestion.element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(wrapper.get('[data-testid="tech-skill-picker-pill-0"]').text()).toContain('Laravel');
        expect(wrapper.find('[data-testid="tech-skill-picker-dropdown"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('selects a highlighted suggestion with Enter', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker();

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });
        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });
        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'Enter',
        });

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [SKILLS[1]] ]]);
    });

    it('moves the highlight back with ArrowUp', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker();

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });
        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'ArrowDown',
        });
        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'ArrowUp',
        });

        expect(
            wrapper.get('[data-testid="tech-skill-picker-suggestion-0"]').classes(),
        ).toContain('bg-primary-50');
    });

    it('removes the last skill on Backspace with an empty query', async () => {
        const wrapper = mountSkillPicker({ modelValue: [SKILLS[0], SKILLS[1]] });

        await wrapper.get('[data-testid="tech-skill-picker-input"]').trigger('keydown', {
            key: 'Backspace',
        });

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [SKILLS[0]] ]]);
    });

    it('emits select instead of updating the model in singleSelect mode', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker({ singleSelect: true });

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="tech-skill-picker-suggestion-0"]').trigger('click');

        expect(wrapper.emitted('select')).toEqual([[SKILLS[0]]]);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('emits remove on the pill x button in singleSelect mode', async () => {
        const wrapper = mountSkillPicker({ singleSelect: true, modelValue: [SKILLS[0]] });

        await wrapper.get('[data-testid="tech-skill-picker-pill-0-remove"]').trigger('click');

        expect(wrapper.emitted('remove')).toEqual([[SKILLS[0]]]);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('shows the no-skills message when the response is empty', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify([]), { status: 200 }));
        const wrapper = mountSkillPicker();

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('zzz');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('No skills found.');
        });
    });

    it('shows the error state and retries', async () => {
        // The GET retry policy (error-handling.md:149-160) retries 5xx
        // responses twice (1 s + 2 s) — keep the mock failing until the
        // manual retry button flips it to success.
        const fetchMock = vi
            .mocked(fetch)
            .mockResolvedValue(new Response('nope', { status: 500 }));
        const wrapper = mountSkillPicker();

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300 + 1000 + 2000);

        expect(wrapper.text()).toContain('Could not load skills.');

        fetchMock.mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );

        await wrapper.get('[data-testid="tech-skill-picker-retry"]').trigger('click');

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="tech-skill-picker-suggestion-"]')).toHaveLength(2);
        });
    });

    it('renders custom suggestion rows through the suggestion slot', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify([SKILLS[0], SKILLS[1]]), { status: 200 }),
        );
        const wrapper = mountSkillPicker(
            {},
            {
                suggestion: `
                    <template #suggestion="{ skill, select }">
                        <button data-testid="custom-suggestion" @click="select">{{ skill.name }}</button>
                    </template>
                `,
            },
        );

        await wrapper.get('[data-testid="tech-skill-picker-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid="custom-suggestion"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="custom-suggestion"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[ [SKILLS[0]] ]]);
    });

    it('shows the error message with role alert', () => {
        const wrapper = mountSkillPicker({
            label: 'Tech skills',
            error: 'Pick at least one skill',
        });

        expect(wrapper.get('[role="alert"]').text()).toBe('Pick at least one skill');
    });
});
