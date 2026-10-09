import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick, reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { visitMock, pageProps, pageRef, analyticsTrack } = vi.hoisted(() => {
    const pageProps = {
        locale: 'en',
        featureFlags: {},
        cspNonce: 'nonce-test',
    };

    return {
        visitMock: vi.fn(),
        pageProps,
        // Shared reactive page wrapper (the real usePage returns the
        // reactive page object): mutations must go through the proxy —
        // raw-object writes would bypass the reactivity tracking.
        pageRef: { value: null as { props: typeof pageProps } | null },
        analyticsTrack: vi.fn(),
    };
});

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: [],
        template: '<div style="display:none"><slot /></div>',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
    router: {
        visit: visitMock,
        on: () => () => {},
    },
    usePage: () => {
        if (pageRef.value === null) {
            pageRef.value = reactive({ props: pageProps });
        }

        return pageRef.value;
    },
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: analyticsTrack, setPageContext: vi.fn(), cleanup: vi.fn(), trackPageView: vi.fn() }),
}));

import LandingPage from '@portfolio/Public/Pages/LandingPage.vue';

const HONEYPOT = 'ab12cd34ef56ab12cd34ef56ab12cd34ef';

const LANDING_PROPS = {
    about: {
        name: 'Alex Kovalev',
        title: 'Full-Stack Developer',
        short_pitch: 'I build fast web apps.',
        photo: 'about/photo.jpg',
        photo_alt: { en: 'Portrait of Alex', ru: 'Портрет Алекса' },
        location: 'Berlin',
        show_location: true,
        timezone: 'Europe/Berlin',
        availability_status: 'available',
        work_type: 'hybrid',
        relocation_readiness: true,
    },
    skills: [
        {
            id: 1,
            name: 'Backend',
            icon: 'heroicons:server',
            skills: [
                { id: 11, name: 'Laravel', icon: 'devicon:laravel', proficiency: 5 },
                { id: 12, name: 'PostgreSQL', icon: 'devicon:postgresql', proficiency: 4 },
            ],
        },
        {
            id: 2,
            name: 'Frontend',
            icon: 'heroicons:code-bracket',
            skills: [{ id: 21, name: 'Vue 3', icon: 'devicon:vuejs', proficiency: 4 }],
        },
    ],
    featured_projects: [
        {
            id: 1,
            title: 'Ledger',
            slug: 'ledger',
            brief_description: 'Invoice automation.',
            preview_image: 'projects/ledger.jpg',
            tech_tags: [{ id: 11, name: 'Laravel', icon: 'devicon:laravel' }],
        },
    ],
    has_more: true,
    contacts: [
        { id: 1, name: 'GitHub', icon: 'simple-icons:github', value: 'octocat', url: 'https://github.com/alex' },
        { id: 2, name: 'Telegram', icon: 'simple-icons:telegram', value: '@alex_dev', url: null },
    ],
    feedback_honeypot_name: HONEYPOT,
    seo: { title: 'Alex Kovalev — Full-Stack Developer', description: 'I build fast web apps.' },
};

function mountPage(props: Record<string, unknown> = {}) {
    return mount(LandingPage, {
        props: { ...LANDING_PROPS, ...props } as unknown as InstanceType<typeof LandingPage>['$props'],
    });
}

async function flush(): Promise<void> {
    // The lazy ContactsBlock (defineAsyncComponent) + the eager icon
    // registration resolve across several microtask/macrotask turns.
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await flushPromises();
}

// The FIRST dynamic import of the ContactsBlock chunk pays the SFC
// transform time (real-time work microtasks cannot wait for) — and under
// parallel suite load that time varies, so poll instead of sleeping.
async function flushUntilContacts(wrapper: ReturnType<typeof mountPage>): Promise<void> {
    const deadline = Date.now() + 10_000;
    while (!wrapper.find('[data-testid="landing-contacts-block"]').exists()) {
        if (Date.now() > deadline) {
            throw new Error('ContactsBlock did not render within 10s');
        }

        await new Promise((resolve) => setTimeout(resolve, 100));
        await flushPromises();
    }
}

