import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentProps } from 'vue-component-type-helpers';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import TOTPSetup from '@auth-admin/TOTPSetup.vue';

const RECOVERY_CODES = ['AAAA-BBBB-CCCC', 'DDDD-EEEE-FFFF'];

function mountSetup(props: ComponentProps<typeof TOTPSetup> = { totp_enabled: false }) {
    return mount(TOTPSetup, {
        props,
        // The codes modal and disable dialog teleport to <body>; stub
        // Teleport so their content stays in-tree for assertions.
        global: { stubs: { Teleport: true } },
    });
}

describe('TOTPSetup', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('shows the enable button in the disabled state', () => {
        const wrapper = mountSetup({ totp_enabled: false });

        expect(wrapper.get('[data-testid="totp-setup-status"]').text()).toBe('TOTP is disabled');
        expect(wrapper.find('[data-testid="totp-setup-enable-btn"]').exists()).toBe(true);
    });

    it('emits enable-start and renders the QR + manual key in setup state', async () => {
        const wrapper = mountSetup({ totp_enabled: false, qr_code_svg: '<svg data-testid="qr-svg" />', manual_key: 'ABC234' });

        await wrapper.get('[data-testid="totp-setup-enable-btn"]').trigger('click');

        expect(wrapper.emitted('enable-start')).toHaveLength(1);
        expect(wrapper.find('[data-testid="totp-setup-qr"] svg').exists()).toBe(true);
        expect(wrapper.get('[data-testid="totp-setup-manual-key"]').text()).toBe('ABC234');
    });

    it('emits enable with the verification code', async () => {
        const wrapper = mountSetup({ totp_enabled: false });

        await wrapper.get('[data-testid="totp-setup-enable-btn"]').trigger('click');
        await wrapper.get('[data-testid="totp-setup-code-input"]').setValue('123456');
        await wrapper.get('[data-testid="totp-setup-setup-form"]').trigger('submit');

        expect(wrapper.emitted('enable')).toEqual([[{ code: '123456' }]]);
    });

    it('shows the recovery-codes remaining count in the enabled state', () => {
        const wrapper = mountSetup({ totp_enabled: true, recovery_codes_remaining: 8 });

        expect(wrapper.get('[data-testid="totp-setup-status"]').text()).toBe('TOTP is enabled');
        expect(wrapper.get('[data-testid="totp-setup-remaining"]').text()).toBe(
            'Recovery codes: 8 remaining',
        );
    });

    it('opens the codes modal and emits regenerate-codes with the password', async () => {
        const wrapper = mountSetup({ totp_enabled: true, recovery_codes: RECOVERY_CODES, recovery_codes_remaining: 10 });

        await wrapper.get('[data-testid="totp-setup-view-codes-btn"]').trigger('click');

        expect(wrapper.find('[data-testid="totp-setup-codes-list"]').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid^="totp-setup-code-"]')).toHaveLength(2);

        await wrapper.get('[data-testid="totp-setup-regenerate-password"]').setValue('current-password');
        await wrapper.get('[data-testid="totp-setup-regenerate-btn"]').trigger('click');

        expect(wrapper.emitted('regenerate-codes')).toEqual([
            [{ password: 'current-password' }],
        ]);
    });

    it('opens the codes modal automatically when fresh codes arrive', async () => {
        const wrapper = mountSetup({ totp_enabled: true, recovery_codes: [], recovery_codes_remaining: 0 });

        await wrapper.setProps({ recovery_codes: RECOVERY_CODES });

        expect(wrapper.find('[data-testid="totp-setup-codes-modal"]').exists()).toBe(true);
    });

    it('falls back to the disabled state when totp_enabled flips to false after a disable', async () => {
        const wrapper = mountSetup({ totp_enabled: false });

        // Simulate an in-progress enrollment (setup step armed).
        await wrapper.get('[data-testid="totp-setup-enable-btn"]').trigger('click');
        expect(wrapper.find('[data-testid="totp-setup-setup-form"]').exists()).toBe(true);

        // The page flips the prop after a successful disable.
        await wrapper.setProps({ totp_enabled: true });
        await wrapper.setProps({ totp_enabled: false });

        expect(wrapper.find('[data-testid="totp-setup-setup-form"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="totp-setup-enable-btn"]').exists()).toBe(true);
    });

    it('emits disable with the password from the confirmation dialog', async () => {
        const wrapper = mountSetup({ totp_enabled: true });

        await wrapper.get('[data-testid="totp-setup-disable-btn"]').trigger('click');

        await wrapper.get('[data-testid="totp-setup-disable-dialog"] [data-testid="totp-setup-disable-dialog-password"]').setValue('my-password');
        await wrapper.get('[data-testid="totp-setup-disable-dialog"] [data-testid="totp-setup-disable-dialog-confirm"]').trigger('click');

        expect(wrapper.emitted('disable')).toEqual([[{ password: 'my-password' }]]);
    });
});
