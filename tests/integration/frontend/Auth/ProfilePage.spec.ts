import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { postMock, putMock, pageProps } = vi.hoisted(() => ({
    postMock: vi.fn(),
    putMock: vi.fn(),
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
    router: { post: postMock, put: putMock },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ProfilePage from '@auth/Admin/Pages/ProfilePage.vue';

const ADMIN_PROPS = {
    login: 'admin',
    email: 'admin@example.com',
    github_linked: false,
    totp_enabled: false,
    recovery_codes_remaining: 0,
    session_version: 0,
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(ProfilePage, {
        props: { admin: ADMIN_PROPS, github_oauth_enabled: false, ...props },
    });
}

describe('ProfilePage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
        putMock.mockClear();
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    it('renders within the admin layout with the profile sections', () => {
        const wrapper = mountPage({ github_oauth_enabled: true });

        expect(wrapper.find('[data-testid="profile-page-layout"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-page-info-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-page-password-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-page-social"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-page-totp"]').exists()).toBe(true);
    });

    it('hides the GitHub linking section when the feature flag is off', () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-testid="profile-page-social"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-page-totp"]').exists()).toBe(true);
    });

    it('prefills the profile form from the admin props', () => {
        const wrapper = mountPage();

        expect((wrapper.get('[data-testid="profile-page-name-input"]').element as HTMLInputElement).value).toBe('Admin');
        expect((wrapper.get('[data-testid="profile-page-email-input"]').element as HTMLInputElement).value).toBe('admin@example.com');
        expect((wrapper.get('[data-testid="profile-page-login-input"]').element as HTMLInputElement).value).toBe('admin');
    });

    it('saves the profile with PUT /admin/profile', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="profile-page-name-input"]').setValue('New Admin');
        await wrapper.get('[data-testid="profile-page-info-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/profile',
            { name: 'New Admin', email: 'admin@example.com', login: 'admin' },
            expect.any(Object),
        );
    });

    it('submits the password change merged with the profile fields', async () => {
        const wrapper = mountPage();

        await wrapper.get('[data-testid="profile-page-password-form-current-input"]').setValue('old-password');
        await wrapper.get('[data-testid="profile-page-password-form-new-input"]').setValue('new-password-123');
        await wrapper.get('[data-testid="profile-page-password-form-confirm-input"]').setValue('new-password-123');
        await wrapper.get('[data-testid="profile-page-password-form-form"]').trigger('submit');

        expect(putMock).toHaveBeenCalledWith(
            '/admin/profile',
            {
                name: 'Admin',
                email: 'admin@example.com',
                login: 'admin',
                current_password: 'old-password',
                password: 'new-password-123',
                password_confirmation: 'new-password-123',
            },
            expect.any(Object),
        );
    });

    it('clears the password fields after a successful password change', async () => {
        putMock.mockImplementationOnce(
            (_url: string, _data: unknown, options: { onSuccess?: () => void }) => {
                options.onSuccess?.();
            },
        );

        const wrapper = mountPage();

        await wrapper.get('[data-testid="profile-page-password-form-new-input"]').setValue('new-password-123');
        await wrapper.get('[data-testid="profile-page-password-form-form"]').trigger('submit');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(
            (wrapper.get('[data-testid="profile-page-password-form-new-input"]').element as HTMLInputElement).value,
        ).toBe('');
    });

    it('starts the TOTP enrollment with a JSON POST and renders the QR', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: { qr_code_svg: '<svg data-testid="totp-qr" />', manual_key: 'ABC234' },
            }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);
        document.cookie = 'XSRF-TOKEN=abc123%2Bdef';

        const wrapper = mountPage();
        await wrapper.get('[data-testid="profile-page-totp-enable-btn"]').trigger('click');

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/profile/totp-enable',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'abc123+def' }),
            }),
        );
        expect(wrapper.get('[data-testid="profile-page-totp-qr"] svg').element).toBeDefined();
        expect(wrapper.get('[data-testid="profile-page-totp-manual-key"]').text()).toBe('ABC234');
    });

    it('shows the unlink flow when the github account is linked', async () => {
        const wrapper = mountPage({
            github_oauth_enabled: true,
            admin: { ...ADMIN_PROPS, github_linked: true },
        });

        expect(
            wrapper.find('[data-testid="profile-page-social-account-github-unlink-btn"]').exists(),
        ).toBe(true);
    });
});
