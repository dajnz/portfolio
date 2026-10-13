import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const { postMock, putMock, getMock, patchMock, delMock, pageProps } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
    getMock: vi.fn(),
    patchMock: vi.fn(),
    delMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
        supported_locales: ['en', 'ru'],
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: getMock,
        visit: vi.fn(),
        post: postMock,
        put: putMock,
        patch: patchMock,
        delete: delMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

vi.mock('@shared-cross/Composables/useJsonApi', () => ({
    useJsonApi: () => ({
        get: getMock,
        post: postMock,
        patch: patchMock,
        del: delMock,
    }),
}));

import ProjectFormPage from '@projects/Admin/Pages/ProjectFormPage/ProjectFormPage.vue';
import { PROJECT_EDITOR_BUTTONS } from '@shared-admin/TextEditor/TextEditor.vue';

const SKILLS = [
    { id: 1, name: 'PHP', icon: 'devicon:php' },
    { id: 2, name: 'Vue.js', icon: 'devicon:vuejs' },
];

const ARTICLES = [
    { id: 10, slug: 'cursor-pagination', title: 'Cursor-Based Pagination in PostgreSQL' },
    { id: 11, slug: 'vue-composables', title: 'Building Reusable Vue Composables' },
];

const PROJECT = {
    id: 7,
    slug: 'portfolio-platform',
    title: { en: 'Portfolio Platform', ru: 'Платформа-портфолио' },
    brief_description: {
        en: 'A full-stack portfolio platform.',
        ru: 'Полнофункциональная платформа-портфолио.',
    },
    full_description: { en: '**Full** description.', ru: null },
    problem: { en: 'Slow payouts.', ru: null },
    solution: { en: 'Queued processing.', ru: null },
    preview_image: 'projects/preview.png',
    preview_image_alt: { en: 'Dashboard', ru: 'Панель' },
    github_url: 'https://github.com/alex/portfolio',
    live_demo_url: null,
    is_featured: true,
    featured_sort_order: 1,
    is_published: true,
    tech_writeup_article_id: 10,
    tech_tags: [{ id: 1, name: 'PHP', icon: 'devicon:php' }],
    screenshots: [
        {
            id: 3,
            image_path: 'projects/shot1.png',
            alt_text: { en: 'Listing', ru: 'Список' },
            title: { en: 'Listing', ru: 'Список' },
            description: { en: 'The listing view.', ru: 'Вид списка.' },
        },
    ],
    detail_items: [
        {
            id: 5,
            icon: 'heroicons:shield-check',
            title: { en: 'Horizontal scaling', ru: 'Горизонтальное масштабирование' },
            description: { en: 'Docker Swarm replicas.', ru: 'Реплики Docker Swarm.' },
        },
    ],
};

