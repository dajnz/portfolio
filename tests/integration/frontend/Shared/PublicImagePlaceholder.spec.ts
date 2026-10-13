import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import PublicImagePlaceholder from '@shared-public/PublicImagePlaceholder.vue';

describe('PublicImagePlaceholder', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the patterned placeholder with the faint photo icon by default', () => {
        const wrapper = mount(PublicImagePlaceholder);

        const placeholder = wrapper.get('[data-testid="image-placeholder"]');
        expect(placeholder.classes()).toContain('image-placeholder--patterned');
        expect(placeholder.attributes('aria-hidden')).toBe('true');
        expect(wrapper.get('.image-placeholder__icon').element.tagName).toBe('svg');
    });

    it('renders the plain placeholder with no icon and no text', () => {
        const wrapper = mount(PublicImagePlaceholder, {
            props: { variant: 'plain' },
        });

        expect(wrapper.get('[data-testid="image-placeholder"]').classes()).toContain(
            'image-placeholder--plain',
        );
        expect(wrapper.find('.image-placeholder__icon').exists()).toBe(false);
        expect(wrapper.text()).toBe('');
    });

    it('uses the provided test id', () => {
        const wrapper = mount(PublicImagePlaceholder, {
            props: { test_id: 'project-card-placeholder' },
        });

        expect(wrapper.find('[data-testid="project-card-placeholder"]').exists()).toBe(true);
    });
});
