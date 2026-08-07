import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: vi.fn(), cleanup: vi.fn() }),
}));

import FeatureFlagToggle from '@settings/Admin/Components/FeatureFlagToggle.vue';

const FLAG = { key: 'github_oauth', name: 'GitHub OAuth login', is_enabled: false };

describe('FeatureFlagToggle', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the flag name and hint with an unchecked toggle when disabled', () => {
        const wrapper = mount(FeatureFlagToggle, {
            props: { flag: FLAG, hint: 'Show the button.' },
        });

        expect(wrapper.get('[data-testid="feature-flag-toggle"]').text()).toContain('GitHub OAuth login');
        expect(wrapper.get('[data-testid="feature-flag-toggle"]').text()).toContain('Show the button.');
        expect(
            (wrapper.get('[data-testid="feature-flag-toggle-toggle"]').element as HTMLInputElement).checked,
        ).toBe(false);
    });

    it('checks the toggle when the flag is enabled', () => {
        const wrapper = mount(FeatureFlagToggle, {
            props: { flag: { ...FLAG, is_enabled: true } },
        });

        expect(
            (wrapper.get('[data-testid="feature-flag-toggle-toggle"]').element as HTMLInputElement).checked,
        ).toBe(true);
    });

    it('names the toggle after the flag for accessibility', () => {
        const wrapper = mount(FeatureFlagToggle, {
            props: { flag: FLAG },
        });

        expect(wrapper.get('[data-testid="feature-flag-toggle-toggle"]').attributes('aria-label')).toBe(
            'GitHub OAuth login',
        );
    });

    it('emits toggle with the key and the new state', async () => {
        const wrapper = mount(FeatureFlagToggle, {
            props: { flag: FLAG },
        });

        await wrapper.get('[data-testid="feature-flag-toggle-toggle"]').setValue(true);

        expect(wrapper.emitted('toggle')).toEqual([[{ key: 'github_oauth', is_enabled: true }]]);
    });
});
