import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import PasswordChangeForm from '@auth-admin/PasswordChangeForm.vue';

describe('PasswordChangeForm', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('emits the password fields on submit', async () => {
        const wrapper = mount(PasswordChangeForm);

        await wrapper.get('[data-testid="password-change-form-current-input"]').setValue('old-password');
        await wrapper.get('[data-testid="password-change-form-new-input"]').setValue('new-password-123');
        await wrapper.get('[data-testid="password-change-form-confirm-input"]').setValue('new-password-123');
        await wrapper.get('[data-testid="password-change-form-form"]').trigger('submit');

        expect(wrapper.emitted('submit')).toEqual([
            [
                {
                    current_password: 'old-password',
                    password: 'new-password-123',
                    password_confirmation: 'new-password-123',
                },
            ],
        ]);
    });

    it('shows the oauth notice instead of the form when is_oauth_primary is set', () => {
        const wrapper = mount(PasswordChangeForm, {
            props: { is_oauth_primary: true },
        });

        expect(wrapper.find('[data-testid="password-change-form-notice"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="password-change-form-form"]').exists()).toBe(false);
    });

    it('renders per-field validation errors on the inputs', () => {
        const wrapper = mount(PasswordChangeForm, {
            props: {
                errors: {
                    current_password: 'The current password is incorrect.',
                    password: 'The password field must be at least 12 characters.',
                    password_confirmation: 'The password confirmation does not match.',
                },
            },
        });

        expect(wrapper.get('[data-testid="password-change-form-current-input"]').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('[data-testid="password-change-form-current-input"] + p').text()).toBe(
            'The current password is incorrect.',
        );
        expect(wrapper.get('[data-testid="password-change-form-new-input"]').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('[data-testid="password-change-form-new-input"] + p').text()).toBe(
            'The password field must be at least 12 characters.',
        );
        expect(wrapper.get('[data-testid="password-change-form-confirm-input"]').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('[data-testid="password-change-form-confirm-input"] + p').text()).toBe(
            'The password confirmation does not match.',
        );
    });

    it('disables the fields while submitting', async () => {
        const wrapper = mount(PasswordChangeForm, {
            props: { is_submitting: true },
        });

        const inputs = wrapper.findAll('[data-testid$="-input"]');

        for (const input of inputs) {
            expect(input.attributes('disabled')).toBeDefined();
        }
    });
});
