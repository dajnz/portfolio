import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentProps } from 'vue-component-type-helpers';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import SocialAccounts from '@auth-admin/SocialAccounts.vue';

function mountSocialAccounts(props: ComponentProps<typeof SocialAccounts> = { accounts: [] }) {
    return mount(SocialAccounts, {
        props,
        // The confirmation dialog teleports to <body>; stub Teleport so the
        // modal stays in-tree for assertions.
        global: { stubs: { Teleport: true } },
    });
}

const ACCOUNTS = [
    {
        provider: 'github',
        provider_name: 'GitHub',
        is_linked: true,
        linked_at: null,
    },
    {
        provider: 'github',
        provider_name: 'GitHub',
        is_linked: false,
        linked_at: null,
    },
];

describe('SocialAccounts', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the linked status and the unlink button for a linked account', () => {
        const wrapper = mount(SocialAccounts, {
            props: { accounts: [ACCOUNTS[0]] },
        });

        expect(wrapper.get('[data-testid="social-accounts-account-github-status"]').text()).toBe(
            'Linked',
        );
        expect(
            wrapper.find('[data-testid="social-accounts-account-github-unlink-btn"]').exists(),
        ).toBe(true);
    });

    it('renders the link button for an unlinked account', () => {
        const wrapper = mount(SocialAccounts, {
            props: { accounts: [ACCOUNTS[1]] },
        });

        expect(wrapper.get('[data-testid="social-accounts-account-github-status"]').text()).toBe(
            'Not linked',
        );
        expect(
            wrapper.find('[data-testid="social-accounts-account-github-link-btn"]').exists(),
        ).toBe(true);
    });

    it('hides the link button while GitHub OAuth is feature-flagged off', () => {
        const wrapper = mount(SocialAccounts, {
            props: { accounts: [ACCOUNTS[1]], can_link: false },
        });

        expect(
            wrapper.find('[data-testid="social-accounts-account-github-link-btn"]').exists(),
        ).toBe(false);
    });

    it('emits link with the provider', async () => {
        const wrapper = mount(SocialAccounts, {
            props: { accounts: [ACCOUNTS[1]] },
        });

        await wrapper.get('[data-testid="social-accounts-account-github-link-btn"]').trigger('click');

        expect(wrapper.emitted('link')).toEqual([[{ provider: 'github' }]]);
    });

    it('emits unlink after the confirmation dialog', async () => {
        const wrapper = mountSocialAccounts({ accounts: [ACCOUNTS[0]] });

        await wrapper.get('[data-testid="social-accounts-account-github-unlink-btn"]').trigger('click');

        expect(wrapper.find('[data-testid="social-accounts-unlink-dialog"]').exists()).toBe(true);

        await wrapper.get('[data-testid="social-accounts-unlink-dialog"] [data-testid="social-accounts-unlink-dialog-confirm"]').trigger('click');

        expect(wrapper.emitted('unlink')).toEqual([[{ provider: 'github' }]]);
    });
});
