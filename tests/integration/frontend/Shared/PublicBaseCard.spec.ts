import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicBaseCard from '@shared-public/PublicBaseCard.vue';

describe('PublicBaseCard', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the slot content', () => {
        const wrapper = mount(PublicBaseCard, {
            slots: { default: '<p>Body</p>' },
        });

        expect(wrapper.get('.card__body').text()).toBe('Body');
    });

    it('applies the padding variant class', () => {
        const wrapper = mount(PublicBaseCard, {
            props: { padding: 'lg' },
        });

        expect(wrapper.get('[data-testid="base-card"]').classes()).toContain('card--padding-lg');
    });

    it('uses the default padding without an extra class', () => {
        const wrapper = mount(PublicBaseCard);

        expect(wrapper.get('[data-testid="base-card"]').classes()).not.toContain('card--padding-md');
    });

    it('applies hoverable', () => {
        const wrapper = mount(PublicBaseCard, {
            props: { hoverable: true },
        });

        expect(wrapper.get('[data-testid="base-card"]').classes()).toContain('card--hoverable');
    });

    it('renders header and footer slots', () => {
        const wrapper = mount(PublicBaseCard, {
            slots: {
                header: '<h3 data-testid="card-header">Title</h3>',
                footer: '<div data-testid="card-footer">Footer</div>',
            },
        });

        expect(wrapper.find('[data-testid="card-header"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="card-footer"]').exists()).toBe(true);
    });
});
