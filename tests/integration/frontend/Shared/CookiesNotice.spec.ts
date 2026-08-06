import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import CookiesNotice from '@shared-public/CookiesNotice.vue';

function mountNotice(props: Record<string, unknown> = {}) {
    return mount(CookiesNotice, {
        props,

    });
}

async function flush(): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve));
}

describe('CookiesNotice', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        localStorage.clear();
    });

    it('stays hidden when consent was already given', () => {
        localStorage.setItem('cookies_accepted', '1');
        const wrapper = mountNotice();

        expect(wrapper.find('[data-testid="cookies-notice"]').exists()).toBe(false);
    });

    it('appears when no consent is stored', async () => {
        const wrapper = mountNotice();
        await flush();

        expect(wrapper.find('[data-testid="cookies-notice"]').exists()).toBe(true);
    });

    it('renders the default translated message and accept label', async () => {
        const wrapper = mountNotice();
        await flush();

        expect(wrapper.text()).toContain('This site uses cookies for language preferences');
        expect(wrapper.text()).toContain('Accept');
    });

    it('renders custom message and accept label', async () => {
        const wrapper = mountNotice({
            message: 'Our cookie policy.',
            accept_label: 'Got it',
        });
        await flush();

        expect(wrapper.text()).toContain('Our cookie policy.');
        expect(wrapper.text()).toContain('Got it');
    });

    it('accept stores consent and emits accepted', async () => {
        const wrapper = mountNotice();
        await flush();

        await wrapper.get('[data-testid="cookies-notice-accept"]').trigger('click');

        expect(localStorage.getItem('cookies_accepted')).toBe('1');
        expect(wrapper.emitted('accepted')).toHaveLength(1);
        expect(wrapper.find('[data-testid="cookies-notice"]').exists()).toBe(false);
    });
});
