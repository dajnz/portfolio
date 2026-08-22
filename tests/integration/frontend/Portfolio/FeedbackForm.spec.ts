import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { analyticsTrack } = vi.hoisted(() => ({
    analyticsTrack: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: [],
        template: '<div style="display:none"><slot /></div>',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        reload: vi.fn(),
        on: () => () => {},
    },
    usePage: () => ({ props: { locale: 'en' } }),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: analyticsTrack, cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import FeedbackForm from '@portfolio-public/FeedbackForm.vue';
import { useToastStore } from '@shared-cross/Stores/toastStore';

const HONEYPOT = 'ab12cd34ef56ab12cd34ef56ab12cd34ef';

const FORM_PROPS = {
    action_url: '/api/feedback',
    sender_name_label: 'Name',
    sender_email_label: 'Email',
    message_label: 'Message',
    submit_label: 'Send',
    success_title: 'Thanks, {sender_name}! Your message has been sent.',
    honeypot_name: HONEYPOT,
};

function mountForm(props: Record<string, unknown> = {}) {
    return mount(FeedbackForm, {
        props: { ...FORM_PROPS, ...props } as unknown as InstanceType<typeof FeedbackForm>['$props'],
    });
}

describe('FeedbackForm', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        analyticsTrack.mockClear();
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('disables the fields and pulses the submit button while submitting', async () => {
        let resolveFetch!: (value: { ok: boolean; status: number }) => void;
        const fetchMock = vi
            .fn()
            .mockReturnValueOnce(
                new Promise((resolve) => {
                    resolveFetch = resolve;
                }),
            );
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountForm();
        await wrapper.get('[data-testid="landing-feedback-form-name"]').setValue('Alex');
        await wrapper.get('[data-testid="landing-feedback-form-email"]').setValue('alex@example.com');
        await wrapper.get('[data-testid="landing-feedback-form-message"]').setValue('Hello there!');
        await wrapper.get('[data-testid="landing-feedback-form"]').trigger('submit');

        const button = wrapper.get('[data-testid="landing-feedback-form-submit"]');
        expect(button.classes()).toContain('is-loading');
        expect(button.attributes('aria-busy')).toBe('true');
        expect(
            (wrapper.get('[data-testid="landing-feedback-form-name"]').element as HTMLInputElement).disabled,
        ).toBe(true);
        expect(
            (wrapper.get('[data-testid="landing-feedback-form-message"]').element as HTMLTextAreaElement).disabled,
        ).toBe(true);

        resolveFetch({ ok: true, status: 201 });
        await flushPromises();
    });

    it('maps a 422 response into inline field errors and stays idle', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            status: 422,
            json: async () => ({
                message: 'The given data was invalid.',
                errors: { sender_name: ['The sender name field is required.'] },
            }),
        });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountForm();
        await wrapper.get('[data-testid="landing-feedback-form"]').trigger('submit');
        await flushPromises();

        const name = wrapper.get('[data-testid="landing-feedback-form-name"]');
        expect(name.attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('.form-group__error').text()).toBe('The sender name field is required.');
        expect(
            (wrapper.get('[data-testid="landing-feedback-form-submit"]').element as HTMLButtonElement).disabled,
        ).toBe(false);
    });

    it('shows the error toast on a network failure and returns to idle', async () => {
        const fetchMock = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountForm();
        await wrapper.get('[data-testid="landing-feedback-form-name"]').setValue('Alex');
        await wrapper.get('[data-testid="landing-feedback-form"]').trigger('submit');
        await flushPromises();

        const toasts = useToastStore().toasts;
        expect(toasts).toHaveLength(1);
        expect(toasts[0]).toMatchObject({
            variant: 'error',
            message: 'Could not send your message. Please try again.',
        });
        expect(
            (wrapper.get('[data-testid="landing-feedback-form-name"]').element as HTMLInputElement).disabled,
        ).toBe(false);
    });

    it('treats non-422 server errors (429/5xx) as a network error toast', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 500 });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountForm();
        await wrapper.get('[data-testid="landing-feedback-form"]').trigger('submit');
        await flushPromises();

        expect(useToastStore().toasts).toHaveLength(1);
        expect(wrapper.find('[data-testid="landing-feedback-form-success"]').exists()).toBe(false);
    });

    it('emits submitted with the sender identity on success', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 201 });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountForm();
        await wrapper.get('[data-testid="landing-feedback-form-name"]').setValue('Alex');
        await wrapper.get('[data-testid="landing-feedback-form-email"]').setValue('alex@example.com');
        await wrapper.get('[data-testid="landing-feedback-form-message"]').setValue('Hello there!');
        await wrapper.get('[data-testid="landing-feedback-form"]').trigger('submit');
        await flushPromises();

        expect(wrapper.emitted('submitted')).toEqual([[{ sender_name: 'Alex', sender_email: 'alex@example.com' }]]);
    });

    it('replaces the whole form with a centered success icon and the confirmation', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 201 });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountForm();
        await wrapper.get('[data-testid="landing-feedback-form-name"]').setValue('Alex');
        await wrapper.get('[data-testid="landing-feedback-form-email"]').setValue('alex@example.com');
        await wrapper.get('[data-testid="landing-feedback-form-message"]').setValue('Hello there!');
        await wrapper.get('[data-testid="landing-feedback-form"]').trigger('submit');
        await flushPromises();

        const success = wrapper.get('[data-testid="landing-feedback-form-success"]');
        expect(success.find('svg.iconify--heroicons').exists()).toBe(true);
        expect(success.text()).toBe('Thanks, Alex! Your message has been sent.');

        // No heading, no fields, no "Send another" button.
        expect(wrapper.find('.feedback-form__title').exists()).toBe(false);
        expect(wrapper.find('[data-testid="landing-feedback-form-name"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="landing-feedback-form-send-another"]').exists()).toBe(false);
    });

    it('renders the honeypot input off-screen with the randomized name and tabindex -1', () => {
        const wrapper = mountForm();

        const honeypot = wrapper.get('[data-testid="landing-feedback-form-honeypot"]');
        expect(honeypot.attributes('name')).toBe(HONEYPOT);
        expect(honeypot.attributes('tabindex')).toBe('-1');
        expect(honeypot.attributes('autocomplete')).toBe('off');
        expect(wrapper.get('.visually-hidden').attributes('aria-hidden')).toBe('true');
    });
});
