import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { getMock, postMock, putMock, deleteMock, pageProps } = vi.hoisted(() => ({
    getMock: vi.fn(),
    postMock: vi.fn(),
    putMock: vi.fn(),
    deleteMock: vi.fn(),
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
    router: { get: getMock, post: postMock, put: putMock, delete: deleteMock },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import ContactsListPage from '@portfolio/Admin/Pages/ContactsListPage.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

const CONTACTS = [
    {
        id: 1,
        name: 'GitHub',
        names: { en: 'GitHub', ru: 'Гитхаб' },
        value: 'github.com/alexkovalev',
        icon: 'simple-icons:github',
        url: 'https://github.com/alexkovalev',
        include_in_resume: true,
        sort_order: 0,
    },
    {
        id: 2,
        name: 'Email',
        names: { en: 'Email', ru: 'Почта' },
        value: 'hello@alexkovalev.dev',
        icon: 'simple-icons:gmail',
        url: null,
        include_in_resume: false,
        sort_order: 1,
    },
];

const META = {
    total: 2,
    per_page: 12,
    current_page: 1,
    sort_column: 'sort_order',
    sort_direction: 'asc' as const,
    cursors: {
        first: { id: 2, sort_value: null },
        last: { id: 1, sort_value: null },
        page: 1,
    },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(ContactsListPage, {
        props: { contacts: CONTACTS, meta: META, ...props },
        // The ConfirmationDialog teleports to body — stub Teleport so the
        // dialog markup renders inline for assertions.
        global: { stubs: { Teleport: true } },
    });
}

describe('ContactsListPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        getMock.mockClear();
        postMock.mockClear();
        putMock.mockClear();
        deleteMock.mockClear();
        window.localStorage.clear();
    });

    it('renders the contact rows with icon, name and value', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="contacts-list-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="contacts-list-page-row-1"]').text()).toContain('GitHub');
        expect(wrapper.get('[data-testid="contacts-list-page-row-1"]').text()).toContain(
            'github.com/alexkovalev',
        );
        expect(wrapper.get('[data-testid="contacts-list-page-row-2"]').text()).toContain('Email');
    });

    it('toggles include-in-resume with the per-locale names map (PUT)', async () => {
        const wrapper = mountPage();

        const checkbox = wrapper.get('[data-testid="contacts-list-page-resume-2"]');
        await checkbox.setValue(true);

        expect(putMock).toHaveBeenCalledWith(
            '/admin/contacts/2',
            expect.objectContaining({
                include_in_resume: true,
                name: { en: 'Email', ru: 'Почта' },
                sort_order: 1,
            }),
            expect.any(Object),
        );
    });

    it('opens the inline editor prefilled with the per-locale names on a row click', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-row-1"]').trigger('click');

        expect(
            (wrapper.get('[data-testid="contacts-list-page-name-1-en"]').element as HTMLInputElement).value,
        ).toBe('GitHub');
        expect(
            (wrapper.get('[data-testid="contacts-list-page-value-1"]').element as HTMLInputElement).value,
        ).toBe('github.com/alexkovalev');
    });

    it('saves an edited contact with PUT /admin/contacts/{id}', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-row-1"]').trigger('click');
        await wrapper.get('[data-testid="contacts-list-page-name-1-en"]').setValue('GitLab');
        await wrapper.get('[data-testid="contacts-list-page-save-1"]').trigger('click');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/contacts/1',
            expect.objectContaining({
                value: 'github.com/alexkovalev',
                icon: 'simple-icons:github',
                name: { en: 'GitLab', ru: 'Гитхаб' },
                include_in_resume: true,
            }),
            expect.any(Object),
        );
    });

    it('does not start editing when clicking the delete button or the drag knob', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-delete-2"]').trigger('click');
        expect(wrapper.find('[data-testid="contacts-list-page-name-2-en"]').exists()).toBe(false);

        await wrapper.get('[data-testid="contacts-list-page-delete-dialog-abort"]').trigger('click');
        const knob = wrapper.get('[data-testid="contacts-list-page-row-2"] .contact-row-handle');
        await knob.trigger('click');
        expect(wrapper.find('[data-testid="contacts-list-page-name-2-en"]').exists()).toBe(false);
    });

    it('closes the inline editor on cancel without reopening it', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-row-1"]').trigger('click');
        expect(wrapper.find('[data-testid="contacts-list-page-name-1-en"]').exists()).toBe(true);

        await wrapper.get('[data-testid="contacts-list-page-cancel-1"]').trigger('click');

        expect(wrapper.find('[data-testid="contacts-list-page-name-1-en"]').exists()).toBe(false);
    });

    it('switches the editor to another row on a row click', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-row-1"]').trigger('click');
        expect(wrapper.find('[data-testid="contacts-list-page-name-1-en"]').exists()).toBe(true);

        await wrapper.get('[data-testid="contacts-list-page-row-2"]').trigger('click');

        expect(wrapper.find('[data-testid="contacts-list-page-name-1-en"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="contacts-list-page-name-2-en"]').exists()).toBe(true);
    });

    it('creates a new contact at the top with POST /admin/contacts', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-add-btn"]').trigger('click');
        await wrapper.get('[data-testid="contacts-list-page-name-new-en"]').setValue('Telegram');
        await wrapper.get('[data-testid="contacts-list-page-value-new"]').setValue('@alexkovalev');
        await wrapper.get('[data-testid="contacts-list-page-save-new"]').trigger('click');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/contacts',
            expect.objectContaining({
                value: '@alexkovalev',
                name: { en: 'Telegram', ru: '' },
                include_in_resume: false,
            }),
            expect.any(Object),
        );
    });

    it('deletes a contact after confirmation', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-delete-2"]').trigger('click');
        expect(wrapper.find('[data-testid="contacts-list-page-delete-dialog"]').exists()).toBe(true);

        await wrapper.get('[data-testid="contacts-list-page-delete-dialog-confirm"]').trigger('click');

        expect(deleteMock).toHaveBeenCalledWith('/admin/contacts/2', expect.any(Object));
    });

    it('posts the reorder payload with the dragged order (A4)', async () => {
        const wrapper = mountPage();

        const sortable = wrapper.findComponent({ name: 'SortableList' });
        const reordered = [CONTACTS[1], CONTACTS[0]];
        sortable.vm.$emit('drag-end', reordered);

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(postMock).toHaveBeenCalledWith(
            '/admin/contacts/reorder',
            { ids: [2, 1] },
            expect.any(Object),
        );
    });

    it('requests the sort_order ascending view when the server served the default', () => {
        mountPage({
            meta: { ...META, sort_column: 'id', sort_direction: 'desc' },
        });

        expect(getMock).toHaveBeenCalledWith(
            '/admin/contacts',
            { sort_column: 'sort_order', sort_direction: 'asc' },
            expect.any(Object),
        );
    });

    it('does not re-request when already sorted by sort_order', () => {
        mountPage();

        expect(getMock).not.toHaveBeenCalled();
    });

    it('navigates to the previous page with the before cursor flag (B72)', async () => {
        const wrapper = mountPage();

        const pagination = wrapper.findComponent({ name: 'PaginationControls' });
        pagination.vm.$emit('navigate', { cursor: { id: 2, sort_value: null } });

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(getMock).toHaveBeenCalledWith(
            '/admin/contacts',
            {
                sort_column: 'sort_order',
                sort_direction: 'asc',
                'cursor[id]': 2,
                'cursor[before]': 1,
            },
            expect.any(Object),
        );
    });

    it('navigates to the next page without the before flag', async () => {
        const wrapper = mountPage();

        const pagination = wrapper.findComponent({ name: 'PaginationControls' });
        pagination.vm.$emit('navigate', { cursor: { id: 1, sort_value: null } });

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(getMock).toHaveBeenCalledWith(
            '/admin/contacts',
            {
                sort_column: 'sort_order',
                sort_direction: 'asc',
                'cursor[id]': 1,
            },
            expect.any(Object),
        );
    });

    it('offers locale tabs on the new-row editor and warns empty locales', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="contacts-list-page-add-btn"]').trigger('click');

        expect(wrapper.find('[data-testid="contacts-list-page-name-tabs-new"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="contacts-list-page-name-tabs-new-warn-en"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="contacts-list-page-name-tabs-new-warn-ru"]').exists()).toBe(true);

        await wrapper.get('[data-testid="contacts-list-page-name-new-en"]').setValue('Telegram');

        expect(wrapper.find('[data-testid="contacts-list-page-name-tabs-new-warn-en"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="contacts-list-page-name-tabs-new-warn-ru"]').exists()).toBe(true);
    });

    it('shows a translation-incomplete toast when the save fails on a missing locale', async () => {
        postMock.mockImplementationOnce(
            (
                _url: string,
                _data: unknown,
                options: { onError?: (errors: Record<string, string>) => void },
            ) => {
                options.onError?.({ 'name.ru': 'The name.ru field is required.' });
            },
        );

        const wrapper = mountPage();
        await wrapper.get('[data-testid="contacts-list-page-add-btn"]').trigger('click');
        await wrapper.get('[data-testid="contacts-list-page-name-new-en"]').setValue('Telegram');
        await wrapper.get('[data-testid="contacts-list-page-save-new"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        const toastStore = useToastStore();
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'The Russian translation is incomplete.',
        });
    });
});
