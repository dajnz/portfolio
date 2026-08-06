import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import TechSkillAutocomplete from '@shared-cross/Components/TechSkillAutocomplete.vue';

const SUGGESTIONS = [
    { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
    { id: 2, name: 'PHP', icon: 'devicon:php' },
];

const SELECTED = [{ id: 1, name: 'Laravel', icon: 'devicon:laravel' }];

describe('TechSkillAutocomplete', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('renders the selected skills as removable pills', () => {
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: SELECTED } });

        expect(wrapper.find('[data-testid="skill-autocomplete-pill-1"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Laravel');
    });

    it('removes a skill via the pill remove button', async () => {
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: SELECTED } });

        await wrapper.get('[data-testid="skill-autocomplete-pill-remove-1"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[[]]]);
    });

    it('shows the start-typing hint when focused with an empty query', async () => {
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: [] } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').trigger('focus');

        expect(wrapper.text()).toContain('Start typing to search skills');
    });

    it('fetches suggestions after the debounce and excludes selected skills', async () => {
        const fetchMock = vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify(SUGGESTIONS), { status: 200 }),
        );
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: SELECTED } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledWith('/api/skills/search?q=lar', expect.anything());

        await vi.waitFor(() => {
            const suggestions = wrapper.findAll('[data-testid^="skill-autocomplete-suggestion-"]');
            expect(suggestions).toHaveLength(1);
            expect(suggestions[0]?.text()).toContain('PHP');
        });
    });

    it('adds a skill on suggestion click and emits the updated array', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: [] } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="skill-autocomplete-suggestion-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="skill-autocomplete-suggestion-1"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[{ id: 2, name: 'PHP', icon: 'devicon:php' }]],
        ]);
    });

    it('selects the highlighted suggestion with Enter and arrow keys', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: [] } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="skill-autocomplete-suggestion-"]')).toHaveLength(2);
        });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').trigger('keydown', { key: 'ArrowDown' });
        await wrapper.get('[data-testid="skill-autocomplete-input"]').trigger('keydown', { key: 'Enter' });

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [[{ id: 1, name: 'Laravel', icon: 'devicon:laravel' }]],
        ]);
    });

    it('removes the last selected skill with Backspace on an empty input', async () => {
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: SELECTED } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').trigger('keydown', {
            key: 'Backspace',
        });

        expect(wrapper.emitted('update:modelValue')).toEqual([[[]]]);
    });

    it('renders the error state with a retry action', async () => {
        vi.mocked(fetch).mockRejectedValue(new Error('network down'));
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: [] } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').setValue('lar');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('Could not load skills.');
        });

        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        await wrapper.get('[data-testid="skill-autocomplete-retry"]').trigger('click');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid^="skill-autocomplete-suggestion-"]')).toHaveLength(2);
        });
    });

    it('shows the no-skills message for an empty result set', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify([]), { status: 200 }));
        const wrapper = mount(TechSkillAutocomplete, { props: { modelValue: [] } });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').setValue('zzz');
        await vi.advanceTimersByTimeAsync(300);

        await vi.waitFor(() => {
            expect(wrapper.text()).toContain('No skills found.');
        });
    });

    it('disables the whole widget', () => {
        const wrapper = mount(TechSkillAutocomplete, {
            props: { modelValue: SELECTED, disabled: true },
        });

        expect(wrapper.get('[data-testid="skill-autocomplete-input"]').attributes('disabled')).toBeDefined();
    });

    it('exposes the pill and suggestion slots', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response(JSON.stringify(SUGGESTIONS), { status: 200 }));
        const wrapper = mount(TechSkillAutocomplete, {
            props: { modelValue: SELECTED },
            slots: {
                pill: `
                    <template #pill="{ skill }">
                        <span data-testid="custom-pill">{{ skill.name }}</span>
                    </template>
                `,
                suggestion: `
                    <template #suggestion="{ skill }">
                        <span data-testid="custom-suggestion">{{ skill.name }}</span>
                    </template>
                `,
            },
        });

        await wrapper.get('[data-testid="skill-autocomplete-input"]').setValue('php');
        await vi.advanceTimersByTimeAsync(300);
        await vi.waitFor(() => {
            expect(wrapper.findAll('[data-testid="custom-suggestion"]').length).toBeGreaterThan(0);
        });

        expect(wrapper.get('[data-testid="custom-pill"]').text()).toBe('Laravel');
    });

    it('applies the classes prop overrides in admin context', () => {
        const wrapper = mount(TechSkillAutocomplete, {
            props: {
                modelValue: [],
                classes: { input: 'rounded-lg border-slate-300', dropdown: 'shadow-xl' },
            },
        });

        expect(wrapper.get('[data-testid="skill-autocomplete-input"]').classes()).toContain(
            'rounded-lg',
        );
    });
});