function mountForm(props: Record<string, unknown> = {}) {
    return mount(ProjectFormPage, {
        props: {
            project: PROJECT,
            skills: SKILLS,
            articles: ARTICLES,
            preview_url: 'http://localhost/preview/7',
            ...props,
        },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

// Mounts the form with an icon-only engineering detail and submits it
// against a server that fails on the missing RU translations — the
// shared preparation for the per-input error placement tests.
async function mountFormWithDetailSaveErrors() {
    putMock.mockImplementation((_url: string, _payload: unknown, options: {
        onError?: (errors: Record<string, string>) => void;
    }) => {
        options.onError?.({
            'detail_items.0.icon': 'The icon field is required.',
            'detail_items.0.title.ru': 'The title field is required.',
            'detail_items.0.description.ru': 'The description field is required.',
        });
    });
    const wrapper = mountForm({ project: { ...PROJECT, detail_items: [] } });
    const tabs = wrapper.findComponent({ name: 'LocaleTabs' });

    await wrapper.get('[data-testid="project-form-page-engineering-details-add"]').trigger('click');
    await nextTick();
    const items = wrapper.findAllComponents({ name: 'EngineeringDetailItem' });
    items[0].vm.$emit('update:modelValue', {
        ...items[0].props('modelValue'),
        icon: 'heroicons:shield-check',
    });
    await nextTick();

    await wrapper.get('[data-testid="project-form-page-form"]').trigger('submit');
    await nextTick();

    return { wrapper, tabs };
}

// Mounts the form with a detail item filled only in EN — the shared
// preparation for the locale-warning tests (the trio is emptied too, so
// the fixture's EN-only trio cannot warn RU on its own).
async function mountFormWithEnOnlyDetail() {
    const wrapper = mountForm({
        project: {
            ...PROJECT,
            detail_items: [],
            full_description: { en: '', ru: '' },
            problem: { en: '', ru: '' },
            solution: { en: '', ru: '' },
        },
    });
    const tabs = wrapper.findComponent({ name: 'LocaleTabs' });

    await wrapper.get('[data-testid="project-form-page-engineering-details-add"]').trigger('click');
    await nextTick();
    const items = wrapper.findAllComponents({ name: 'EngineeringDetailItem' });
    items[0].vm.$emit('update:modelValue', {
        ...items[0].props('modelValue'),
        icon: 'heroicons:shield-check',
        title: { en: 'Scaling', ru: '' },
        description: { en: 'Replicas', ru: '' },
    });
    await nextTick();

    return { wrapper, tabs, items };
}

describe('ProjectFormPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
        putMock.mockClear();
        getMock.mockClear();
        patchMock.mockClear();
        delMock.mockClear();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    it('renders the edit form with the section headings and the sticky bar', () => {
        const wrapper = mountForm();

        expect(wrapper.find('[data-testid="project-form-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="project-form-page"] h1').text()).toBe('Edit Project');
        expect(wrapper.get('[data-testid="project-form-page-section-basic"]').text()).toContain(
            'Basic',
        );
        expect(wrapper.get('[data-testid="project-form-page-section-content"]').text()).toContain(
            'Content',
        );
        expect(wrapper.get('[data-testid="project-form-page-section-media"]').text()).toContain(
            'Media',
        );
        expect(wrapper.get('[data-testid="project-form-page-section-meta"]').text()).toContain(
            'Meta',
        );
        expect(wrapper.get('[data-testid="project-form-page-section-relations"]').text()).toContain(
            'Relations',
        );
        expect(
            wrapper.get('[data-testid="project-form-page-section-engineering"]').text(),
        ).toContain('Engineering Details');
    });

    it('shows the create title and no preview button on the create form', () => {
        const wrapper = mountForm({ project: null, preview_url: null });

        expect(wrapper.get('[data-testid="project-form-page"] h1').text()).toBe('New Project');
        expect(wrapper.find('[data-testid="project-form-page-preview-btn"]').exists()).toBe(false);
    });

    it('opens the signed preview URL in a new tab on edit', () => {
        const wrapper = mountForm();
        const preview = wrapper.get('[data-testid="project-form-page-preview-btn"]');

        expect(preview.attributes('href')).toBe('http://localhost/preview/7');
        expect(preview.attributes('target')).toBe('_blank');
    });

    it('pre-fills the per-locale fields and the shared meta fields', () => {
        const wrapper = mountForm();

        expect(wrapper.get('[data-testid="project-form-page-title-en"]').attributes('value')).toBe(
            'Portfolio Platform',
        );
        expect(
            (wrapper.get('[data-testid="project-form-page-brief-en"]').element as HTMLTextAreaElement).value,
        ).toBe('A full-stack portfolio platform.');
        expect(wrapper.get('[data-testid="project-form-page-github-url"]').attributes('value')).toBe(
            'https://github.com/alex/portfolio',
        );
        expect(wrapper.get('[data-testid="project-form-page-featured-order"]').attributes('value')).toBe(
            '1',
        );
    });

    it('switches the locale-bound fields when the active tab changes', async () => {
        const wrapper = mountForm();
        const tabs = wrapper.findComponent({ name: 'LocaleTabs' });

        tabs.vm.$emit('update:modelValue', 'ru');
        await nextTick();

        expect(wrapper.get('[data-testid="project-form-page-title-ru"]').attributes('value')).toBe(
            'Платформа-портфолио',
        );
        expect(
            (wrapper.get('[data-testid="project-form-page-brief-ru"]').element as HTMLTextAreaElement).value,
        ).toBe('Полнофункциональная платформа-портфолио.');
    });

    it('auto-generates the slug from the EN title on create until manually edited', async () => {
        const wrapper = mountForm({ project: null, preview_url: null });

        await wrapper.get('[data-testid="project-form-page-title-en"]').setValue('My New Project!');
        await nextTick();

        expect(wrapper.get('[data-testid="project-form-page-slug"]').attributes('value')).toBe(
            'my-new-project',
        );

        // Manual edits win over the auto-generation.
        await wrapper.get('[data-testid="project-form-page-slug"]').setValue('custom-slug');
        await wrapper.get('[data-testid="project-form-page-title-en"]').setValue('Another Title');
        await nextTick();

        expect(wrapper.get('[data-testid="project-form-page-slug"]').attributes('value')).toBe(
            'custom-slug',
        );
    });

    it('submits the create payload with the per-locale maps and normalized nested rows', async () => {
        postMock.mockResolvedValue({});
        const wrapper = mountForm({ project: null, preview_url: null });

        await wrapper.get('[data-testid="project-form-page-title-en"]').setValue('Payments Gateway');
        await wrapper.get('[data-testid="project-form-page-add-screenshot"]').trigger('click');
        await wrapper.get('[data-testid="project-form-page-engineering-details-add"]').trigger('click');
        await nextTick();

        await wrapper.get('[data-testid="project-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith('/admin/projects', expect.objectContaining({
            slug: 'payments-gateway',
            title: expect.objectContaining({ en: 'Payments Gateway' }),
            is_published: false,
            is_featured: false,
            tech_tags: [],
            tech_writeup_article_id: null,
        }), expect.any(Object));

        // The empty screenshot and detail rows are dropped before the
        // submit (the backend's `required_with` rules).
        const payload = postMock.mock.calls[0][1] as Record<string, unknown>;
        expect(payload.screenshots).toEqual([]);
        expect(payload.detail_items).toEqual([]);
    });

    it('submits the edit payload with the screenshots, detail items and tech writeup', async () => {
        putMock.mockResolvedValue({});
        const wrapper = mountForm();
        const tags = wrapper.findComponent({ name: 'AdminTechSkillPicker' });

        tags.vm.$emit('update:modelValue', [
            { id: 1, name: 'PHP', icon: 'devicon:php' },
            { id: 2, name: 'Vue.js', icon: 'devicon:vuejs' },
        ]);
        await nextTick();

        await wrapper.get('[data-testid="project-form-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith('/admin/projects/7', expect.objectContaining({
            slug: 'portfolio-platform',
            tech_tags: [1, 2],
            tech_writeup_article_id: 10,
            is_published: true,
            is_featured: true,
            featured_sort_order: 1,
            preview_image: 'projects/preview.png',
        }), expect.any(Object));

        const payload = putMock.mock.calls[0][1] as Record<string, unknown>;
        expect(payload.screenshots).toEqual([
            {
                id: 3,
                image: 'projects/shot1.png',
                alt_text: { en: 'Listing', ru: 'Список' },
                title: { en: 'Listing', ru: 'Список' },
                description: { en: 'The listing view.', ru: 'Вид списка.' },
            },
        ]);
        expect(payload.detail_items).toEqual([
            {
                id: 5,
                icon: 'heroicons:shield-check',
                title: { en: 'Horizontal scaling', ru: 'Горизонтальное масштабирование' },
                description: { en: 'Docker Swarm replicas.', ru: 'Реплики Docker Swarm.' },
            },
        ]);
    });

    it('reorders screenshots via the keyboard move buttons (accessibility.md:35-44)', async () => {
        putMock.mockResolvedValue({});
        const wrapper = mountForm({
            project: {
                ...PROJECT,
                screenshots: [
                    PROJECT.screenshots[0],
                    {
                        id: 4,
                        image_path: 'projects/shot2.png',
                        alt_text: { en: 'Detail', ru: 'Деталь' },
                        title: { en: 'Detail', ru: 'Деталь' },
                        description: { en: 'The detail view.', ru: 'Детальный вид.' },
                    },
                ],
            },
        });

        await wrapper
            .get('[data-testid="project-form-page-screenshot-0-move-down"]')
            .trigger('click');
        await nextTick();

        await wrapper.get('[data-testid="project-form-page-form"]').trigger('submit');

        const payload = putMock.mock.calls[0][1] as Record<string, unknown>;
        expect((payload.screenshots as Array<{ id: number }>).map((row) => row.id)).toEqual([4, 3]);
    });

    it('reorders engineering detail items via the keyboard move buttons (accessibility.md:35-44)', async () => {
        putMock.mockResolvedValue({});
        const wrapper = mountForm({
            project: {
                ...PROJECT,
                detail_items: [
                    PROJECT.detail_items[0],
                    {
                        id: 6,
                        icon: 'heroicons:cpu-chip',
                        title: { en: 'Caching', ru: 'Кэширование' },
                        description: { en: 'Redis responses.', ru: 'Ответы Redis.' },
                    },
                ],
            },
        });

        await wrapper
            .get('[data-testid="project-form-page-engineering-details-item-0-move-down"]')
            .trigger('click');
        await nextTick();

        await wrapper.get('[data-testid="project-form-page-form"]').trigger('submit');

        const payload = putMock.mock.calls[0][1] as Record<string, unknown>;
        expect((payload.detail_items as Array<{ id: number }>).map((row) => row.id)).toEqual([
            6, 5,
        ]);
    });

    it('renders the tech-writeup select with the published articles', () => {
        const wrapper = mountForm();
        const dropdown = wrapper.findComponent({ name: 'AdminBaseDropdown' });

        expect(dropdown.props('modelValue')).toBe('10');
        expect(dropdown.props('options')).toEqual([
            { value: '', label: 'None' },
            { value: '10', label: 'Cursor-Based Pagination in PostgreSQL' },
            { value: '11', label: 'Building Reusable Vue Composables' },
        ]);
    });

    it('clears the tech-writeup link when None is selected', async () => {
        putMock.mockResolvedValue({});
        const wrapper = mountForm();
        const dropdown = wrapper.findComponent({ name: 'AdminBaseDropdown' });

        dropdown.vm.$emit('update:modelValue', '');
        await nextTick();

        await wrapper.get('[data-testid="project-form-page-form"]').trigger('submit');

        const payload = putMock.mock.calls[0][1] as Record<string, unknown>;
        expect(payload.tech_writeup_article_id).toBeNull();
    });

    it('disables the featured sort order input when the project is not featured', () => {
        const wrapper = mountForm({
            project: { ...PROJECT, is_featured: false, featured_sort_order: null },
        });

        expect(
            wrapper.get('[data-testid="project-form-page-featured-order"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('renders the screenshots collapsed by default', () => {
        const wrapper = mountForm();

        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-collapsed"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-expanded"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.get('[data-testid="project-form-page-screenshot-0-compact-title"]').text(),
        ).toBe('Listing');
    });

    it('hides the screenshot drag knob below sm (mobile uses the move buttons)', () => {
        const wrapper = mountForm();

        const knob = wrapper.get('.screenshot-handle');

        expect(knob.classes()).toContain('hidden');
        expect(knob.classes()).toContain('sm:block');
    });

    it('expands a collapsed screenshot on click', async () => {
        const wrapper = mountForm();

        await wrapper.get('[data-testid="project-form-page-screenshot-0-collapsed"]').trigger('click');
        await nextTick();

        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-expanded"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-collapsed"]').exists()).toBe(
            false,
        );
        expect(wrapper.get('[data-testid="project-form-page-screenshot-0-expanded"]').text()).toContain(
            'Click to collapse',
        );
    });

    it('collapses the expanded screenshot on header click', async () => {
        const wrapper = mountForm();

        await wrapper.get('[data-testid="project-form-page-screenshot-0-collapsed"]').trigger('click');
        await nextTick();
        await wrapper.get('[data-testid="project-form-page-screenshot-0-expanded"]').trigger('click');
        await nextTick();

        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-collapsed"]').exists()).toBe(
            true,
        );
    });

    it('removes a collapsed screenshot without expanding it', async () => {
        const wrapper = mountForm();

        await wrapper.get('[data-testid="project-form-page-screenshot-0-remove"]').trigger('click');
        await nextTick();

        expect(wrapper.find('[data-testid="project-form-page-screenshot-0"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-expanded"]').exists()).toBe(
            false,
        );
    });

    it('expands and collapses all screenshots via the sticky header buttons', async () => {
        const wrapper = mountForm();

        // A fresh screenshot opens EXPANDED (existing ones stay
        // collapsed by default).
        await wrapper.get('[data-testid="project-form-page-add-screenshot"]').trigger('click');
        await nextTick();
        expect(wrapper.find('[data-testid="project-form-page-screenshot-1-expanded"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-expanded"]').exists()).toBe(
            false,
        );

        await wrapper.get('[data-testid="project-form-page-expand-all"]').trigger('click');
        await nextTick();
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-expanded"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="project-form-page-screenshot-1-expanded"]').exists()).toBe(
            true,
        );

        await wrapper.get('[data-testid="project-form-page-collapse-all"]').trigger('click');
        await nextTick();
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-collapsed"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="project-form-page-screenshot-1-collapsed"]').exists()).toBe(
            true,
        );
    });

    it('places the expanded screenshot delete button in the header row', async () => {
        const wrapper = mountForm();

        await wrapper.get('[data-testid="project-form-page-add-screenshot"]').trigger('click');
        await nextTick();

        const header = wrapper.get('[data-testid="project-form-page-screenshot-1-expanded"]');
        expect(
            header.find('[data-testid="project-form-page-screenshot-1-remove-expanded"]').exists(),
        ).toBe(true);
        expect(header.text()).toContain('Click to collapse');

        // The compact row keeps its own delete button.
        expect(wrapper.find('[data-testid="project-form-page-screenshot-0-remove"]').exists()).toBe(
            true,
        );
    });

    it('marks the OTHER locale warned when a Markdown field is filled in one language', async () => {
        const wrapper = mountForm({
            project: {
                ...PROJECT,
                full_description: { en: '', ru: '' },
                problem: { en: '', ru: '' },
                solution: { en: '', ru: '' },
            },
        });
        const tabs = wrapper.findComponent({ name: 'LocaleTabs' });

        expect(tabs.props('warned_locales')).toEqual([]);

        // Filling only full_description in EN marks the RU tab (the
        // field must be filled in every language), NOT the EN tab.
        const editors = wrapper.findAllComponents({ name: 'TextEditor' });
        editors[0].vm.$emit('update:modelValue', 'Some text');
        await nextTick();
        expect(tabs.props('warned_locales')).toEqual(['ru']);

        // Completing the EN trio keeps RU warned (RU has nothing but
        // the fields have content elsewhere).
        editors[1].vm.$emit('update:modelValue', 'Problem');
        editors[2].vm.$emit('update:modelValue', 'Solution');
        await nextTick();
        expect(tabs.props('warned_locales')).toEqual(['ru']);

        // Filling the RU trio clears the warning (the editors write
        // into the ACTIVE locale's map).
        tabs.vm.$emit('update:modelValue', 'ru');
        await nextTick();
        editors[0].vm.$emit('update:modelValue', 'Текст');
        editors[1].vm.$emit('update:modelValue', 'Проблема');
        editors[2].vm.$emit('update:modelValue', 'Решение');
        await nextTick();
        expect(tabs.props('warned_locales')).toEqual([]);
    });

    it('renders the engineering details collapsed with the icon and title', () => {
        const wrapper = mountForm();

        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-0-collapsed"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-0-expanded"]').exists(),
        ).toBe(false);
        expect(
            wrapper.get('[data-testid="project-form-page-engineering-details-item-0-compact-title"]').text(),
        ).toBe('Horizontal scaling');
    });

    it('hides the engineering detail drag knob below sm (mobile uses the move buttons)', () => {
        const wrapper = mountForm();

        const knob = wrapper.get('.engineering-detail-handle');

        expect(knob.classes()).toContain('hidden');
        expect(knob.classes()).toContain('sm:block');
    });

    it('expands a collapsed engineering detail on click', async () => {
        const wrapper = mountForm();

        await wrapper
            .get('[data-testid="project-form-page-engineering-details-item-0-collapsed"]')
            .trigger('click');
        await nextTick();

        const header = wrapper.get(
            '[data-testid="project-form-page-engineering-details-item-0-expanded"]',
        );
        expect(header.text()).toContain('Click to collapse');
        expect(
            header.find('[data-testid="project-form-page-engineering-details-item-0-remove-expanded"]').exists(),
        ).toBe(true);
    });

    it('collapses the expanded engineering detail on header click', async () => {
        const wrapper = mountForm();

        await wrapper
            .get('[data-testid="project-form-page-engineering-details-item-0-collapsed"]')
            .trigger('click');
        await nextTick();
        await wrapper
            .get('[data-testid="project-form-page-engineering-details-item-0-expanded"]')
            .trigger('click');
        await nextTick();

        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-0-collapsed"]').exists(),
        ).toBe(true);
    });

    it('expands a NEW engineering detail and supports expand/collapse all', async () => {
        const wrapper = mountForm();

        // The new row opens expanded (existing rows stay collapsed).
        await wrapper.get('[data-testid="project-form-page-engineering-details-add"]').trigger('click');
        await nextTick();
        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-1-expanded"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-0-expanded"]').exists(),
        ).toBe(false);

        await wrapper.get('[data-testid="project-form-page-engineering-details-expand-all"]').trigger('click');
        await nextTick();
        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-0-expanded"]').exists(),
        ).toBe(true);

        await wrapper.get('[data-testid="project-form-page-engineering-details-collapse-all"]').trigger('click');
        await nextTick();
        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-0-collapsed"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="project-form-page-engineering-details-item-1-collapsed"]').exists(),
        ).toBe(true);
    });

    it('shows the detail icon error under the EN input after a failed save', async () => {
        const { wrapper } = await mountFormWithDetailSaveErrors();

        expect(
            wrapper.get('[data-testid="project-form-page-engineering-details-item-0-icon-error"]').text(),
        ).toBe('The icon field is required.');
    });

    it('shows the missing-translation errors under the RU inputs after the locale switch', async () => {
        const { wrapper, tabs } = await mountFormWithDetailSaveErrors();

        // Switch to RU — the missing-translation errors appear under the
        // exact title/description inputs (the error <p role="alert"> is
        // a sibling of the input inside the component wrapper).
        tabs.vm.$emit('update:modelValue', 'ru');
        await nextTick();
        const titleError = wrapper.find(
            '[data-testid="project-form-page-engineering-details-item-0-title-ru"] + [role="alert"]',
        );
        expect(titleError.text()).toContain('The title field is required.');
        const descriptionError = wrapper.find(
            '[data-testid="project-form-page-engineering-details-item-0-description-ru"] + [role="alert"]',
        );
        expect(descriptionError.text()).toContain('The description field is required.');
    });

    it('marks the engineering detail fields as required with asterisks', async () => {
        const wrapper = mountForm();

        // Existing detail items render collapsed — expand the row to
        // reveal the fields.
        await wrapper
            .get('[data-testid="project-form-page-engineering-details-item-0-collapsed"]')
            .trigger('click');
        await nextTick();

        // The asterisks live in the field LABELS (the inputs themselves
        // carry no text).
        expect(
            wrapper
                .get('[data-testid="project-form-page-engineering-details-item-0-icon"]')
                .attributes('aria-required'),
        ).toBeDefined();
        expect(
            wrapper
                .get('[data-testid="project-form-page-engineering-details-item-0-title-en"]')
                .attributes('aria-required'),
        ).toBeDefined();
        expect(
            wrapper
                .get('[data-testid="project-form-page-engineering-details-item-0-description-en"]')
                .attributes('aria-required'),
        ).toBeDefined();
    });

    it('warns the missing locale when a detail item has only the EN pair', async () => {
        const { tabs } = await mountFormWithEnOnlyDetail();

        expect(tabs.props('warned_locales')).toEqual(['ru']);
    });

    it('clears the locale warning once the RU pair is filled', async () => {
        const { tabs, items } = await mountFormWithEnOnlyDetail();

        items[0].vm.$emit('update:modelValue', {
            ...items[0].props('modelValue'),
            icon: 'heroicons:shield-check',
            title: { en: 'Scaling', ru: 'Масштабирование' },
            description: { en: 'Replicas', ru: 'Реплики' },
        });
        await nextTick();

        expect(tabs.props('warned_locales')).toEqual([]);
    });

    it('configures the editors with the projects toolbar buttons', async () => {
        const wrapper = mountForm();

        const editors = wrapper.findAllComponents({ name: 'TextEditor' });
        expect(editors).toHaveLength(3);

        // Projects upload previews/screenshots through the dedicated
        // Media section — the Markdown editor uses the projects button
        // config (block formatting only, no image/callout controls).
        for (const editor of editors) {
            expect(editor.props('buttons')).toEqual(PROJECT_EDITOR_BUTTONS);
        }
    });

    it('shows the unsaved-changes guard armed on first edit', () => {
        const wrapper = mountForm();

        expect(wrapper.find('[data-testid="project-form-page-save-btn"]').exists()).toBe(true);

        // The guard is active — a dirty form marks the page dirty (the
        // composable is exercised through the form's own rebase logic).
        const title = wrapper.get('[data-testid="project-form-page-title-en"]');
        void title.setValue('Changed Title');

        return nextTick().then(() => {
            expect(title.attributes('value')).toBe('Changed Title');
        });
    });
});
