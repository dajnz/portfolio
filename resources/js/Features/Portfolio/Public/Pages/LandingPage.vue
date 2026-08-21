<!-- LandingPage (public-section-ui.md:56-101, public-section-components.md:889-1007) — replaces the step-4.1 placeholder (B107): AboutBlock + SkillsBlock (masonry) + FeaturedProjectsSection + ContactsBlock (lazy-loaded feedback section per architecture.md's Lazy-Loading Rules), laid out on PublicPageLayout(centered: false) so every section spans full width with its own background. Wiring: nav items per A10, SEO updater (seo.md:47), dns-prefetch for external links (architecture.md:1271) and the eager offline registration of the devicon/simple-icons sets (B78). -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AboutBlock from '@portfolio-public/AboutBlock.vue';
import FeaturedProjectsSection from '@portfolio-public/FeaturedProjectsSection.vue';
import SkillsBlock from '@portfolio-public/SkillsBlock.vue';
import { usePageTracking } from '@shared-cross/Composables/usePageTracking';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import type { ConsoleGreetingData } from '@shared-public/ConsoleGreeting.vue';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import { defineAsyncComponent } from 'vue';
import { useLandingPage } from '../Composables/useLandingPage';
import { ensurePublicIcons } from '../iconRegistration';

type LandingProps = NonNullable<components['schemas']['LandingPageProps']['props']>;

// Per-property types (not the whole `LandingProps` alias): the SFC
// compiler's runtime prop resolution cannot expand the indexed-access
// `NonNullable<...['props']>` type (B107 placeholder used the same form).
const props = withDefaults(
    defineProps<{
        about?: LandingProps['about'];
        skills?: LandingProps['skills'];
        featured_projects?: LandingProps['featured_projects'];
        has_more?: boolean;
        contacts?: LandingProps['contacts'];
        console_greeting?: LandingProps['console_greeting'];
        feedback_honeypot_name?: LandingProps['feedback_honeypot_name'];
        seo?: LandingProps['seo'];
        test_id?: string;
    }>(),
    {
        about: undefined,
        skills: undefined,
        featured_projects: undefined,
        has_more: undefined,
        contacts: undefined,
        console_greeting: undefined,
        feedback_honeypot_name: undefined,
        seo: undefined,
        test_id: 'landing-page',
    },
);

// The wire type has optional fields; the layout requires the full shape.
// (The cast must live in the script — a template `as X | Y` would be
// parsed as a deprecated filter by the Vue template compiler.)
const greeting = (props.console_greeting ?? null) as ConsoleGreetingData | null;

// The feedback section is dynamically imported (architecture.md:
// "Feedback form | Dynamic import() | When the user scrolls into view").
const ContactsBlock = defineAsyncComponent(() => import('@portfolio-public/ContactsBlock.vue'));

ensurePublicIcons();
usePageTracking('landing');
useSeoUpdater();

const {
    navItems,
    aboutBlock,
    ctaLinks,
    skillGroups,
    featuredProjects,
    hasMore,
    contactLinks,
    feedbackProps,
    externalHosts,
} = useLandingPage(props);
</script>

<template>
  <PublicPageLayout
    :centered="false"
    :nav_items="navItems"
    :logo_text="aboutBlock?.name ?? ''"
    :console_greeting="greeting"
    :test_id="props.test_id"
  >
    <Head>
      <link
        v-for="host in externalHosts"
        :key="host"
        rel="dns-prefetch"
        :href="`https://${host}`"
      >
    </Head>

    <AboutBlock
      :about="aboutBlock"
      :cta_links="ctaLinks"
    />
    <SkillsBlock :skill_groups="skillGroups" />
    <FeaturedProjectsSection
      :projects="featuredProjects"
      :has_more="hasMore"
    />
    <ContactsBlock
      :contact_links="contactLinks"
      :feedback_form_props="feedbackProps"
    />
  </PublicPageLayout>
</template>
