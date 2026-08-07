import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { postMock, pageUrl } = vi.hoisted(() => ({
    postMock: vi.fn(),
    pageUrl: '/admin/login/totp',
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: postMock },
    usePage: () => ({ url: pageUrl, props: undefined }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import TotpVerificationPage from '@auth/Admin/Pages/TotpVerificationPage.vue';

describe('TotpVerificationPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        postMock.mockClear();
    });

    it('renders the code input and verify button', () => {
        const wrapper = mount(TotpVerificationPage, {
            props: { lock_remaining_seconds: null },
        });

        expect(wrapper.get('[data-testid="totp-page-title"]').text()).toBe(
            'Two-Factor Authentication',
        );
        expect(wrapper.find('[data-testid="totp-page-code-input"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="totp-page-verify-btn"]').text()).toBe('Verify');
    });

    it('posts the code to the current page URL', async () => {
        const wrapper = mount(TotpVerificationPage, {
            props: { lock_remaining_seconds: null },
        });

        await wrapper.get('[data-testid="totp-page-code-input"]').setValue('123456');
        await wrapper.get('[data-testid="totp-page-form"]').trigger('submit');

        expect(postMock).toHaveBeenCalledWith(
            '/admin/login/totp',
            { code: '123456' },
            expect.any(Object),
        );
    });

    it('shows the inline error when the code is rejected', async () => {
        postMock.mockImplementationOnce(
            (_url: string, _data: unknown, options: { onError?: (errors: Record<string, string>) => void }) => {
                options.onError?.({ code: 'The code you entered is invalid.' });
            },
        );

        const wrapper = mount(TotpVerificationPage, {
            props: { lock_remaining_seconds: null },
        });

        await wrapper.get('[data-testid="totp-page-form"]').trigger('submit');

        expect(wrapper.get('[data-testid="totp-page-error"]').text()).toBe(
            'The code you entered is invalid.',
        );
    });

    it('toggles between TOTP and recovery-code mode', async () => {
        const wrapper = mount(TotpVerificationPage, {
            props: { lock_remaining_seconds: null },
        });

        expect(wrapper.get('[data-testid="totp-page-toggle-btn"]').text()).toBe(
            'Use Recovery Code',
        );
        expect(wrapper.get('[data-testid="totp-page-hint"]').text()).toContain(
            'authenticator app',
        );

        await wrapper.get('[data-testid="totp-page-toggle-btn"]').trigger('click');

        expect(wrapper.get('[data-testid="totp-page-toggle-btn"]').text()).toBe(
            'Use TOTP Code',
        );
        expect(wrapper.get('[data-testid="totp-page-hint"]').text()).toBe(
            'Enter a recovery code from your backup list.',
        );
    });
});
