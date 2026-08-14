import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const { getMock, visitMock, postMock, putMock, patchMock, delMock, pageProps } = vi.hoisted(
    () => ({
        getMock: vi.fn(),
        visitMock: vi.fn(),
        postMock: vi.fn(),
        putMock: vi.fn(),
        patchMock: vi.fn(),
        delMock: vi.fn(),
        pageProps: {
            auth: { user: { name: 'Admin', id: 1 } },
            locale: 'en',
            supported_locales: ['en', 'ru'],
        },
    }),
);

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        get: getMock,
        visit: visitMock,
        post: postMock,
        put: putMock,
        patch: patchMock,
        delete: delMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

vi.mock('@shared-cross/Composables/useJsonApi', () => ({
    useJsonApi: () => ({
        get: getMock,
        post: postMock,
        patch: patchMock,
        del: delMock,
    }),
}));

import TagFormPage from '@blog/Admin/Pages/TagFormPage.vue';

const TAG = {
    id: 1,
    slug: 'laravel',
    name: 'Laravel',
    names: { en: 'Laravel', ru: 'Ларавел' },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(TagFormPage, {
        props: { tag: null, ...props },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('TagFormPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        visitMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        patchMock.mockClear();
        delMock.mockClear();
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the create form with the EN name and slug fields', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="tag-form-page"] h1').text()).toBe('Create Tag');
        expect(wrapper.find('[data-testid="tag-form-page-name-en"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="tag-form-page-slug"]').exists()).toBe(true);
    });

    it('renders the edit form pre-filled', () => {
        const wrapper = mountPage({ tag: TAG });

        expect(wrapper.get('[data-testid="tag-form-page"] h1').text()).toBe('Edit Tag');
        expect((wrapper.get('[data-testid="tag-form-page-name-en"]').element as HTMLInputElement).value).toBe(
            'Laravel',
        );
        expect((wrapper.get('[data-testid="tag-form-page-slug"]').element as HTMLInputElement).value).toBe('laravel');
    });

    it('switches the name field with the locale tabs', async () => {
        const wrapper = mountPage({ tag: TAG });
        const tabs = wrapper.findComponent({ name: 'LocaleTabs' });

        tabs.vm.$emit('update:modelValue', 'ru');
        await nextTick();

        expect((wrapper.get('[data-testid="tag-form-page-name-ru"]').element as HTMLInputElement).value).toBe(
            'Ларавел',
        );
    });

    it('auto-generates the slug from the EN name on create until manually edited', async () => {
        const wrapper = mountPage();
        const name = wrapper.get('[data-testid="tag-form-page-name-en"]');
        const slug = wrapper.get('[data-testid="tag-form-page-slug"]');

        await name.setValue('Hello World');
        expect((slug.element as HTMLInputElement).value).toBe('hello-world');

        await slug.setValue('custom-slug');
        await name.setValue('Another Name');
        expect((slug.element as HTMLInputElement).value).toBe('custom-slug');
    });

    it('posts the create payload with the per-locale names', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="tag-form-page-name-en"]').setValue('Vue');
        await wrapper.get('[data-testid="tag-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/tags',
            {
                slug: 'vue',
                name: { en: 'Vue', ru: '' },
            },
            expect.any(Object),
        );
    });

    it('puts the edit payload via PUT', async () => {
        const wrapper = mountPage({ tag: TAG });

        await wrapper.get('[data-testid="tag-form-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/tags/1',
            {
                slug: 'laravel',
                name: { en: 'Laravel', ru: 'Ларавел' },
            },
            expect.any(Object),
        );
    });
});
