import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

describe('AdminBaseCard', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the slot content', () => {
        const wrapper = mount(AdminBaseCard, {
            slots: { default: '<p>Body</p>' },
        });

        expect(wrapper.text()).toBe('Body');
    });

    it('applies the padding variant class', () => {
        const wrapper = mount(AdminBaseCard, {
            props: { padding: 'lg' },
        });

        expect(wrapper.get('[data-testid="base-card"]').classes()).toContain('p-6');
    });

    it('uses the default md padding', () => {
        const wrapper = mount(AdminBaseCard);

        expect(wrapper.get('[data-testid="base-card"]').classes()).toContain('p-5');
    });

    it('skips the padding classes when padding is none', () => {
        const wrapper = mount(AdminBaseCard, {
            props: { padding: 'none' },
        });

        const root = wrapper.get('[data-testid="base-card"]');
        expect(root.classes()).not.toContain('p-5');
        expect(root.classes()).not.toContain('p-6');
    });

    it('applies hoverable', () => {
        const wrapper = mount(AdminBaseCard, {
            props: { hoverable: true },
        });

        expect(wrapper.get('[data-testid="base-card"]').classes()).toContain(
            'hover:shadow-card-hover',
        );
    });

    it('renders header and footer slots only when provided', () => {
        const withSlots = mount(AdminBaseCard, {
            slots: {
                header: '<h3 data-testid="card-header">Title</h3>',
                footer: '<div data-testid="card-footer">Footer</div>',
            },
        });

        expect(withSlots.find('div.mb-4').exists()).toBe(true);
        expect(withSlots.find('div.mt-4').exists()).toBe(true);
        expect(withSlots.find('[data-testid="card-header"]').exists()).toBe(true);
        expect(withSlots.find('[data-testid="card-footer"]').exists()).toBe(true);

        const withoutSlots = mount(AdminBaseCard);

        expect(withoutSlots.find('div.mb-4').exists()).toBe(false);
        expect(withoutSlots.find('div.mt-4').exists()).toBe(false);
    });
});
