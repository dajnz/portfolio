import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';

describe('AdminStickyActionBar', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders its slot content and the sticky positioning classes', () => {
        const wrapper = mount(AdminStickyActionBar, {
            props: { test_id: 'bar' },
            slots: { default: '<button type="button">Save</button>' },
        });

        const bar = wrapper.get('[data-testid="bar"]');
        expect(bar.classes()).toContain('sticky');
        expect(bar.text()).toBe('Save');
    });
});
