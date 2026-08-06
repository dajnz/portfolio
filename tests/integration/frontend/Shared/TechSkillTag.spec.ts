import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';

const SKILL = { id: 3, name: 'Laravel', icon: 'devicon:laravel', proficiency: 4 };

describe('TechSkillTag', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders the skill name with the icon', () => {
        const wrapper = mount(TechSkillTag, { props: { skill: SKILL } });

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
