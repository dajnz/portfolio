import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const { getMock, postMock, putMock, deleteMock, pageProps } = vi.hoisted(() => ({
    getMock: vi.fn(),
    postMock: vi.fn(),
    putMock: vi.fn(),
    deleteMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
        supported_locales: undefined as string[] | undefined,
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: getMock,
        post: postMock,
        put: putMock,
        delete: deleteMock,
        on: vi.fn(() => vi.fn()),
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import SkillsListPage from '@techskills/Admin/Pages/SkillsListPage/SkillsListPage.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

const GROUPS = [
    {
        id: 1,
        name: 'Backend',
        names: { en: 'Backend', ru: 'Бэкенд' },
        icon: 'heroicons:server',
        sort_order: 0,
        skills: [
            {
                id: 1,
                name: 'Laravel',
                icon: 'devicon:laravel',
                proficiency: 4,
                sort_order: 0,
                include_in_resume: true,
            },
            {
                id: 2,
                name: 'PHP',
                icon: 'devicon:php',
                proficiency: 3,
                sort_order: 1,
                include_in_resume: false,
            },
        ],
    },
    {
        id: 2,
        name: 'Frontend',
        names: { en: 'Frontend', ru: 'Фронтенд' },
        icon: 'heroicons:globe-alt',
        sort_order: 1,
        skills: [
            {
                id: 3,
                name: 'Vue.js',
                icon: 'devicon:vuejs',
                proficiency: 5,
                sort_order: 0,
                include_in_resume: true,
            },
        ],
    },
];

function mountPage(props: Record<string, unknown> = {}) {
    // Deep-copy the fixture — the page mutates its local skill rows
    // (live proficiency/checkbox PUTs), which must not leak between tests.
    const skillGroups: Array<(typeof GROUPS)[number]> = props.skillGroups === undefined ? GROUPS : (props.skillGroups as Array<(typeof GROUPS)[number]>);

    return mount(SkillsListPage, {
        props: {
            skillGroups: skillGroups.map((group) => ({
                ...group,
                skills: group.skills.map((skill) => ({ ...skill })),
            })),
            ...props,
        },
        // Attach to the document — the page's click-outside-exit resolves
        // the current editor element via document.querySelector.
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('SkillsListPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        deleteMock.mockClear();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    it('renders the groups with their skills', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="skills-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="skills-list-page-group-1"]').text()).toContain('Backend');
        expect(wrapper.get('[data-testid="skills-list-page-group-1"]').text()).toContain('Laravel');
        expect(wrapper.get('[data-testid="skills-list-page-group-1"]').text()).toContain('PHP');
        expect(wrapper.get('[data-testid="skills-list-page-group-2"]').text()).toContain('Frontend');
        expect(wrapper.get('[data-testid="skills-list-page-group-2"]').text()).toContain('Vue.js');
    });

    it('shows the empty state and opens the create-group editor from its CTA', async () => {
        const wrapper = mountPage({ skillGroups: [] });

        expect(wrapper.find('[data-testid="skills-list-page-empty"]').exists()).toBe(true);

        await wrapper.get('[data-testid="skills-list-page-empty"] button').trigger('click');

        expect(wrapper.find('[data-testid="skills-list-page-new-group"]').exists()).toBe(true);
    });

    it('creates a group with the per-locale names map (POST /admin/skill-groups)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-add-group"]').trigger('click');
        await wrapper.get('[data-testid="skills-list-page-new-group-name-en"]').setValue('DevOps');

        expect(
            wrapper.find('[data-testid="skills-list-page-new-group-tabs-warn-ru"]').exists(),
        ).toBe(true);

        await wrapper.get('[data-testid="skills-list-page-new-group-save"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/skill-groups',
            expect.objectContaining({
                icon: null,
                name: { en: 'DevOps', ru: '' },
                sort_order: 2,
            }),
            expect.any(Object),
        );
    });

    it('edits a group name per locale and saves the names map (PUT /admin/skill-groups/{id})', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-header"]').trigger('click');

        const nameEn = wrapper.get('[data-testid="skills-list-page-group-1-name-en"]');
        expect((nameEn.element as HTMLInputElement).value).toBe('Backend');
        await nameEn.setValue('Backend & APIs');

        await wrapper.get('[data-testid="skills-list-page-group-1-save"]').trigger('click');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/skill-groups/1',
            expect.objectContaining({
                icon: 'heroicons:server',
                name: { en: 'Backend & APIs', ru: 'Бэкенд' },
                sort_order: 0,
            }),
            expect.any(Object),
        );
    });

    it('saves the group RU translation typed under the RU tab (regression)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-header"]').trigger('click');
        await wrapper.get('[data-testid="skills-list-page-group-1-tabs-tab-ru"]').trigger('click');
        const ruInput = wrapper.get('[data-testid="skills-list-page-group-1-name-ru"]');
        expect((ruInput.element as HTMLInputElement).value).toBe('Бэкенд');
        await ruInput.setValue('Бэкенд API');
        await wrapper.get('[data-testid="skills-list-page-group-1-save"]').trigger('click');

        // The full per-locale map must reach the backend — dropping the
        // RU (e.g. an inactive-locale filter) regresses the saved data.
        expect(putMock).toHaveBeenCalledWith(
            '/admin/skill-groups/1',
            expect.objectContaining({
                name: { en: 'Backend', ru: 'Бэкенд API' },
            }),
            expect.any(Object),
        );
    });

    it('offers only the active locales in the group editor tabs (5.2 rework)', async () => {
        // The shared `supported_locales` prop reflects the settings —
        // disabled locales are not editable anywhere.
        pageProps.supported_locales = ['en'];
        try {
            const wrapper = mountPage();

            await wrapper.get('[data-testid="skills-list-page-group-1-header"]').trigger('click');

            expect(
                wrapper.find('[data-testid="skills-list-page-group-1-tabs-tab-en"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-testid="skills-list-page-group-1-tabs-tab-ru"]').exists(),
            ).toBe(false);
        } finally {
            delete pageProps.supported_locales;
        }
    });

    it('shows a translation-incomplete toast when a group save fails on a missing locale', async () => {
        putMock.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options: { onError?: (errors: Record<string, string>) => void },
            ) => {
                options.onError?.({ 'name.ru': 'The name.ru field is required.' });
            },
        );

        const wrapper = mountPage();
        await wrapper.get('[data-testid="skills-list-page-group-1-header"]').trigger('click');
        await wrapper.get('[data-testid="skills-list-page-group-1-tabs-tab-ru"]').trigger('click');
        await wrapper.get('[data-testid="skills-list-page-group-1-name-ru"]').setValue('');
        await wrapper.get('[data-testid="skills-list-page-group-1-save"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'The Russian translation is incomplete.',
        });
    });

    it('deletes a group after confirmation (DELETE /admin/skill-groups/{id})', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-2-delete"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-delete-dialog"]').exists()).toBe(true);

        await wrapper.get('[data-testid="skills-list-page-delete-dialog-confirm"]').trigger('click');

        expect(deleteMock).toHaveBeenCalledWith('/admin/skill-groups/2', expect.any(Object));
    });

    it('adds a skill to a group with its group id (POST /admin/skills)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-add-skill"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-new-skill-1"]').exists()).toBe(true);

        // The editor row renders BELOW the skills list (5.2 rework — the
        // saved skill appears where it was created, at the group bottom).
        const skillsList = wrapper.get('[data-testid="skills-list-page-skills-1"]');
        const editorRow = wrapper.get('[data-testid="skills-list-page-new-skill-1"]');
        expect(
            skillsList.element.compareDocumentPosition(editorRow.element) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();

        await wrapper.get('[data-testid="skills-list-page-new-skill-1-name"]').setValue('PostgreSQL');
        // The proficiency dropdown: open + pick level 5.
        await wrapper.get('[data-testid="skills-list-page-new-skill-1-proficiency"]').trigger('click');
        await wrapper
            .get('[data-testid="skills-list-page-new-skill-1-proficiency-option-5"]')
            .trigger('click');
        await wrapper.get('[data-testid="skills-list-page-new-skill-1-resume"]').setValue(true);
        await wrapper.get('[data-testid="skills-list-page-new-skill-1-save"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/skills',
            expect.objectContaining({
                name: 'PostgreSQL',
                icon: null,
                proficiency: 5,
                skill_group_id: 1,
                include_in_resume: true,
                sort_order: 2,
            }),
            expect.any(Object),
        );
    });

    it('renders exactly ONE new-skill editor inside the group card (no per-row duplication)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-add-skill"]').trigger('click');

        // Group 1 has two skills — the editor must appear once, not once
        // per skill row (the reported duplication).
        expect(
            wrapper.findAll('[data-testid="skills-list-page-new-skill-1"]'),
        ).toHaveLength(1);
        // It lives inside the group's card, after the skills list.
        const groupCard = wrapper.get('[data-testid="skills-list-page-group-1"]');
        const editor = wrapper.get('[data-testid="skills-list-page-new-skill-1"]');
        expect(groupCard.element.contains(editor.element)).toBe(true);
    });

    it('renders the new-skill editor for an empty group (5.2 rework)', async () => {
        const wrapper = mountPage({
            skillGroups: [
                {
                    id: 1,
                    name: 'Empty',
                    names: { en: 'Empty', ru: 'Пусто' },
                    icon: null,
                    sort_order: 0,
                    skills: [],
                },
            ],
        });

        await wrapper.get('[data-testid="skills-list-page-group-1-add-skill"]').trigger('click');

        expect(
            wrapper.findAll('[data-testid="skills-list-page-new-skill-1"]'),
        ).toHaveLength(1);
        // The empty-group hint hides while the editor is open.
        expect(
            wrapper.find('[data-testid="skills-list-page-group-1-empty"]').exists(),
        ).toBe(false);
    });

    it('edits a skill name and icon inline (PUT /admin/skills/{id})', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-2"]').trigger('click');

        const nameInput = wrapper.get('[data-testid="skills-list-page-skill-2-name"]');
        expect((nameInput.element as HTMLInputElement).value).toBe('PHP');
        await nameInput.setValue('PHP 8');

        await wrapper.get('[data-testid="skills-list-page-skill-2-save"]').trigger('click');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/skills/2',
            expect.objectContaining({
                name: 'PHP 8',
                icon: 'devicon:php',
                proficiency: 3,
                skill_group_id: 1,
                include_in_resume: false,
                sort_order: 1,
            }),
            expect.any(Object),
        );
    });

    it('edits the proficiency via the localized dropdown (PUT /admin/skills/{id})', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-2"]').trigger('click');

        const dropdown = wrapper.get('[data-testid="skills-list-page-skill-2-proficiency"]');
        expect(dropdown.text()).toContain('Competent');
        await dropdown.trigger('click');
        await wrapper.get('[data-testid="skills-list-page-skill-2-proficiency-option-5"]').trigger('click');

        await wrapper.get('[data-testid="skills-list-page-skill-2-save"]').trigger('click');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/skills/2',
            expect.objectContaining({
                name: 'PHP',
                proficiency: 5,
                skill_group_id: 1,
                include_in_resume: false,
            }),
            expect.any(Object),
        );
    });

    it('discards the skill edit when a click lands outside the row', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-1"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-skill-1-name"]').exists()).toBe(true);

        // Capture-phase pointerdown outside the edited row closes the
        // editor without saving (the blur-save was removed — ADR-0024 rework).
        wrapper
            .get('[data-testid="skills-list-page-add-group"]')
            .element.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-testid="skills-list-page-skill-1-name"]').exists()).toBe(false);
        expect(putMock).not.toHaveBeenCalled();
    });

    it('keeps the group editor open when clicking inside the header', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-header"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-group-1-name-en"]').exists()).toBe(true);

        wrapper
            .get('[data-testid="skills-list-page-group-1-name-en"]')
            .element.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-testid="skills-list-page-group-1-name-en"]').exists()).toBe(true);
    });

    it('discards the group edit when a click lands outside the header', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-header"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-group-1-name-en"]').exists()).toBe(true);

        wrapper
            .get('[data-testid="skills-list-page-groups-sortable"]')
            .element.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));
        await nextTick();

        expect(wrapper.find('[data-testid="skills-list-page-group-1-name-en"]').exists()).toBe(false);
    });

    it('colors all filled proficiency dots with the current level color and carries the single container title', () => {
        const wrapper = mountPage();

        // Skill 1 has proficiency 4 — all four filled dots are blue (the
        // current level's color), the 5th is unfilled (gray).
        for (const n of [1, 2, 3, 4]) {
            expect(
                wrapper.get(`[data-testid="skills-list-page-skill-1-dots-${n}"]`).classes(),
            ).toContain('bg-primary-600');
        }
        expect(wrapper.get('[data-testid="skills-list-page-skill-1-dots-5"]').classes()).not.toContain(
            'bg-violet-500',
        );

        // Skill 3 has proficiency 5 — all five dots are violet.
        for (const n of [1, 2, 3, 4, 5]) {
            expect(
                wrapper.get(`[data-testid="skills-list-page-skill-3-dots-${n}"]`).classes(),
            ).toContain('bg-violet-500');
        }

        // ONE title on the whole indicator — the current level's name;
        // the individual dots carry no titles.
        expect(
            wrapper.get('[data-testid="skills-list-page-skill-1-dots"]').attributes('title'),
        ).toBe('Proficient');
        expect(wrapper.get('[data-testid="skills-list-page-skill-1-dots-2"]').attributes('title')).toBe(
            undefined,
        );
    });

    it('renders the proficiency dots as display-only (no buttons, no live PUT — 5.2 rework)', () => {
        const wrapper = mountPage();

        // The dots are plain spans — too small to click, proficiency is
        // edited via the inline dropdown instead.
        expect(
            wrapper.get('[data-testid="skills-list-page-skill-1-dots-3"]').element.tagName,
        ).toBe('SPAN');
        expect(
            wrapper
                .get('[data-testid="skills-list-page-skill-1-dots"]')
                .findAll('button'),
        ).toHaveLength(0);

        // Clicking a dot does nothing (no PUT).
        wrapper.get('[data-testid="skills-list-page-skill-1-dots-2"]').trigger('click');
        expect(putMock).not.toHaveBeenCalled();
    });

    it('shows the empty-group hint inside a group without skills', () => {
        const wrapper = mountPage({
            skillGroups: [
                {
                    id: 1,
                    name: 'Empty',
                    names: { en: 'Empty', ru: 'Пусто' },
                    icon: null,
                    sort_order: 0,
                    skills: [],
                },
            ],
        });

        expect(wrapper.get('[data-testid="skills-list-page-group-1-empty"]').text()).toBe(
            'No skills in this group yet.',
        );
    });

    it('toggles include-in-resume live', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-2-resume"]').setValue(true);

        expect(putMock).toHaveBeenCalledWith(
            '/admin/skills/2',
            expect.objectContaining({
                include_in_resume: true,
                name: 'PHP',
                skill_group_id: 1,
            }),
            expect.any(Object),
        );
    });

    it('offers devicon icons in the new-skill icon picker (ADR-0023 regression)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-add-skill"]').trigger('click');
        await wrapper.get('[data-testid="skills-list-page-new-skill-1-icon"]').trigger('click');

        await vi.waitFor(
            () => {
                expect(
                    wrapper.find('[data-testid="skills-list-page-new-skill-1-icon-icon-devicon:laravel"]')
                        .exists(),
                ).toBe(true);
            },
            { timeout: 5000 },
        );
        expect(
            wrapper.find('[data-testid="skills-list-page-new-skill-1-icon-icon-heroicons:bolt"]')
                .exists(),
        ).toBe(false);
    });

    it('deletes a skill after confirmation (DELETE /admin/skills/{id})', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-3-delete"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-delete-dialog"]').exists()).toBe(true);

        await wrapper.get('[data-testid="skills-list-page-delete-dialog-confirm"]').trigger('click');

        expect(deleteMock).toHaveBeenCalledWith('/admin/skills/3', expect.any(Object));
    });

    it('disables the keyboard move buttons at the list ends', () => {
        const wrapper = mountPage();

        expect(
            wrapper.get('[data-testid="skills-list-page-group-1-up"]').attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper.get('[data-testid="skills-list-page-group-2-down"]').attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper.get('[data-testid="skills-list-page-skill-1-up"]').attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper.get('[data-testid="skills-list-page-skill-3-down"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('moves a skill up with the keyboard alternative and posts the reorder', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-2-up"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/skills/reorder',
            {
                skills: [
                    { id: 2, skill_group_id: 1 },
                    { id: 1, skill_group_id: 1 },
                    { id: 3, skill_group_id: 2 },
                ],
            },
            expect.any(Object),
        );
    });

    it('moves a group down with the keyboard alternative and posts the reorder', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-1-down"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/skill-groups/reorder',
            { ids: [2, 1] },
            expect.any(Object),
        );
    });

    it('posts the flattened ADR-0005 payload after a within-group drag', async () => {
        const wrapper = mountPage();

        const sortables = wrapper.findAllComponents({ name: 'SortableList' });
        // [0] = groups list, [1] = group 1 skills, [2] = group 2 skills
        sortables[1].vm.$emit('update:modelValue', [GROUPS[0].skills[1], GROUPS[0].skills[0]]);
        sortables[1].vm.$emit('drag-end', [GROUPS[0].skills[1], GROUPS[0].skills[0]], null);

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(postMock).toHaveBeenCalledWith(
            '/admin/skills/reorder',
            {
                skills: [
                    { id: 2, skill_group_id: 1 },
                    { id: 1, skill_group_id: 1 },
                    { id: 3, skill_group_id: 2 },
                ],
            },
            expect.any(Object),
        );
    });

    it('moves a skill between groups on a cross-list drop (ADR-0005)', async () => {
        const wrapper = mountPage();

        const sortables = wrapper.findAllComponents({ name: 'SortableList' });
        const moved = GROUPS[0].skills[0];

        sortables[2].vm.$emit('update:modelValue', [GROUPS[1].skills[0], moved]);
        sortables[2].vm.$emit('drag-end', [GROUPS[1].skills[0], moved], {
            item: moved,
            from_list_id: 'skills-1',
        });

        await new Promise((resolve) => setTimeout(resolve, 0));

        // The moved skill is removed from group 1 and posted under group 2.
        expect(postMock).toHaveBeenCalledWith(
            '/admin/skills/reorder',
            {
                skills: [
                    { id: 2, skill_group_id: 1 },
                    { id: 3, skill_group_id: 2 },
                    { id: 1, skill_group_id: 2 },
                ],
            },
            expect.any(Object),
        );
    });

    it('posts the group ids after a group drag', async () => {
        const wrapper = mountPage();

        const sortables = wrapper.findAllComponents({ name: 'SortableList' });
        sortables[0].vm.$emit('update:modelValue', [GROUPS[1], GROUPS[0]]);
        sortables[0].vm.$emit('drag-end', [GROUPS[1], GROUPS[0]], null);

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(postMock).toHaveBeenCalledWith(
            '/admin/skill-groups/reorder',
            { ids: [2, 1] },
            expect.any(Object),
        );
    });

    it('cancels inline editing on the Escape shortcut (ADR-0024)', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-skill-1"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-skill-1-name"]').exists()).toBe(true);

        window.dispatchEvent(new Event('admin:shortcut-escape'));
        await nextTick();

        expect(wrapper.find('[data-testid="skills-list-page-skill-1-name"]').exists()).toBe(false);
    });

    it('closes the new-skill editor when a server re-render arrives', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="skills-list-page-group-2-add-skill"]').trigger('click');
        expect(wrapper.find('[data-testid="skills-list-page-new-skill-2"]').exists()).toBe(true);

        // A fresh array reference — the watch compares the prop value.
        await wrapper.setProps({
            skillGroups: GROUPS.map((group) => ({ ...group, skills: [...group.skills] })),
        });

        expect(wrapper.find('[data-testid="skills-list-page-new-skill-2"]').exists()).toBe(false);
    });
});
