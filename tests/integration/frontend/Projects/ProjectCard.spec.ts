import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { trackMock } = vi.hoisted(() => ({ trackMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: trackMock, cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import ProjectCard from '@projects/Public/Components/ProjectCard.vue';

const PROJECT = {
    id: 7,
    title: 'Ledger — Invoice Automation',
    slug: 'ledger',
    brief_description: 'Automated invoice processing pipeline with OCR.',
    preview_image: null,
    preview_image_alt: '',
    github_url: 'https://example.com/ledger',
    live_demo_url: null,
    tech_tags: [
        { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
        { id: 2, name: 'Vue 3', icon: 'devicon:vuejs' },
    ],
    screenshots: null,
};

function mountCard(props: Record<string, unknown> = {}) {
    return mount(ProjectCard, {
        props: { project: PROJECT, ...props } as unknown as InstanceType<typeof ProjectCard>['$props'],
        attachTo: document.body,
        global: { stubs: { Teleport: true } },
    });
}

describe('ProjectCard', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        trackMock.mockClear();
        document.body.innerHTML = '';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders as a link to the detail page with title and description', () => {
        const wrapper = mountCard();

        expect(wrapper.element.tagName).toBe('A');
        expect(wrapper.attributes('href')).toBe('/projects/ledger');
        expect(wrapper.get('h3').text()).toBe('Ledger — Invoice Automation');
        expect(wrapper.get('p').text()).toBe('Automated invoice processing pipeline with OCR.');
    });

    it('renders the carousel when screenshots exist and the preview image otherwise', () => {
        const withShots = mountCard({
            project: {
                ...PROJECT,
                screenshots: [
                    { image_url: '/assets/projects/ledger-1.png', alt_text: 'Dashboard' },
                    { image_url: '/assets/projects/ledger-2.png', alt_text: 'Approvals' },
                ],
            },
        });
        expect(withShots.find('[data-testid="project-card-carousel"]').exists()).toBe(true);

        const singleShot = mountCard({
            project: {
                ...PROJECT,
                screenshots: [{ image_url: '/assets/projects/ledger-1.png', alt_text: 'Dashboard' }],
            },
        });
        // The carousel's `single` state is a static image.
        expect(singleShot.find('[data-testid="project-card-carousel"] img').exists()).toBe(true);

        const withPreview = mountCard({
            project: {
                ...PROJECT,
                preview_image: '/assets/projects/ledger.png',
                preview_image_alt: 'Ledger preview',
            },
        });
        const img = withPreview.get('img');
        expect(img.attributes('src')).toBe('/assets/projects/ledger.png');
        expect(img.attributes('alt')).toBe('Ledger preview');

        const placeholder = mountCard();
        expect(placeholder.find('.project-card__image--placeholder').exists()).toBe(true);
    });

    it('links the tech tags to the projects tag filter', () => {
        const wrapper = mountCard();

        const tags = wrapper.findAll('a.tech-skill-tag');
        expect(tags).toHaveLength(2);
        expect(tags[0].attributes('href')).toBe('/projects?tags=1');
        expect(tags[1].attributes('href')).toBe('/projects?tags=2');
    });

    it('renders non-interactive tag chips when tags_linked is false', () => {
        const wrapper = mountCard({ tags_linked: false });

        const tags = wrapper.findAll('.tech-skill-tag');
        expect(tags).toHaveLength(2);
        expect(tags[0].element.tagName).toBe('SPAN');
    });

    it('caps the tech tags at tags_limit and shows a "+N" overflow chip with the remaining names', () => {
        const project = {
            ...PROJECT,
            tech_tags: [
                { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
                { id: 2, name: 'Vue 3', icon: 'devicon:vuejs' },
                { id: 3, name: 'PostgreSQL', icon: 'devicon:postgresql' },
                { id: 4, name: 'Redis', icon: 'devicon:redis' },
                { id: 5, name: 'Docker', icon: 'devicon:docker' },
            ],
        };

        // The index page passes 3 for the grid and 5 for the list view.
        const grid = mountCard({ project });
        expect(grid.findAll('a.tech-skill-tag')).toHaveLength(3);
        const gridOverflow = grid.get('[data-testid="project-card-tags-overflow"]');
        expect(gridOverflow.text()).toBe('+2');
        expect(gridOverflow.attributes('aria-label')).toBe('And 2 more: Redis, Docker');

        const list = mountCard({ project, tags_limit: 5 });
        expect(list.findAll('a.tech-skill-tag')).toHaveLength(5);
        expect(list.find('[data-testid="project-card-tags-overflow"]').exists()).toBe(false);
    });

    it('renders the neat placeholder (large faint icon, no label) when the project has no images', () => {
        const wrapper = mountCard();

        const placeholder = wrapper.get('.project-card__image--placeholder');
        expect(placeholder.attributes('aria-hidden')).toBe('true');
        expect(placeholder.find('.project-card__placeholder-icon').exists()).toBe(true);
        // The "PREVIEW" text label was dropped (2026-08-16 rework).
        expect(placeholder.find('.project-card__placeholder-label').exists()).toBe(false);
        expect(placeholder.text()).toBe('');
    });

    it('renders the outline (contour) buttons in a new tab and fires the analytics events', async () => {
        const wrapper = mountCard({
            project: { ...PROJECT, live_demo_url: 'https://example.com/demo' },
        });

        const github = wrapper.get('[data-testid="project-card-github"]');
        expect(github.attributes('href')).toBe('https://example.com/ledger');
        expect(github.attributes('target')).toBe('_blank');
        expect(github.attributes('rel')).toBe('noopener');
        // The 2026-08-16 rework: light contour buttons instead of the
        // heavy solid secondary variant.
        expect(github.classes()).toContain('btn--outline');
        expect(github.text()).toContain('Sources');

        const demo = wrapper.get('[data-testid="project-card-demo"]');
        expect(demo.attributes('href')).toBe('https://example.com/demo');
        expect(demo.attributes('target')).toBe('_blank');
        expect(demo.classes()).toContain('btn--outline');
        expect(demo.text()).toContain('Live Demo');

        await github.trigger('click');
        await demo.trigger('click');

        expect(trackMock).toHaveBeenCalledTimes(2);
        expect(trackMock).toHaveBeenNthCalledWith(1, 'cta_click_github');
        expect(trackMock).toHaveBeenNthCalledWith(2, 'cta_click_demo');
    });

    it('applies the compact variant class', () => {
        const wrapper = mountCard({ variant: 'compact' });

        expect(wrapper.classes()).toContain('project-card--compact');
    });

    it('renders the skeleton when is_loading', () => {
        const wrapper = mountCard({ is_loading: true });

        expect(wrapper.classes()).toContain('project-card--skeleton');
    });
});
