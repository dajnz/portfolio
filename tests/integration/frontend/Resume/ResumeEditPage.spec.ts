import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { putMock, visitMock, pageProps } = vi.hoisted(() => ({
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
        put: putMock,
        visit: visitMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ResumeEditPage from '@resume/Admin/Pages/ResumeEditPage.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';
import type { ResumeLayout } from '@resume/Admin/Types/layout';

const LAYOUT: ResumeLayout = {
    template: 'modern',
    sections: {
        header: { visible: true, order: 0 },
        about_me: { visible: true, order: 1 },
        skills: { visible: true, order: 2 },
        experience: { visible: true, order: 3 },
        contacts: { visible: true, order: 4 },
    },
};

const PROPS = {
    mode: 'auto' as 'auto' | 'manual',
    layout: LAYOUT,
    custom_resume_file: null,
    about_texts: { en: 'Custom about.', ru: 'Своё «обо мне».' },
    included_skills_count: 12,
    included_experience_count: 5,
    included_contacts_count: 3,
};

function mountPage(props: Record<string, unknown> = {}) {
    const wrapper = mount(ResumeEditPage, {
        props: { ...PROPS, ...props },
    });
    mountedWrappers.push(wrapper);

    return wrapper;
}

// The unsaved-changes guard registers window listeners per mount — every
// wrapper must be unmounted between tests or stale dirty guards would
// trip the beforeunload assertions of later tests.
let mountedWrappers: Array<ReturnType<typeof mount>> = [];

describe('ResumeEditPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        putMock.mockClear();
        visitMock.mockClear();
        pageProps.locale = 'en';
        pageProps.supported_locales = ['en', 'ru'];
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        for (const wrapper of mountedWrappers) {
            wrapper.unmount();
        }
        mountedWrappers = [];
    });

    it('renders within the admin layout with the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="resume-edit-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="resume-edit-page"] h1').text()).toBe('Resume Settings');
    });

    it('renders the auto-mode block with the template, sections, about text, summary and actions', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="resume-edit-page-auto-block"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="resume-edit-page-template"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="resume-edit-page-sections"]').exists()).toBe(true);
        expect(
            (wrapper.get('[data-testid="resume-edit-page-about-en"]').element as HTMLTextAreaElement).value,
        ).toBe('Custom about.');
        expect(
            wrapper.get('[data-testid="resume-edit-page-included-skills"]').text(),
        ).toContain('12');
        expect(
            wrapper.get('[data-testid="resume-edit-page-included-experience"]').text(),
        ).toContain('5');
        expect(
            wrapper.get('[data-testid="resume-edit-page-included-contacts"]').text(),
        ).toContain('3');
    });

    it('swaps the about text on locale tab switch', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="resume-edit-page-locale-tabs-tab-ru"]').trigger('click');

        expect(
            (wrapper.get('[data-testid="resume-edit-page-about-ru"]').element as HTMLTextAreaElement).value,
        ).toBe('Своё «обо мне».');
    });

    it('auto-saves the mode switch without the about text', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="resume-edit-page-mode-manual"]').setValue(true);

        expect(putMock).toHaveBeenCalledWith(
            '/admin/resume',
            expect.objectContaining({
                mode: 'manual',
                layout: LAYOUT,
                custom_resume_file: null,
            }),
            expect.any(Object),
        );
        expect(putMock.mock.calls[0]?.[1]).not.toHaveProperty('about_text');
    });

    it('auto-saves the template change', async () => {
        const wrapper = mountPage();
        const dropdown = wrapper.get('[data-testid="resume-edit-page-template"]');

        // The dropdown is a button-triggered listbox — emit the change
        // through its update:modelValue wiring like the real click does.
        await dropdown.trigger('click');
        await wrapper.find('[data-testid="resume-edit-page-template-option-classic"]').trigger('click');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/resume',
            expect.objectContaining({
                mode: 'auto',
                layout: expect.objectContaining({ template: 'classic' }),
            }),
            expect.any(Object),
        );
    });

    it('auto-saves the section visibility toggle', async () => {
        const wrapper = mountPage();

        await wrapper
            .get('[data-testid="resume-edit-page-sections-visible-experience"]')
            .setValue(false);

        expect(putMock).toHaveBeenCalledWith(
            '/admin/resume',
            expect.objectContaining({
                layout: expect.objectContaining({
                    sections: expect.objectContaining({
                        experience: { visible: false, order: 3 },
                    }),
                }),
            }),
            expect.any(Object),
        );
    });

    it('auto-saves a section reorder', async () => {
        const wrapper = mountPage();

        await wrapper
            .get('[data-testid="resume-edit-page-sections-down-about_me"]')
            .trigger('click');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/resume',
            expect.objectContaining({
                layout: expect.objectContaining({
                    sections: expect.objectContaining({
                        header: { visible: true, order: 0 },
                        about_me: { visible: true, order: 2 },
                        skills: { visible: true, order: 1 },
                    }),
                }),
            }),
            expect.any(Object),
        );
    });

    it('saves the about texts with the explicit Save button', async () => {
        const wrapper = mountPage();

        await wrapper
            .get('[data-testid="resume-edit-page-about-en"]')
            .setValue('Updated about text.');
        await wrapper.get('[data-testid="resume-edit-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/resume',
            expect.objectContaining({
                mode: 'auto',
                about_text: expect.objectContaining({ en: 'Updated about text.' }),
            }),
            expect.any(Object),
        );
    });

    it('keeps an unsaved about text when an auto-save re-renders with stale props', async () => {
        const wrapper = mountPage();
        const textarea = wrapper.get('[data-testid="resume-edit-page-about-en"]');

        await textarea.setValue('Typed but not saved.');

        // The auto-save (mode switch) sends no about_text — the server
        // re-render then returns the STALE stored texts; the page must
        // not overwrite the user's local edit (no props watcher).
        putMock.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options: { onSuccess?: () => void },
            ) => {
                options.onSuccess?.();
            },
        );
        await wrapper.get('[data-testid="resume-edit-page-mode-manual"]').setValue(true);
        await wrapper.get('[data-testid="resume-edit-page-mode-auto"]').setValue(true);
        await wrapper.setProps({ about_texts: { en: 'Stored text.', ru: '' } });

        expect(
            (wrapper.get('[data-testid="resume-edit-page-about-en"]').element as HTMLTextAreaElement).value,
        ).toBe('Typed but not saved.');
    });

    it('switches to the manual block on manual mode', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="resume-edit-page-mode-manual"]').setValue(true);

        expect(wrapper.find('[data-testid="resume-edit-page-manual-block"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="resume-edit-page-auto-block"]').exists()).toBe(false);
    });

    it('uploads the PDF via POST /api/upload with the about entity type', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: { path: 'about/abc123.pdf', name: 'resume.pdf' } }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);
        document.cookie = 'XSRF-TOKEN=abc123%2Bdef';

        const wrapper = mountPage({ mode: 'manual', custom_resume_file: null });
        await wrapper.get('[data-testid="resume-edit-page-mode-manual"]').setValue(true);

        const input = wrapper.get('[data-testid="resume-edit-page-file-input"]')
            .element as HTMLInputElement;
        const file = new File(['%PDF-1.7'], 'resume.pdf', { type: 'application/pdf' });
        Object.defineProperty(input, 'files', { value: [file] });
        await input.dispatchEvent(new Event('change'));

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/upload',
            expect.objectContaining({ method: 'POST' }),
        );
        const body = fetchMock.mock.calls[0]?.[1]?.body as FormData;
        expect(body.get('entity_type')).toBe('about');
        expect(body.get('file')).toBe(file);
        expect(wrapper.get('[data-testid="resume-edit-page-file-file-name"]').text()).toBe('resume.pdf');
    });

    it('saves the uploaded file path with the full Save', async () => {
        const wrapper = mountPage({ mode: 'manual', custom_resume_file: 'about/abc123.pdf' });

        await wrapper.get('[data-testid="resume-edit-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/resume',
            expect.objectContaining({
                mode: 'manual',
                custom_resume_file: 'about/abc123.pdf',
            }),
            expect.any(Object),
        );
    });

    it('regenerates the PDF and toasts the result', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: { message: 'Resume PDF regenerated.' } }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountPage();
        await wrapper.get('[data-testid="resume-edit-page-regenerate"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/resume/regenerate',
            expect.objectContaining({ method: 'POST' }),
        );
        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'success',
            message: 'Resume PDF regenerated.',
        });
    });

    it('toasts an error when the regeneration fails', async () => {
        vi.mocked(fetch).mockResolvedValue({ ok: false, status: 500 } as Response);

        const wrapper = mountPage();
        await wrapper.get('[data-testid="resume-edit-page-regenerate"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const toastStore = useToastStore();
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'Could not regenerate the resume PDF. Please try again.',
        });
    });

    it('links the download preview to /cv?locale={active tab}', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="resume-edit-page-download-preview"]').attributes('href')).toBe(
            '/cv?locale=en',
        );
        expect(
            wrapper.get('[data-testid="resume-edit-page-download-preview"]').attributes('target'),
        ).toBe('_blank');
    });

    it('surfaces the manual-file validation error on the dropzone', async () => {
        putMock.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options: { onError?: (errors: Record<string, string>) => void },
            ) => {
                options.onError?.({ custom_resume_file: 'The custom resume file field is required.' });
            },
        );

        const wrapper = mountPage();
        await wrapper.get('[data-testid="resume-edit-page-mode-manual"]').setValue(true);
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(wrapper.get('[data-testid="resume-edit-page-manual-block"]').text()).toContain(
            'The custom resume file field is required.',
        );
    });

    it('navigates to the dashboard on cancel', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="resume-edit-page-cancel-btn"]').trigger('click');

        expect(visitMock).toHaveBeenCalledWith('/admin');
    });
});
