<!-- DashboardPage (admin-section-components.md:672-705, admin-section-ui.md:220-228, dashboard.html) — the admin dashboard: four stat cards (B32) + quick links to content surfaces. Replaces the step-4.1 placeholder (B107). -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed } from 'vue';
import QuickLinks, { type QuickLinkData } from '../Components/QuickLinks.vue';
import StatsCards, { type StatCardData } from '../Components/StatsCards.vue';

type Stats = NonNullable<components['schemas']['DashboardPageProps']['props']>['stats'];

const props = withDefaults(
    defineProps<{
        stats?: Stats;
        test_id?: string;
    }>(),
    {
        stats: undefined,
        test_id: 'dashboard-page',
    },
);

const { t } = useI18n();
const { menuGroups } = useAdminMenu();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();

const stats = computed<Array<StatCardData>>(() => [
    {
        label: t('admin.dashboard.total_views'),
        value: props.stats?.total_views ?? 0,
        isLoading: false,
    },
    {
        label: t('admin.dashboard.blog_views'),
        value: props.stats?.blog_views ?? 0,
        isLoading: false,
    },
    {
        label: t('admin.dashboard.projects'),
        value: props.stats?.projects_count ?? 0,
        isLoading: false,
    },
    {
        label: t('admin.dashboard.articles'),
        value: props.stats?.articles_count ?? 0,
        isLoading: false,
    },
]);

const links = computed<Array<QuickLinkData>>(() => [
    {
        label: t('admin.projects.add'),
        url: '/admin/projects/create',
        icon: 'heroicons:plus',
        variant: 'primary',
    },
    {
        label: t('admin.blog.add'),
        url: '/admin/blog/create',
        icon: 'heroicons:plus',
        variant: 'secondary',
    },
    {
        label: t('admin.dashboard.unread_feedback'),
        url: '/admin/feedback',
        icon: 'heroicons:chat-bubble-left-right',
        variant: 'secondary',
    },
    {
        label: t('admin.backups.backup_now'),
        url: '/admin/backups',
        icon: 'heroicons:archive-box',
        variant: 'secondary',
    },
]);
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'dashboard'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-6xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
        {{ t('admin.dashboard.overview') }}
      </h1>

      <StatsCards
        :stats="stats"
        :columns="4"
        :test_id="`${props.test_id}-stats`"
      />

      <div class="mt-6">
        <QuickLinks
          :links="links"
          :test_id="`${props.test_id}-quick-links`"
        />
      </div>
    </section>
  </AdminPageLayout>
</template>
