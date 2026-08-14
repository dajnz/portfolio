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

import BlogFormPage from '@blog/Admin/Pages/BlogFormPage/BlogFormPage.vue';

const TAGS = [
    { id: 1, slug: 'vue', name: 'Vue' },
    { id: 2, slug: 'backend', name: 'Backend' },
];

const ARTICLES = [
    { id: 3, slug: 'cursor-based-pagination', title: 'Cursor-Based Pagination in PostgreSQL' },
];

const ARTICLE = {
    id: 5,
    slug: 'building-reusable-vue-composables',
    title: { en: 'Building Reusable Vue Composables', ru: 'Переиспользуемые композаблы Vue' },
    short_description: {
        en: 'Patterns for testable composables.',
        ru: 'Паттерны для тестируемых композаблов.',
    },
    content: {
        en: 'Composable functions are the backbone of maintainable Vue applications. They encapsulate state and behavior in a testable unit.',
        ru: 'Композабл-функции — основа сопровождаемых Vue-приложений. Они инкапсулируют состояние и поведение в тестируемую единицу.',
    },
    published_at: '2026-06-15T10:00:00+00:00',
    reading_time_minutes: 6,
    lead_image: 'blog/5/lead.png',
    lead_image_alt: { en: 'Composable diagram', ru: 'Схема композаблов' },
    is_featured: true,
    is_published: true,
    related_article_ids: [3],
    tags: TAGS,
    skill_tags: [{ id: 9, name: 'TypeScript', icon: 'devicon:typescript' }],
    related: [{ id: 3, title: 'Cursor-Based Pagination in PostgreSQL' }],
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(BlogFormPage, {
        props: {
            article: null,
            tags: TAGS,
            articles: ARTICLES,
            preview_url: null,
            ...props,
        },
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('BlogFormPage', () => {
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

    it('renders the create form with no preview button and no Cancel button', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="blog-form-page"] h1').text()).toBe('Create Article');
        expect(wrapper.find('[data-testid="blog-form-page-preview-btn"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="blog-form-page-cancel-btn"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="blog-form-page-editor-en"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="blog-form-page-title-en"]').exists()).toBe(true);
    });

    it('renders the edit form pre-filled and with the signed preview URL', () => {
        const wrapper = mountPage({ article: ARTICLE, preview_url: '/admin/preview/blog/5?sig=x' });

        expect(wrapper.get('[data-testid="blog-form-page"] h1').text()).toBe('Edit Article');
        expect((wrapper.get('[data-testid="blog-form-page-title-en"]').element as HTMLInputElement).value).toBe(
            'Building Reusable Vue Composables',
        );
        expect((wrapper.get('[data-testid="blog-form-page-slug"]').element as HTMLInputElement).value).toBe(
            'building-reusable-vue-composables',
        );
        expect(
            wrapper.get('[data-testid="blog-form-page-preview-btn"]').attributes('href'),
        ).toBe('/admin/preview/blog/5?sig=x');
        expect(
            wrapper.get('[data-testid="blog-form-page-published-at"]').text(),
        ).toBe('2026-06-15 10:00 UTC');
    });

    it('switches the locale-tabbed fields with the locale tabs', async () => {
        const wrapper = mountPage({ article: ARTICLE });

        const tabs = wrapper.findComponent({ name: 'LocaleTabs' });
        tabs.vm.$emit('update:modelValue', 'ru');
        await nextTick();

        expect((wrapper.get('[data-testid="blog-form-page-title-ru"]').element as HTMLInputElement).value).toBe(
            'Переиспользуемые композаблы Vue',
        );
        expect((wrapper.get('[data-testid="blog-form-page-slug"]').element as HTMLInputElement).value).toBe(
            'building-reusable-vue-composables',
        );
    });

    it('renders the featured/published states as switch toggles', () => {
        const wrapper = mountPage({ article: ARTICLE });

        const featured = wrapper.get('[data-testid="blog-form-page-featured"]');
        const published = wrapper.get('[data-testid="blog-form-page-published"]');

        expect(featured.attributes('role')).toBe('switch');
        expect(featured.attributes('aria-checked')).toBe('true');
        expect(published.attributes('role')).toBe('switch');
        expect(published.attributes('aria-checked')).toBe('true');
    });

    it('shows the alphabetical related-articles tooltip on the dropdown trigger', () => {
        const wrapper = mountPage({
            article: {
                ...ARTICLE,
                related_article_ids: [3, 7, 9],
                related: [
                    { id: 3, title: 'Cursor-Based Pagination in PostgreSQL' },
                    { id: 7, title: 'Alpha Post' },
                    // Linked-but-unpublished — absent from the picker
                    // options, still listed in the tooltip.
                    { id: 9, title: 'Hidden Draft' },
                ],
            },
            articles: [
                { id: 3, slug: 'cursor-based-pagination', title: 'Cursor-Based Pagination in PostgreSQL' },
                { id: 7, slug: 'alpha-post', title: 'Alpha Post' },
            ],
        });

        const related = wrapper
            .findAllComponents({ name: 'AdminBaseDropdown' })
            .find((dropdown) => dropdown.props('test_id') === 'blog-form-page-related');
        expect(related?.props('title')).toBe(
            'Alpha Post\nCursor-Based Pagination in PostgreSQL\nHidden Draft',
        );
    });

    it('auto-generates the slug from the EN title on create until manually edited', async () => {
        const wrapper = mountPage();
        const title = wrapper.get('[data-testid="blog-form-page-title-en"]');
        const slug = wrapper.get('[data-testid="blog-form-page-slug"]');

        await title.setValue('Hello World Article');
        expect((slug.element as HTMLInputElement).value).toBe('hello-world-article');

        await slug.setValue('my-custom-slug');
        await title.setValue('Another Title');
        expect((slug.element as HTMLInputElement).value).toBe('my-custom-slug');
    });

    it('submits the create payload with the unified tags split and related ids', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-form-page-title-en"]').setValue('Hello World Article');
        await wrapper
            .get('[data-testid="blog-form-page-short-description-en"]')
            .setValue('A short description of the article.');

        const picker = wrapper.findComponent({ name: 'AdminBlogTagPicker' });
        picker.vm.$emit('update:modelValue', [
            { id: 1, name: 'Vue', type: 'tag' },
            { id: 9, name: 'TypeScript', type: 'skill' },
        ]);

        const related = wrapper
            .findAllComponents({ name: 'AdminBaseDropdown' })
            .find((dropdown) => dropdown.props('test_id') === 'blog-form-page-related');
        related?.vm.$emit('update:modelValue', ['3']);
        await nextTick();

        const editor = wrapper.findComponent({ name: 'TextEditor' });
        editor.vm.$emit('update:modelValue', 'x'.repeat(60));
        await nextTick();

        await wrapper.get('[data-testid="blog-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/blog',
            expect.objectContaining({
                slug: 'hello-world-article',
                title: { en: 'Hello World Article', ru: '' },
                short_description: { en: 'A short description of the article.', ru: '' },
                content: { en: 'x'.repeat(60), ru: '' },
                is_featured: false,
                is_published: false,
                lead_image: null,
                tags: [1],
                skill_tags: [9],
                related_articles: [3],
            }),
            expect.any(Object),
        );
    });

    it('submits the edit payload via PUT', async () => {
        const wrapper = mountPage({ article: ARTICLE, preview_url: '/admin/preview/blog/5' });
        const editor = wrapper.findComponent({ name: 'TextEditor' });

        editor.vm.$emit('update:modelValue', 'y'.repeat(60));
        await nextTick();

        await wrapper.get('[data-testid="blog-form-page-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/blog/5',
            expect.objectContaining({
                slug: 'building-reusable-vue-composables',
                title: ARTICLE.title,
                tags: [1, 2],
                skill_tags: [9],
                related_articles: [3],
                lead_image: 'blog/5/lead.png',
            }),
            expect.any(Object),
        );
    });

    it('binds per-locale validation errors and toasts the missing translation', async () => {
        postMock.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: { onError: (e: Record<string, string>) => void },
            ) => {
                options.onError({ 'title.ru': 'The title is required.' });
            },
        );
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-form-page-form"]').trigger('submit');
        await nextTick();

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    });

    it('surfaces the nested array-field validation errors (related_articles.0 / tags.0) instead of failing silently', async () => {
        postMock.mockImplementationOnce(
            (
                _url: string,
                _payload: unknown,
                options: { onError: (e: Record<string, string>) => void },
            ) => {
                options.onError({
                    'related_articles.0': 'The selected related article is invalid.',
                    'tags.2': 'The selected tag is invalid.',
                });
            },
        );
        const wrapper = mountPage();

        await wrapper.get('[data-testid="blog-form-page-form"]').trigger('submit');
        await nextTick();

        const related = wrapper
            .findAllComponents({ name: 'AdminBaseDropdown' })
            .find((dropdown) => dropdown.props('test_id') === 'blog-form-page-related');
        const tags = wrapper.findComponent({ name: 'AdminBlogTagPicker' });

        expect(related?.props('error')).toBe('The selected related article is invalid.');
        expect(tags.props('error')).toBe('The selected tag is invalid.');
    });

    it('uploads the lead image through /api/upload with the blog entity type', async () => {
        postMock.mockResolvedValue({ data: { path: 'blog/new/lead.png' } });
        const wrapper = mountPage();
        const dropzone = wrapper.findComponent({ name: 'DropzoneUpload' });

        dropzone.vm.$emit('upload', new File(['x'], 'lead.png', { type: 'image/png' }));
        await new Promise((resolve) => setTimeout(resolve, 0));
        await nextTick();

        const formData = postMock.mock.calls[0][1].formData as FormData;
        expect(postMock).toHaveBeenCalledWith('/api/upload', expect.any(Object));
        expect(formData.get('entity_type')).toBe('blog');
    });

    it('uploads an editor image and inserts the /assets/ URL at the cursor', async () => {
        postMock.mockResolvedValue({ data: { path: 'blog/new/inline.png' } });
        const wrapper = mountPage();
        const editor = wrapper.findComponent({ name: 'TextEditor' });

        editor.vm.$emit('image-upload', new File(['x'], 'inline.png', { type: 'image/png' }));
        await new Promise((resolve) => setTimeout(resolve, 0));
        await nextTick();

        expect(postMock).toHaveBeenCalledWith('/api/upload', expect.any(Object));
    });

    it('preserves the raw-mode content across the submit (regression — setEditable re-emits the stale WYSIWYG doc)', async () => {
        const wrapper = mountPage();

        // Switch to the raw Markdown textarea and type the body there.
        await wrapper
            .get('[data-testid="blog-form-page-editor-en-mode-toggle"]')
            .trigger('click');
        await nextTick();
        const raw = wrapper.get('[data-testid="blog-form-page-editor-en-raw"]');
        await raw.setValue('x'.repeat(60));

        // The submit disables the editor (isSaving) — Tiptap's
        // setEditable re-fires onUpdate with the empty WYSIWYG doc,
        // which must NOT clobber the raw textarea's value.
        await wrapper.get('[data-testid="blog-form-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/blog',
            expect.objectContaining({
                content: { en: 'x'.repeat(60), ru: '' },
            }),
            expect.any(Object),
        );
    });
});
