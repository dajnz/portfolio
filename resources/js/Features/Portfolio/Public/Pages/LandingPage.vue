<!-- LandingPage — TEMPORARY placeholder (step 4.1, B107): the full landing (AboutBlock, SkillsBlock, FeaturedProjectsSection, ContactsBlock + FeedbackForm per public-section-ui.md:56-101) lands in step 9.1. The minimal page keeps `/` functional after the welcome route was replaced by the landing route (routes.md:22). -->
<script setup lang="ts">
import type { components } from '@shared-cross/Types/inertia';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';

type LandingProps = NonNullable<components['schemas']['LandingPageProps']['props']>;

defineProps<{
    about?: LandingProps['about'];
    skills?: LandingProps['skills'];
    featured_projects?: LandingProps['featured_projects'];
    has_more?: boolean;
    contacts?: LandingProps['contacts'];
}>();
</script>

<template>
  <PublicPageLayout>
    <section>
      <h1 v-if="about">
        {{ about.name }}
      </h1>
      <p v-if="about">
        {{ about.title }}
      </p>
      <p v-if="about">
        {{ about.short_pitch }}
      </p>
    </section>
    <ul v-if="contacts?.length">
      <li
        v-for="contact in contacts"
        :key="contact.id"
      >
        {{ contact.name }}: {{ contact.value }}
      </li>
    </ul>
  </PublicPageLayout>
</template>
