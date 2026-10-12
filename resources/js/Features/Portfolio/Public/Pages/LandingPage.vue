<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AboutBlock from '@portfolio-public/AboutBlock.vue';
import FeaturedProjectsSection from '@portfolio-public/FeaturedProjectsSection.vue';
import SkillsBlock from '@portfolio-public/SkillsBlock.vue';
import { usePageTracking } from '@shared-cross/Composables/usePageTracking';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import { defineAsyncComponent } from 'vue';
import { useLandingPage } from '../Composables/useLandingPage';
import { ensurePublicIcons } from '../iconRegistration';

type LandingProps = NonNullable<components['schemas']['LandingPageProps']['props']>;

// Per-property types (not the whole `LandingProps` alias): the SFC
// compiler's runtime prop resolution cannot expand the indexed-access
// `NonNullable<...['props']>` type.
const props = withDefaults(
    defineProps<{
        about?: LandingProps['about'];
        skills?: LandingProps['skills'];
        featured_projects?: LandingProps['featured_projects'];
        has_more?: boolean;
        contacts?: LandingProps['contacts'];
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
        feedback_honeypot_name: undefined,
        seo: undefined,
        test_id: 'landing-page',
    },
);

// The feedback section is dynamically imported (loaded when the user
// scrolls into view).
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
    active_route="#about"
    :logo_text="aboutBlock?.name ?? ''"
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
