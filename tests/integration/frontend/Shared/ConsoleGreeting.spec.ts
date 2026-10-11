import { usePage } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import ConsoleGreeting from '@shared-public/ConsoleGreeting.vue';

vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(() => ({
        props: {
            cspNonce: 'test-nonce',
            featureFlags: { console_greeting: false },
        },
    })),
}));

const CONTACTS = [
    { label: 'Email', value: 'alex@example.com' },
    { label: 'GitHub', value: 'alexkovalev' },
];

describe('ConsoleGreeting', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        document.head.innerHTML = '';
        vi.mocked(usePage).mockReturnValue({
            props: {
                errors: {},
                cspNonce: 'test-nonce',
                featureFlags: { console_greeting: false },
            },
        } as unknown as ReturnType<typeof usePage>);
    });

    it('injects a nonced script into the head when the flag is enabled', () => {
        vi.mocked(usePage).mockReturnValue({
            props: {
                errors: {},
                cspNonce: 'test-nonce',
                featureFlags: { console_greeting: true },
            },
        } as unknown as ReturnType<typeof usePage>);

        mount(ConsoleGreeting, {
            props: {
                greeting: 'Hey there!',
                name: 'Alex Kovalev',
                position: 'Full-Stack Developer',
                contacts: CONTACTS,
            },
        });

        const scripts = document.head.querySelectorAll('script');

        expect(scripts).toHaveLength(1);
        expect(scripts[0].nonce).toBe('test-nonce');
        expect(scripts[0].textContent).toContain('console.info');
        expect(scripts[0].textContent).toContain('%cHey there!');
        expect(scripts[0].textContent).toContain('%cEmail: %calex@example.com');
    });

    it('renders no visible DOM', () => {
        const wrapper = mount(ConsoleGreeting, {
            props: { greeting: 'Hi', name: 'Alex', position: 'Dev', contacts: [] },
        });

        expect(wrapper.html()).toBe('');
    });
});
