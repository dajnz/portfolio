import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { putMock, visitMock, pageProps } = vi.hoisted(() => ({
    putMock: vi.fn(),
    visitMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
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
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import AboutEditPage from '@portfolio/Admin/Pages/AboutEditPage.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

const ABOUT = {
    name: { en: 'Alex Kovalev', ru: 'Алекс Ковалёв' },
    title: { en: 'Full-Stack Developer', ru: 'Full-Stack разработчик' },
    short_pitch: { en: 'I build fast web apps.', ru: 'Я создаю быстрые веб-приложения.' },
    location: { en: 'Berlin, Germany', ru: 'Берлин, Германия' },
    photo: 'about/photo.jpg',
    photo_alt: { en: 'Portrait of Alex', ru: 'Портрет Алекса' },
    show_location: true,
    timezone: 'Europe/Berlin',
    availability_status: 'available',
    work_type: 'remote',
    relocation_readiness: true,
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(AboutEditPage, {
        props: { about: ABOUT, ...props },
    });
}

describe('AboutEditPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        putMock.mockClear();
        visitMock.mockClear();
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    it('renders within the admin layout with the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="about-edit-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="about-edit-page"] h1').text()).toBe('About Me');
    });

    it('prefills the locale-tabbed fields and swaps values on tab switch', async () => {
        const wrapper = mountPage();

        const enName = wrapper.get('[data-testid="about-edit-page-name-en"]');
        expect((enName.element as HTMLInputElement).value).toBe('Alex Kovalev');

        await wrapper.get('[data-testid="about-edit-page-locale-tabs-tab-ru"]').trigger('click');

        const ruName = wrapper.get('[data-testid="about-edit-page-name-ru"]');
        expect((ruName.element as HTMLInputElement).value).toBe('Алекс Ковалёв');
        expect(
            (
                wrapper.get('[data-testid="about-edit-page-short-pitch-ru"]').element as HTMLTextAreaElement
            ).value,
        ).toBe('Я создаю быстрые веб-приложения.');
    });

    it('prefills the shared fields outside the tabs', () => {
        const wrapper = mountPage();

        expect((wrapper.get('[data-testid="about-edit-page-timezone"]').element as HTMLInputElement).value).toBe(
            'Europe/Berlin',
        );
        expect(wrapper.find('[data-testid="about-edit-page-photo-preview"]').exists()).toBe(true);
        expect(
            (wrapper.get('[data-testid="about-edit-page-photo-alt-en"]').element as HTMLInputElement).value,
        ).toBe('Portrait of Alex');
    });

    it('saves the full form with PUT /admin/about', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="about-edit-page-name-en"]').setValue('New Name');
        await wrapper.get('[data-testid="about-edit-page-locale-tabs-tab-ru"]').trigger('click');
        await wrapper.get('[data-testid="about-edit-page-title-ru"]').setValue('Новый заголовок');
        await wrapper.get('[data-testid="about-edit-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/about',
            expect.objectContaining({
                name: { en: 'New Name', ru: 'Алекс Ковалёв' },
                title: { en: 'Full-Stack Developer', ru: 'Новый заголовок' },
                short_pitch: { en: 'I build fast web apps.', ru: 'Я создаю быстрые веб-приложения.' },
                photo: 'about/photo.jpg',
                show_location: true,
                timezone: 'Europe/Berlin',
                work_type: 'remote',
                relocation_readiness: true,
                availability_status: 'available',
            }),
            expect.any(Object),
        );
    });

    it('uploads the photo via POST /api/upload with the about entity type', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: { path: 'about/abc123.jpg', name: 'abc123.jpg' } }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);
        document.cookie = 'XSRF-TOKEN=abc123%2Bdef';

        const wrapper = mountPage();
        const input = wrapper.get('[data-testid="about-edit-page-photo-input"]')
            .element as HTMLInputElement;
        const file = new File(['image'], 'portrait.jpg', { type: 'image/jpeg' });
        Object.defineProperty(input, 'files', { value: [file] });
        await input.dispatchEvent(new Event('change'));

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/upload',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'abc123+def' }),
            }),
        );
        const body = fetchMock.mock.calls[0]?.[1]?.body as FormData;
        expect(body.get('entity_type')).toBe('about');
        expect(body.get('file')).toBe(file);
    });

    it('links to the resume settings page', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="about-edit-page-configure-resume"]').attributes('href')).toBe(
            '/admin/resume',
        );
    });

    it('renders the resume mode label from the resume_config mode', () => {
        const wrapper = mountPage({ resume_mode: 'auto' });

        expect(wrapper.get('[data-testid="about-edit-page-resume-mode"]').text()).toBe('Auto-generated');
    });

    it('defaults the resume mode label to manual when the prop is absent', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="about-edit-page-resume-mode"]').text()).toBe('Custom upload');
    });

    it('navigates to the dashboard on cancel', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="about-edit-page-cancel-btn"]').trigger('click');

        expect(visitMock).toHaveBeenCalledWith('/admin');
    });

    it('keeps the tabs and the save/cancel buttons in the sticky action bar', () => {
        const wrapper = mountPage();

        const bar = wrapper.get('[data-testid="about-edit-page-action-bar"]');
        expect(bar.classes()).toContain('sticky');
        expect(bar.classes()).toContain('bg-slate-50');
        expect(bar.find('[data-testid="about-edit-page-locale-tabs"]').exists()).toBe(true);
        expect(bar.find('[data-testid="about-edit-page-save-btn"]').exists()).toBe(true);
        expect(bar.find('[data-testid="about-edit-page-cancel-btn"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="about-edit-page-form"] [data-testid="about-edit-page-save-btn"]').exists()).toBe(true);
    });

    it('warns the tab of a locale with an incomplete required translation', async () => {
        const wrapper = mountPage({ about: { ...ABOUT, name: { en: '', ru: 'Алекс' } } });

        expect(wrapper.find('[data-testid="about-edit-page-locale-tabs-warn-en"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="about-edit-page-locale-tabs-warn-ru"]').exists()).toBe(false);

        await wrapper.get('[data-testid="about-edit-page-name-en"]').setValue('Alex');
        expect(wrapper.find('[data-testid="about-edit-page-locale-tabs-warn-en"]').exists()).toBe(false);
    });

    it('shows a translation-incomplete toast when the save fails on a missing locale', async () => {
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
        await wrapper.get('[data-testid="about-edit-page-form"]').trigger('submit');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'The Russian translation is incomplete.',
        });
    });
});
