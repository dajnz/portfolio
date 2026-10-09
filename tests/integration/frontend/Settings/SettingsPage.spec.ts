import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { putMock, reloadMock, pageProps } = vi.hoisted(() => ({
    putMock: vi.fn(),
    reloadMock: vi.fn(),
    pageProps: {
        auth: { user: { name: 'Admin', id: 1 } },
        locale: 'en',
        featureFlags: { github_oauth: false, social_reactions: false, console_greeting: false },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        put: putMock,
        reload: reloadMock,
        on: () => () => {},
    },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import SettingsPage from '@settings/Admin/Pages/SettingsPage.vue';
import LanguageSelector from '@shared-cross/Components/LanguageSelector.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';
const SETTINGS = {
    site_maintenance_mode: false,
    supported_locales: ['en', 'ru'],
    items_per_page: 12,
    projects_per_page: 10,
    articles_per_page: 9,
    experience_items_per_page: 15,
    trash_retention_days: 30,
    backup_max_count: 30,
    backup_upload_max_size: 102400,
    image_max_dimension: 2048,
    mail_api_endpoint: 'https://api.example.com/v1/mail',
    mail_api_token: '',
    mail_from_address: 'hello@example.com',
    mail_from_name: 'Portfolio',
    email_on_feedback: true,
    analytics_email_enabled: false,
    analytics_email_recipient: '',
};

function mountPage(props: Record<string, unknown> = {}) {
    const wrapper = mount(SettingsPage, {
        props: { settings: SETTINGS, ...props },
    });
    mountedWrappers.push(wrapper);

    return wrapper;
}

// The unsaved-changes guard registers window listeners per mount — every
// wrapper must be unmounted between tests or stale dirty guards would
// trip the beforeunload assertions of later tests.
let mountedWrappers: Array<ReturnType<typeof mount>> = [];

describe('SettingsPage', () => {
    let toastStore: ReturnType<typeof useToastStore>;

    beforeEach(() => {
        setActivePinia(createPinia());
        toastStore = useToastStore();
        putMock.mockClear();
        reloadMock.mockClear();
        pageProps.locale = 'en';
        pageProps.featureFlags = { github_oauth: false, social_reactions: false, console_greeting: false };
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        for (const wrapper of mountedWrappers) {
            wrapper.unmount();
        }
        mountedWrappers = [];
    });

    it('renders within the admin layout with the page title and four tabs', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="settings-page-layout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="settings-page"] h1').text()).toBe('Settings');
        expect(
            wrapper.findAll('[data-testid^="settings-page-tab-"]').map((tab) => tab.text()),
        ).toEqual(['General', 'Mail', 'Feature Flags', 'Analytics']);
    });

    it('shows the general tab by default with the prefilled grouped controls', () => {
        const wrapper = mountPage();

        expect(
            (wrapper.get('[data-testid="settings-page-items-per-page"]').element as HTMLInputElement).value,
        ).toBe('12');
        expect(
            (wrapper.get('[data-testid="settings-page-projects-per-page"]').element as HTMLInputElement).value,
        ).toBe('10');
        expect(
            (wrapper.get('[data-testid="settings-page-trash-days"]').element as HTMLInputElement).value,
        ).toBe('30');
        expect(
            (wrapper.get('[data-testid="settings-page-image-max-dimension"]').element as HTMLInputElement).value,
        ).toBe('2048');
        expect(wrapper.text()).toContain('Content settings');
        expect(
            (wrapper.get('[data-testid="settings-page-maintenance"]').element as HTMLInputElement).checked,
        ).toBe(false);
        expect(
            (wrapper.get('[data-testid="settings-page-locale-en"]').element as HTMLInputElement).checked,
        ).toBe(true);
    });

    it('applies the ADR-0011 numeric bounds via the min attributes', () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="settings-page-trash-days"]').attributes('min')).toBe('0');
        for (const testId of [
            'settings-page-items-per-page',
            'settings-page-projects-per-page',
            'settings-page-articles-per-page',
            'settings-page-experience-per-page',
            'settings-page-backup-max',
            'settings-page-backup-size',
        ]) {
            expect(wrapper.get(`[data-testid="${testId}"]`).attributes('min')).toBe('1');
        }
        expect(wrapper.get('[data-testid="settings-page-image-max-dimension"]').attributes('min')).toBe('512');
        expect(wrapper.get('[data-testid="settings-page-image-max-dimension"]').attributes('max')).toBe('4096');
    });

    it('toggles the maintenance switch and reflects the ON/OFF status', async () => {
        const wrapper = mountPage();

        expect(wrapper.get('[data-testid="settings-page-maintenance-status"]').text()).toBe('Off');

        await wrapper.get('[data-testid="settings-page-maintenance"]').setValue(true);

        expect(wrapper.get('[data-testid="settings-page-maintenance-status"]').text()).toBe('On');
    });

    it('prevents disabling the last remaining locale', async () => {
        const wrapper = mountPage({ settings: { ...SETTINGS, supported_locales: ['en'] } });

        const enCheckbox = wrapper.get('[data-testid="settings-page-locale-en"]');
        const ruCheckbox = wrapper.get('[data-testid="settings-page-locale-ru"]');

        expect(enCheckbox.attributes('disabled')).toBeDefined();
        expect(ruCheckbox.attributes('disabled')).toBeUndefined();

        await ruCheckbox.setValue(true);
        await wrapper.get('[data-testid="settings-page-locale-en"]').setValue(false);
        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/settings',
            {
                settings: expect.objectContaining({ supported_locales: ['ru'] }),
            },
            expect.any(Object),
        );
    });

    it('narrows the header language selector to the remaining locales when one is unchecked', async () => {
        const wrapper = mountPage();

        const selector = wrapper.findComponent(LanguageSelector);
        expect(selector.props('supported_locales')).toEqual([
            { code: 'en', native_name: 'English' },
            { code: 'ru', native_name: 'Russian' },
        ]);

        await wrapper.get('[data-testid="settings-page-locale-ru"]').setValue(false);

        expect(selector.props('supported_locales')).toEqual([{ code: 'en', native_name: 'English' }]);
    });

    it('switches the actual language when a save disables the active locale', async () => {
        putMock.mockImplementationOnce((_url, _data, options) => options.onSuccess?.());
        pageProps.locale = 'ru';

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-locale-ru"]').setValue(false);
        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');

        expect(document.cookie).toContain('locale=en');
        expect(reloadMock).toHaveBeenCalled();
    });

    it('keeps the language when a save disables a non-active locale', async () => {
        putMock.mockImplementationOnce((_url, _data, options) => options.onSuccess?.());

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-locale-ru"]').setValue(false);
        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');

        expect(reloadMock).not.toHaveBeenCalled();
    });

    it('builds the general tab payload and saves it via PUT /admin/settings', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="settings-page-maintenance"]').setValue(true);
        await wrapper.get('[data-testid="settings-page-items-per-page"]').setValue('24');
        await wrapper.get('[data-testid="settings-page-locale-ru"]').setValue(false);
        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/settings',
            {
                settings: {
                    site_maintenance_mode: true,
                    supported_locales: ['en'],
                    items_per_page: 24,
                    projects_per_page: 10,
                    articles_per_page: 9,
                    experience_items_per_page: 15,
                    trash_retention_days: 30,
                    backup_max_count: 30,
                    backup_upload_max_size: 102400,
                    image_max_dimension: 2048,
                },
            },
            expect.any(Object),
        );
    });

    it('shows a success toast after the general tab save', async () => {
        putMock.mockImplementationOnce((_url, _data, options) => options.onSuccess?.());

        const wrapper = mountPage();

        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');

        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'success',
            message: 'Settings saved successfully.',
        });
    });

    it('binds server validation errors to the general tab fields', async () => {
        putMock.mockImplementationOnce((_url, _data, options) =>
            options.onError?.({ 'settings.items_per_page': "Setting 'items_per_page' must be at least 1." }),
        );

        const wrapper = mountPage();

        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');

        expect(
            wrapper.get('[data-testid="settings-page-items-per-page"]').attributes('aria-invalid'),
        ).toBe('true');
        expect(wrapper.text()).toContain("Setting 'items_per_page' must be at least 1.");
    });

    it('builds the mail tab payload (API connection + notifications) and saves it', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="settings-page-tab-mail"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-api-endpoint"]').setValue('https://api.new.example/v1/mail');
        await wrapper.get('[data-testid="settings-page-api-token"]').setValue('new-token');
        await wrapper.get('[data-testid="settings-page-from-address"]').setValue('noreply@example.com');
        await wrapper.get('[data-testid="settings-page-email-on-feedback"]').setValue(false);
        await wrapper.get('[data-testid="settings-page-form-mail"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/settings',
            {
                settings: {
                    mail_api_endpoint: 'https://api.new.example/v1/mail',
                    mail_api_token: 'new-token',
                    mail_from_address: 'noreply@example.com',
                    mail_from_name: 'Portfolio',
                    email_on_feedback: false,
                },
            },
            expect.any(Object),
        );
    });

    it('probes the mail API with the current form values and toasts the result', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: { ok: true, status: 'ok' } }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);
        document.cookie = 'XSRF-TOKEN=abc123';

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-tab-mail"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-test-connection"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/settings/test-mail',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': 'abc123',
                }),
            }),
        );
        const body = fetchMock.mock.calls[0]?.[1]?.body;
        expect(JSON.parse(String(body))).toEqual({
            settings: {
                mail_api_endpoint: 'https://api.example.com/v1/mail',
                mail_api_token: '',
                mail_from_address: 'hello@example.com',
                mail_from_name: 'Portfolio',
                email_on_feedback: true,
            },
        });
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'success',
            message: 'Mail API connection successful.',
        });
    });

    it('toasts an error when the probe reports an unauthorized token', async () => {
        vi.mocked(fetch).mockResolvedValue(
            new Response(JSON.stringify({ data: { ok: false, status: 'unauthorized' } }), { status: 200 }),
        );

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-tab-mail"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-test-connection"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({
            variant: 'error',
            message: 'The mail API rejected the token.',
        });
    });

    it('builds the analytics tab payload and saves it', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="settings-page-tab-analytics"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-email-reports"]').setValue(true);
        await wrapper.get('[data-testid="settings-page-report-recipient"]').setValue('reports@example.com');
        await wrapper.get('[data-testid="settings-page-form-analytics"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/settings',
            {
                settings: {
                    analytics_email_enabled: true,
                    analytics_email_recipient: 'reports@example.com',
                },
            },
            expect.any(Object),
        );
    });

    it('renders a FeatureFlagToggle per flag from the shared featureFlags prop', async () => {
        pageProps.featureFlags = { github_oauth: true, social_reactions: false, console_greeting: false };

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-tab-flags"]').trigger('click');

        const rows = wrapper.findAll('[data-testid^="settings-page-flag-"]:not([data-testid$="-toggle"])');
        expect(rows).toHaveLength(3);
        expect(wrapper.text()).toContain('GitHub OAuth login');
        expect(wrapper.text()).toContain('Blog reactions');
        expect(wrapper.text()).toContain('Console greeting');
    });

    it('toggles a flag via PATCH /admin/settings/feature-flags with the emitted payload', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ data: { flags: { github_oauth: true } } }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);
        document.cookie = 'XSRF-TOKEN=abc123';

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-tab-flags"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-flag-github_oauth-toggle"]').setValue(true);
        await new Promise((resolve) => setTimeout(resolve, 50));

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/settings/feature-flags',
            expect.objectContaining({ method: 'PATCH' }),
        );
        const body = fetchMock.mock.calls[0]?.[1]?.body;
        expect(JSON.parse(String(body))).toEqual({ flags: { github_oauth: true } });
        expect(
            (wrapper.get('[data-testid="settings-page-flag-github_oauth-toggle"]').element as HTMLInputElement)
                .checked,
        ).toBe(true);
    });

    it('reverts a failed flag toggle and shows an error toast', async () => {
        vi.mocked(fetch).mockResolvedValue(new Response('', { status: 422 }));

        const wrapper = mountPage();
        await wrapper.get('[data-testid="settings-page-tab-flags"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-flag-github_oauth-toggle"]').setValue(true);
        await new Promise((resolve) => setTimeout(resolve, 50));

        expect(
            (wrapper.get('[data-testid="settings-page-flag-github_oauth-toggle"]').element as HTMLInputElement)
                .checked,
        ).toBe(false);
        expect(toastStore.toasts).toHaveLength(1);
        expect(toastStore.toasts[0]).toMatchObject({ variant: 'error' });
    });

    it('arms the unsaved-changes guard on edits and re-baselines after a tab save', async () => {
        putMock.mockImplementationOnce((_url, _data, options) => options.onSuccess?.());

        const wrapper = mountPage();

        const fireBeforeUnload = () => {
            const event = new Event('beforeunload');
            const spy = vi.spyOn(event, 'preventDefault');
            window.dispatchEvent(event);

            return spy;
        };

        expect(fireBeforeUnload()).not.toHaveBeenCalled();

        await wrapper.get('[data-testid="settings-page-items-per-page"]').setValue('42');
        expect(fireBeforeUnload()).toHaveBeenCalled();

        await wrapper.get('[data-testid="settings-page-form-general"]').trigger('submit');
        expect(fireBeforeUnload()).not.toHaveBeenCalled();
    });

    it('keeps the other tabs armed after saving one tab', async () => {
        putMock.mockImplementationOnce((_url, _data, options) => options.onSuccess?.());

        const wrapper = mountPage();

        const fireBeforeUnload = () => {
            const event = new Event('beforeunload');
            const spy = vi.spyOn(event, 'preventDefault');
            window.dispatchEvent(event);

            return spy;
        };

        await wrapper.get('[data-testid="settings-page-tab-mail"]').trigger('click');
        await wrapper.get('[data-testid="settings-page-api-endpoint"]').setValue('https://api.new.example/v1/mail');
        await wrapper.get('[data-testid="settings-page-form-mail"]').trigger('submit');

        expect(fireBeforeUnload()).not.toHaveBeenCalled();

        await wrapper.get('[data-testid="settings-page-tab-general"]').trigger('click');
        expect(fireBeforeUnload()).not.toHaveBeenCalled();

        await wrapper.get('[data-testid="settings-page-items-per-page"]').setValue('42');
        expect(fireBeforeUnload()).toHaveBeenCalled();
    });

    it('opens the last visited tab from localStorage', () => {
        window.localStorage.setItem('admin_settings_active_tab', 'analytics');

        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="settings-page-form-analytics"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="settings-page-form-general"]').exists()).toBe(false);
    });

    it('remembers the selected tab in localStorage', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="settings-page-tab-mail"]').trigger('click');

        expect(window.localStorage.getItem('admin_settings_active_tab')).toBe('mail');
    });

    it('falls back to the first tab when nothing or an unknown value is stored', () => {
        window.localStorage.setItem('admin_settings_active_tab', 'bogus');

        expect(mountPage().find('[data-testid="settings-page-form-general"]').exists()).toBe(true);
    });
});
