<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';
import ConsoleGreeting, { type ConsoleGreetingData } from './ConsoleGreeting.vue';
import CookiesNotice from './CookiesNotice.vue';
import PublicBaseToastContainer from './PublicBaseToastContainer.vue';
import PublicCenteredContainer from './PublicCenteredContainer.vue';
import PublicGoTop from './PublicGoTop.vue';
import PublicPageFooter from './PublicPageFooter.vue';
import PublicPageHeader from './PublicPageHeader.vue';

const props = withDefaults(
    defineProps<{
        nav_items?: Array<{ label: string; url: string; condition?: boolean }>;
        active_route?: string;
        logo_url?: string;
        logo_text?: string;
        copyright_year?: string;
        copyright_name?: string;
        centered?: boolean;
        test_id?: string;
    }>(),
    {
        nav_items: () => [],
        active_route: undefined,
        logo_url: '/',
        logo_text: undefined,
        copyright_year: undefined,
        copyright_name: undefined,
        centered: true,
        test_id: 'public-layout',
    },
);

const { t } = useI18n();

type PublicPageProps = {
    console_greeting?: ConsoleGreetingData | null;
};

const page = usePage<PublicPageProps>();

const greeting = computed((): ConsoleGreetingData | null => {
    const consoleGreeting = page.props?.console_greeting;

    return consoleGreeting ?? null;
});
</script>

<template>
  <div
    class="public-page-layout"
    :data-testid="props.test_id"
  >
    <a
      class="skip-link"
      href="#main-content"
      data-testid="public-layout-skip-link"
    >
      {{ t('nav.skip_to_content') }}
    </a>
    <PublicPageHeader
      :nav_items="props.nav_items"
      :active_route="props.active_route ?? '/'"
      :logo_url="props.logo_url"
      :logo_text="props.logo_text"
    >
      <template #controls>
        <slot name="controls" />
      </template>
    </PublicPageHeader>
    <PublicCenteredContainer
      id="main-content"
      tag="main"
      size="xl"
      :centered="props.centered"
      class="public-page-layout__main"
      test_id="public-layout-main"
    >
      <slot />
    </PublicCenteredContainer>
    <PublicPageFooter
      :copyright_year="props.copyright_year"
      :copyright_name="props.copyright_name"
    >
      <slot name="footer" />
    </PublicPageFooter>
    <PublicGoTop />
    <CookiesNotice />
    <ConsoleGreeting
      v-if="greeting !== null"
      v-bind="greeting"
    />
    <PublicBaseToastContainer />
  </div>
</template>

<style scoped>
.public-page-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.public-page-layout__main {
    flex: 1;
    display: block;
}

.skip-link {
    position: absolute;
    top: -100%;
    left: var(--space-md);
    z-index: var(--z-toast);
    padding: var(--space-sm) var(--space-md);
    background: var(--color-accent);
    color: var(--color-text-inverse);
    border-radius: var(--radius-md);
    font-weight: var(--font-semibold);
    transition: top var(--transition-fast) var(--ease-out);
}

.skip-link:focus {
    top: var(--space-md);
}
</style>
