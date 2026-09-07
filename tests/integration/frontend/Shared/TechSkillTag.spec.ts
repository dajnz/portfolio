import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';

const SKILL = { id: 3, name: 'Laravel', icon: 'devicon:laravel', proficiency: 4 };

describe('TechSkillTag', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the skill name with the icon once the offline devicon subset is registered', async () => {
        const wrapper = mount(TechSkillTag, { props: { skill: SKILL } });

        // The devicon subset imports lazily; the glyph pops in after the
        // shared registration resolves (never before — the Iconify
        // runtime would otherwise fall back to its blocked remote API).
        // The import chain is timer-driven, so poll instead of a fixed
        // flush (mirrors waitForHead in the useSeoUpdater spec).
        const deadline = Date.now() + 1000;
        while (!wrapper.find('svg').exists()) {
            if (Date.now() >= deadline) {
                throw new Error('devicon glyph did not appear within 1000ms');
            }
            await new Promise((resolve) => setTimeout(resolve, 10));
        }

        expect(wrapper.text()).toContain('Laravel');
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('renders as a span by default', () => {
        const wrapper = mount(TechSkillTag, { props: { skill: SKILL } });

        expect(wrapper.element.tagName).toBe('SPAN');
    });

    it('renders the proficiency label when showProficiency is set', () => {
        const wrapper = mount(TechSkillTag, {
            props: { skill: { ...SKILL, proficiencyLabel: 'Proficient' }, showProficiency: true },
        });

        expect(wrapper.get('[data-testid="tech-skill-tag-proficiency"]').text()).toContain(
            'Proficient',
        );
    });

    it('hides the proficiency indicator when showProficiency is unset', () => {
        const wrapper = mount(TechSkillTag, { props: { skill: SKILL } });

        expect(wrapper.find('[data-testid="tech-skill-tag-proficiency"]').exists()).toBe(false);
    });

    it('renders as a blog filter link when linked', () => {
        const wrapper = mount(TechSkillTag, { props: { skill: SKILL, linked: true } });

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('/blog?skill_tags=3');
    });

    it('applies the classes prop overrides in admin context', () => {
        const wrapper = mount(TechSkillTag, {
            props: { skill: SKILL, classes: { container: 'bg-primary-600 text-white' } },
        });

        expect(wrapper.classes()).toContain('bg-primary-600');
    });
});
