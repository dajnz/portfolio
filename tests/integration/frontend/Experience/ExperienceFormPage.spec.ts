import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { postMock, putMock, visitMock, pageProps } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
    visitMock: vi.fn(),
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
        get: vi.fn(),
        post: postMock,
        put: putMock,
        delete: vi.fn(),
        patch: vi.fn(),
        visit: visitMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ExperienceFormPage from '@experience/Admin/Pages/ExperienceFormPage.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

const SKILLS = [
    { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
    { id: 2, name: 'PostgreSQL', icon: 'devicon:postgresql' },
];

const PROJECTS = [
    { id: 10, title: 'Portfolio Platform', slug: 'portfolio-platform' },
    { id: 11, title: 'E-commerce Engine', slug: 'ecommerce-engine' },
];

const ITEM = {
    id: 7,
    position: { en: 'Senior Backend Engineer', ru: 'Старший бэкенд-разработчик' },
    company_name: { en: 'Acme Corp', ru: 'ООО «Акме»' },
    company_country: { en: 'Germany', ru: 'Германия' },
    project_id: 10,
    external_project_name: null,
    short_description: { en: '**Led** the platform team.', ru: 'Руководил командой платформы.' },
    started_at: '2023-01-15',
    ended_at: null,
    is_published: true,
    sort_order: 0,
    include_in_resume: true,
    achievements: [
        {
            description: { en: 'Cut API p95 latency by 40%.', ru: 'Снизил p95 API на 40%.' },
            project_link: 'https://github.com/acme/latency',
        },
    ],
    tech_tags: [SKILLS[0]],
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(ExperienceFormPage, {
        props: { item: ITEM, skills: SKILLS, projects: PROJECTS, ...props },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('ExperienceFormPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
        putMock.mockClear();
        visitMock.mockClear();
        window.localStorage.clear();
        document.body.innerHTML = '';
        window.confirm = vi.fn(() => true);
    });

    it('renders within the admin layout with the edit title', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="experience-form-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="experience-form-page"] h1').text()).toBe(
            'Edit Experience',
        );
    });

    it('uses the add title when no item is provided', () => {
        const wrapper = mountPage({ item: null });

        expect(wrapper.get('[data-testid="experience-form-page"] h1').text()).toBe('Add Experience');
    });

    it('prefills the locale-tabbed fields and swaps values on tab switch', async () => {
        const wrapper = mountPage();

        const enPosition = wrapper.get('[data-testid="experience-form-page-position-en"]');
        expect((enPosition.element as HTMLInputElement).value).toBe('Senior Backend Engineer');
        expect(
            (wrapper.get('[data-testid="experience-form-page-company-name-en"]').element as HTMLInputElement)
                .value,
        ).toBe('Acme Corp');

        await wrapper.get('[data-testid="experience-form-page-locale-tabs-tab-ru"]').trigger('click');

        const ruPosition = wrapper.get('[data-testid="experience-form-page-position-ru"]');
        expect((ruPosition.element as HTMLInputElement).value).toBe('Старший бэкенд-разработчик');
        expect(
            (
                wrapper.get('[data-testid="experience-form-page-short-description-ru"]')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Руководил командой платформы.');
    });

    it('prefills the dates and shared fields', () => {
        const wrapper = mountPage();

        // AdminDatePicker displays dd.mm.yyyy (ADR-0024) — the ISO model
        // value stays internal.
        expect(wrapper.get('[data-testid="experience-form-page-started-at-value"]').text()).toBe(
            '15.01.2023',
        );
        expect(
            wrapper.find('[data-testid="experience-form-page-ended-at-value"]').exists(),
        ).toBe(false);
        expect(
            (wrapper.get('[data-testid="experience-form-page-published"]').element as HTMLInputElement)
                .checked,
        ).toBe(true);
        // No "Current" checkbox anymore (ADR-0024) — an empty end date IS the
        // ongoing marker.
        expect(wrapper.find('[data-testid="experience-form-page-current"]').exists()).toBe(false);
    });

    it('selects a portfolio project in the combobox and clears it', async () => {
        const wrapper = mountPage();

        const input = wrapper.get('[data-testid="experience-form-page-project-input"]');
        expect((input.element as HTMLInputElement).value).toBe('Portfolio Platform');
        expect(
            wrapper.find('[data-testid="experience-form-page-project-portfolio-badge"]').exists(),
        ).toBe(true);

        await input.setValue('e-commerce');
        expect(wrapper.get('[data-testid="experience-form-page-project-option-0"]').text()).toContain(
            'E-commerce Engine',
        );

        await wrapper.get('[data-testid="experience-form-page-project-option-0"]').trigger('click');
        expect(wrapper.get('[data-testid="experience-form-page-project-input"]').element).toHaveProperty(
            'value',
            'E-commerce Engine',
        );

        // The portfolio badge replaces the right-side control — clearing
        // happens by emptying the input (spec: "Clearing the input resets
        // both project_id and external_project_name to null").
        await input.setValue('');
        expect(wrapper.find('[data-testid="experience-form-page-project-portfolio-badge"]').exists()).toBe(
            false,
        );
    });

    it('accepts a custom external project name on Enter', async () => {
        const wrapper = mountPage({ item: null });

        const input = wrapper.get('[data-testid="experience-form-page-project-input"]');
        await input.setValue('My Side Project');
        await input.trigger('keydown', { key: 'Enter' });

        await wrapper.get('[data-testid="experience-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/experience',
            expect.objectContaining({
                project_id: null,
                external_project_name: 'My Side Project',
            }),
            expect.any(Object),
        );
    });

    it('commits a custom project name on blur (save without Enter)', async () => {
        const wrapper = mountPage({ item: null });

        // Clicking the Save button blurs the combobox input — the typed
        // name must be committed as the external project then, or the
        // payload saves null and the name disappears after the round-trip.
        const input = wrapper.get('[data-testid="experience-form-page-project-input"]');
        await input.setValue('My Side Project');
        await input.trigger('blur');

        await wrapper.get('[data-testid="experience-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/experience',
            expect.objectContaining({
                project_id: null,
                external_project_name: 'My Side Project',
            }),
            expect.any(Object),
        );
    });

    it('adds, edits and removes achievements and reorders them via move buttons', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="experience-form-page-achievements-add"]').trigger('click');
        expect(
            wrapper.find('[data-testid="experience-form-page-achievements-item-1"]').exists(),
        ).toBe(true);

        const newDescription = wrapper.get(
            '[data-testid="experience-form-page-achievements-item-1-description-en"]',
        );
        await newDescription.setValue('Shipped the billing module.');

        await wrapper.get('[data-testid="experience-form-page-achievements-item-1-move-up"]').trigger('click');

        expect(
            wrapper.get('[data-testid="experience-form-page-achievements-item-0-description-en"]')
                .element as HTMLTextAreaElement,
        ).toHaveProperty('value', 'Shipped the billing module.');

        await wrapper
            .get('[data-testid="experience-form-page-achievements-item-1-remove"]')
            .trigger('click');
        expect(
            wrapper.find('[data-testid="experience-form-page-achievements-item-1"]').exists(),
        ).toBe(false);
    });

    it('submits the create payload with the core fields and the calendar-picked date', async () => {
        const wrapper = mountPage({ item: null });

        await wrapper.get('[data-testid="experience-form-page-position-en"]').setValue('Engineer');
        await wrapper
            .get('[data-testid="experience-form-page-company-name-en"]')
            .setValue('Example Ltd');
        await wrapper.get('[data-testid="experience-form-page-country-en"]').setValue('UK');
        await wrapper
            .get('[data-testid="experience-form-page-short-description-en"]')
            .setValue('Built core services.');

        // The date comes from the calendar picker (ADR-0024 — no manual
        // editing): open the started-at picker and click the 15th of the
        // currently shown month.
        await wrapper.get('[data-testid="experience-form-page-started-at-field"]').trigger('click');
        const today = new Date();
        const startedIso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-15`;
        await wrapper
            .get(`[data-testid="experience-form-page-started-at-day-15-${String(today.getMonth() + 1).padStart(2, '0')}-${today.getFullYear()}"]`)
            .trigger('click');

        await wrapper.get('[data-testid="experience-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/experience',
            expect.objectContaining({
                position: { en: 'Engineer', ru: '' },
                company_name: { en: 'Example Ltd', ru: '' },
                company_country: { en: 'UK', ru: '' },
                short_description: { en: 'Built core services.', ru: '' },
                started_at: startedIso,
                ended_at: '',
                achievements: [],
                tech_tags: [],
                include_in_resume: true,
                is_published: false,
                sort_order: 0,
            }),
            expect.any(Object),
        );
    });

    it('drops empty achievements from the create payload', async () => {
        const wrapper = mountPage({ item: null });

        await wrapper.get('[data-testid="experience-form-page-achievements-add"]').trigger('click');
        await wrapper
            .get('[data-testid="experience-form-page-achievements-item-0-description-en"]')
            .setValue('Cut p95 latency by 40%.');
        await wrapper
            .get('[data-testid="experience-form-page-achievements-item-0-project-link"]')
            .setValue('https://github.com/acme/latency');
        // The second achievement stays empty — the form must not send it.
        await wrapper.get('[data-testid="experience-form-page-achievements-add"]').trigger('click');

        await wrapper.get('[data-testid="experience-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/experience',
            expect.objectContaining({
                achievements: [
                    {
                        description: { en: 'Cut p95 latency by 40%.', ru: '' },
                        project_link: 'https://github.com/acme/latency',
                    },
                ],
            }),
            expect.any(Object),
        );
    });

    it('submits with PUT on edit and keeps the provided ended_at', async () => {
        const wrapper = mountPage({ item: { ...ITEM, ended_at: '2026-02-28' } });

        await wrapper.get('[data-testid="experience-form-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/experience/7',
            expect.objectContaining({
                project_id: 10,
                external_project_name: null,
                started_at: '2023-01-15',
                ended_at: '2026-02-28',
                tech_tags: [1],
                sort_order: 0,
            }),
            expect.any(Object),
        );
    });

    it('shows per-locale validation errors and the translation-incomplete toast', async () => {
        postMock.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options: { onError?: (errors: Record<string, string>) => void },
            ) => {
                options.onError?.({
                    'short_description.ru': 'The short_description.ru field is required.',
                });
            },
        );

        const wrapper = mountPage({ item: null });
        await wrapper.get('[data-testid="experience-form-page-form"]').trigger('submit');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'The Russian translation is incomplete.',
        });
    });

    it('warns the tab of a locale with a missing required translation', async () => {
        const wrapper = mountPage({ item: { ...ITEM, short_description: { en: 'Only en.', ru: '' } } });

        expect(wrapper.find('[data-testid="experience-form-page-locale-tabs-warn-ru"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="experience-form-page-locale-tabs-warn-en"]').exists()).toBe(
            false,
        );
    });

    it('keeps the back link, locale tabs and save button in the sticky bar (no cancel — ADR-0024)', () => {
        const wrapper = mountPage();

        const bar = wrapper.get('[data-testid="experience-form-page-sticky-bar"]');
        expect(bar.classes()).toContain('sticky');
        expect(bar.find('[data-testid="experience-form-page-back-btn"]').attributes('href')).toBe(
            '/admin/experience',
        );
        expect(bar.find('[data-testid="experience-form-page-locale-tabs"]').exists()).toBe(true);
        expect(bar.find('[data-testid="experience-form-page-save-btn"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="experience-form-page-cancel-btn"]').exists()).toBe(false);
        // The sticky bar sits above the form card — the save button is
        // linked to the form via the `form` attribute.
        expect(
            wrapper
                .get('[data-testid="experience-form-page-save-btn"]')
                .attributes('form'),
        ).toBe('experience-form');
    });
});