describe('LandingPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        pageProps.locale = 'en';
        visitMock.mockClear();
        analyticsTrack.mockClear();
        window.localStorage.clear();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('renders within the full-width public layout with all landing sections', async () => {
        const wrapper = mountPage();
        // Cold start: the lazy ContactsBlock chunk is transformed on first
        // import — poll until it renders.
        await flushUntilContacts(wrapper);

        expect(wrapper.find('[data-testid="landing-page"]').exists()).toBe(true);
        const main = wrapper.get('[data-testid="public-layout-main"]');
        expect(main.classes()).toContain('centered-container--full');
        expect(wrapper.find('[data-testid="landing-about-block"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="landing-skills-block"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="landing-featured-section"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="landing-contacts-block"]').exists()).toBe(true);
    });

    it('renders the about hero with name, title, pitch, photo and availability badge', async () => {
        const wrapper = mountPage();
        await flush();

        const about = wrapper.get('[data-testid="landing-about-block"]');
        expect(about.get('h1').text()).toBe('Alex Kovalev');
        expect(about.get('.about-block__title').text()).toBe('Full-Stack Developer');
        expect(about.get('.about-block__pitch').text()).toBe('I build fast web apps.');
        expect(about.get('[data-testid="landing-about-block-photo"]').attributes('src')).toBe(
            '/assets/about/photo.jpg',
        );
        expect(about.get('[data-testid="landing-about-block-photo"]').attributes('alt')).toBe(
            'Portrait of Alex',
        );

        const badge = about.get('[data-testid="landing-about-block-availability"]');
        expect(badge.classes()).toContain('badge--success');
        expect(badge.text()).toContain('Available');

        const location = about.get('[data-testid="landing-about-block-location"]');
        expect(location.text()).toContain('Berlin');
        expect(location.text()).toMatch(/UTC[+-]\d/);

        const workType = about.get('[data-testid="landing-about-block-work-type"]');
        expect(workType.text()).toBe('Hybrid · Open to relocation');
    });

    it('renders the CTA row: GitHub, Download CV and View Projects', async () => {
        const wrapper = mountPage();
        await flush();

        const github = wrapper.get('[data-testid="landing-about-block-cta-github"]');
        expect(github.attributes('href')).toBe('https://github.com/alex');
        expect(github.text()).toContain('GitHub');

        const cv = wrapper.get('[data-testid="landing-about-block-cta-cv"]');
        expect(cv.attributes('href')).toBe('/cv');
        expect(cv.text()).toBe('Download CV');

        const projects = wrapper.get('[data-testid="landing-about-block-cta-projects"]');
        expect(projects.attributes('href')).toBe('/projects');
    });

    it('fires cta_click_github, cta_click_cv and cta_click_projects from the about CTAs', async () => {
        const wrapper = mountPage();
        await flush();

        await wrapper.get('[data-testid="landing-about-block-cta-github"]').trigger('click');
        expect(analyticsTrack).toHaveBeenCalledWith('cta_click_github');

        await wrapper.get('[data-testid="landing-about-block-cta-cv"]').trigger('click');
        expect(analyticsTrack).toHaveBeenCalledWith('cta_click_cv');

        analyticsTrack.mockClear();
        await wrapper.get('[data-testid="landing-about-block-cta-projects"]').trigger('click');
        expect(analyticsTrack).toHaveBeenCalledWith('cta_click_projects');
    });

    it('renders the about skeleton when no about row exists', async () => {
        const wrapper = mountPage({ about: null });
        await flush();

        expect(wrapper.find('[data-testid="landing-about-block-skeleton"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="landing-about-block"]').find('h1').exists()).toBe(false);
    });

    it('renders the "My skills" heading above the skill groups', async () => {
        const wrapper = mountPage();
        await flush();

        const heading = wrapper.get('[data-testid="landing-skills-block"] .section-title');
        expect(heading.text()).toBe('My Skills');
    });

    it('renders skill groups in the alternating 1/3-2/3 masonry rows', async () => {
        const wrapper = mountPage({
            skills: [
                ...LANDING_PROPS.skills,
                {
                    id: 3,
                    name: 'DevOps',
                    icon: 'heroicons:circle-stack',
                    skills: [{ id: 31, name: 'Docker', icon: 'devicon:docker', proficiency: 3 }],
                },
            ],
        });
        await flush();

        const grid = wrapper.get('[data-testid="landing-skills-block"]');
        const rows = grid.findAll('.skills-block__row');
        expect(rows).toHaveLength(2);

        // Row 1: narrow (Backend) + wide (Frontend); row 2: wide (DevOps) alone.
        expect(rows[0]!.classes()).not.toContain('skills-block__row--wide-first');
        expect(rows[1]!.classes()).toContain('skills-block__row--wide-first');
        expect(rows[1]!.classes()).toContain('skills-block__row--single');

        const group = grid.get('[data-testid="landing-skill-group-1"]');
        expect(group.get('.skill-group__name').text()).toContain('Backend');
    });

    it('renders skill items as compact 2-row chips with level-colored circles and a plain level title', async () => {
        const wrapper = mountPage();
        await flush();

        const skill = wrapper.get('[data-testid="landing-skill-item-11"]');
        // Top row: icon + name; bottom row: the indicator.
        expect(skill.get('.skill-item__top .skill-item__name').text()).toBe('Laravel');
        const level = skill.get('[data-testid="landing-skill-item-11-level"]');
        const dots = level.findAll('.skill-item__dot');
        expect(dots).toHaveLength(5);
        expect(level.findAll('.skill-item__dot.is-filled')).toHaveLength(5);

        // The title/aria-label carry only the level name (not "Laravel — …").
        expect(level.attributes('title')).toBe('Expert');
        expect(level.attributes('aria-label')).toBe('Expert');

        // Filled circles share the current level's color (5 = violet).
        expect(dots[0]!.attributes('style')).toContain('--dot-color: #8b5cf6');

        const partial = wrapper.get('[data-testid="landing-skill-item-12"]');
        expect(partial.findAll('.skill-item__dot.is-filled')).toHaveLength(4);
        expect(partial.get('.skill-item__level').attributes('title')).toBe('Proficient');
    });

    it('renders featured project cards linking to the project pages', async () => {
        const wrapper = mountPage();
        await flush();

        const card = wrapper.get('[data-testid="landing-featured-section-card-1"]');
        expect(card.get('a.project-card__link').attributes('href')).toBe('/projects/ledger');
        expect(card.get('h3').text()).toBe('Ledger');
        expect(card.get('img').attributes('src')).toBe('/assets/projects/ledger.jpg');
        expect(card.get('img').attributes('loading')).toBe('lazy');
        expect(card.text()).toContain('Invoice automation.');
    });

    it('renders the projects-index no-image placeholder (large faint icon, no text) without a preview', async () => {
        const wrapper = mountPage({
            featured_projects: [
                { ...LANDING_PROPS.featured_projects[0], preview_image: null },
            ],
        });
        await flush();

        const placeholder = wrapper.get(
            '[data-testid="landing-featured-section-card-1-placeholder"]',
        );
        expect(placeholder.attributes('aria-hidden')).toBe('true');
        expect(placeholder.find('.project-card__placeholder-icon').exists()).toBe(true);
        expect(placeholder.text()).toBe('');
        // No preview image renders.
        expect(wrapper.find('[data-testid="landing-featured-section-card-1"] img').exists()).toBe(
            false,
        );
    });

    it('links the featured card tech tags to the projects filter only when more projects exist', async () => {
        const withMore = mountPage();
        await flush();

        const tag = withMore.get('[data-testid="landing-featured-section-card-1"] .tech-skill-tag');
        expect(tag.element.tagName).toBe('A');
        expect(tag.attributes('href')).toBe('/projects?tags=11');

        const withoutMore = mountPage({ has_more: false });
        await flush();

        const plainTag = withoutMore.get(
            '[data-testid="landing-featured-section-card-1"] .tech-skill-tag',
        );
        expect(plainTag.element.tagName).toBe('SPAN');
        expect(plainTag.attributes('href')).toBeUndefined();
    });

    it('shows "View all projects" only when more projects exist beyond featured', async () => {
        const withMore = mountPage();
        await flush();
        expect(withMore.get('[data-testid="landing-featured-section-view-all"]').attributes('href')).toBe(
            '/projects',
        );

        const withoutMore = mountPage({ has_more: false });
        await flush();
        expect(withoutMore.find('[data-testid="landing-featured-section-view-all"]').exists()).toBe(false);
    });

    it('hides the blocks when no data is configured', async () => {
        const wrapper = mountPage({
            about: null,
            skills: [],
            featured_projects: [],
            has_more: false,
            contacts: [],
        });
        await flush();

        expect(wrapper.find('[data-testid="landing-skills-block"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="landing-featured-section"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="landing-contacts-block"]').exists()).toBe(false);
    });

    it('renders the contacts column with links and plain values, and the feedback form', async () => {
        const wrapper = mountPage();
        await flush();

        const github = wrapper.get('[data-testid="landing-contacts-block-link-1"]');
        expect(github.element.tagName).toBe('A');
        expect(github.attributes('href')).toBe('https://github.com/alex');
        expect(github.text()).toContain('octocat');

        const telegram = wrapper.get('[data-testid="landing-contacts-block-link-2"]');
        expect(telegram.element.tagName).toBe('SPAN');
        expect(telegram.attributes('href')).toBeUndefined();
        expect(wrapper.get('[data-testid="landing-contacts-block-row-2"]').classes()).toContain(
            'contact-row--plain',
        );

        expect(wrapper.find('[data-testid="landing-feedback-form"]').exists()).toBe(true);
    });

    it('copies the contact url via the copy button and flashes the check icon', async () => {
        const writeTextMock = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText: writeTextMock },
            configurable: true,
        });

        const wrapper = mountPage();
        await flush();

        const copy = wrapper.get('[data-testid="landing-contacts-block-copy-1"]');
        expect(copy.attributes('title')).toBe('Copy contact');
        expect(copy.attributes('aria-label')).toBe('Copy contact');

        const iconBefore = copy.find('svg').html();
        await copy.trigger('click');
        await flush();

        expect(writeTextMock).toHaveBeenCalledWith('https://github.com/alex');
        expect(copy.find('svg').html()).not.toBe(iconBefore);

        // The copied state is announced once through the status region —
        // the button label stays stable so VoiceOver does not also
        // announce the changed accessible name.
        expect(copy.attributes('aria-label')).toBe('Copy contact');
        expect(wrapper.get('[data-testid="landing-contacts-block-copy-status"]').text()).toBe(
            'Copied!',
        );

        // The contact without a url copies its value instead.
        const valueCopy = wrapper.get('[data-testid="landing-contacts-block-copy-2"]');
        await valueCopy.trigger('click');
        await flush();
        expect(writeTextMock).toHaveBeenLastCalledWith('@alex_dev');
    });

    it('builds the public nav per ADR-0025: anchors always, projects conditional', async () => {
        const wrapper = mountPage();
        await flush();

        expect(wrapper.get('[data-testid="public-header-nav-#about"]').attributes('href')).toBe('#about');
        expect(wrapper.get('[data-testid="public-header-nav-/projects"]').attributes('href')).toBe('/projects');
        expect(wrapper.get('[data-testid="public-header-nav-/experience"]').attributes('href')).toBe('/experience');
        expect(wrapper.get('[data-testid="public-header-nav-/blog"]').attributes('href')).toBe('/blog');
        expect(wrapper.get('[data-testid="public-header-nav-#contacts"]').attributes('href')).toBe('#contacts');

        // The landing highlights About Me.
        const about = wrapper.get('[data-testid="public-header-nav-#about"]');
        expect(about.attributes('aria-current')).toBe('page');
        expect(about.classes()).toContain('is-active');

        const noProjects = mountPage({ featured_projects: [], has_more: false });
        await flush();
        expect(noProjects.find('[data-testid="public-header-nav-/projects"]').exists()).toBe(false);
        expect(noProjects.find('[data-testid="public-header-nav-/blog"]').exists()).toBe(true);
    });

    it('adds dns-prefetch hints for the external contact hosts', async () => {
        const wrapper = mountPage();
        await flush();

        const hints = wrapper.findAll('link[rel="dns-prefetch"]');
        expect(hints.map((hint) => hint.attributes('href'))).toEqual(['https://github.com']);
    });

    it('keeps the language selector in sync with the page locale across visits', async () => {
        const wrapper = mountPage();
        await flush();

        const trigger = wrapper.get('[data-testid="language-selector-button"]');
        expect(trigger.text()).toBe('EN');

        // Simulate the reload after the first switch: the page props
        // arrive with the new locale while the component tree survives.
        pageRef.value!.props.locale = 'ru';
        await nextTick();

        expect(wrapper.get('[data-testid="language-selector-button"]').text()).toBe('RU');
    });

    it('submits the feedback form with the honeypot field and shows the success state', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 201 });
        vi.mocked(fetch).mockImplementation(fetchMock);

        const wrapper = mountPage();
        await flush();

        const form = wrapper.get('[data-testid="landing-feedback-form"]');
        await form.get('[data-testid="landing-feedback-form-name"]').setValue('Alex');
        await form.get('[data-testid="landing-feedback-form-email"]').setValue('alex@example.com');
        await form.get('[data-testid="landing-feedback-form-message"]').setValue('Hello there!');
        await form.trigger('submit');
        await flush();

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/feedback',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    sender_name: 'Alex',
                    sender_email: 'alex@example.com',
                    message: 'Hello there!',
                    [HONEYPOT]: '',
                }),
            }),
        );

        const success = wrapper.get('[data-testid="landing-feedback-form-success"]');
        expect(success.text()).toContain('Thanks, Alex!');
        expect(analyticsTrack).toHaveBeenCalledWith('cta_click_feedback');
    });
});
