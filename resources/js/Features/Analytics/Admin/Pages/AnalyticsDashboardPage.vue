<!-- AnalyticsDashboardPage (admin-section-components.md:1295-1334, admin-section-ui.md:441-452, mockup analytics.html) — the admin analytics dashboard: tabs matching the report types (Overview/Blog/Projects/Feedback), stat cards, hand-drawn SVG charts (AnalyticsChart — textContent-only data per analytics.md:200) and DataTables for top articles/projects. "Generate Now" (A9/B284) dispatches report generation for the in-progress period via POST /admin/analytics/generate-reports; the job runs async and the dashboard reloads (the 202 → success toast + reload mirrors "the UI reloads on completion", openapi/inertia/analytics.yaml). The chart component is code-split (defineAsyncComponent — 13.3 "dashboard charts async"). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import DataTable from '@shared-admin/DataTable.vue';
import StatsCards from '@shared-admin/StatsCards.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import type { components } from '@shared-cross/Types/inertia';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { defineAsyncComponent, ref } from 'vue';
import { useAnalytics } from '../Composables/useAnalytics';

const AnalyticsChart = defineAsyncComponent(() => import('@analytics-admin/AnalyticsChart.vue'));

type Reports = NonNullable<
    components['schemas']['AnalyticsDashboardPageProps']['props']
>['reports'];
type TabId = 'overall' | 'blog' | 'projects' | 'feedback';

const props = withDefaults(
    defineProps<{
        reports?: Reports;
        test_id?: string;
    }>(),
    {
        reports: () => [],
        test_id: 'analytics-dashboard-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();
const { post } = useJsonApi();

const {
    reportFor,
    overviewStats,
    visitsSeries,
    blogStats,
    topArticlesTable,
    reactionsSeries,
    projectViewsTable,
    submissionsSeries,
} = useAnalytics(props);

const TABS: Array<{ id: TabId; label_key: string }> = [
    { id: 'overall', label_key: 'admin.analytics.overview' },
    { id: 'blog', label_key: 'admin.analytics.blog' },
    { id: 'projects', label_key: 'admin.analytics.projects' },
    { id: 'feedback', label_key: 'admin.analytics.feedback' },
];

const activeTab = ref<TabId>('overall');
const isGenerating = ref(false);

function generateNow(): void {
    if (isGenerating.value) {
        return;
    }

    isGenerating.value = true;

    post<{ dispatched: boolean }>('/admin/analytics/generate-reports')
        .then(() => {
            toast.show(t('admin.toasts.generate_reports_started'));
            router.reload();
        })
        .catch(() => {
            toast.show(t('admin.toasts.generate_reports_failed'), { variant: 'error' });
        })
        .finally(() => {
            isGenerating.value = false;
        });
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/analytics'"
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
      <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          {{ t('admin.analytics.title') }}
        </h1>
        <AdminBaseButton
          variant="primary"
          icon="heroicons:arrow-path"
          icon_position="left"
          :loading="isGenerating"
          :test_id="`${props.test_id}-generate-now`"
          @click="generateNow"
        >
          {{ t('admin.analytics.generate_now') }}
        </AdminBaseButton>
      </div>

      <div
        class="mb-6 flex border-b border-slate-200 dark:border-slate-700"
        :data-testid="`${props.test_id}-tabs`"
      >
        <AdminBaseButton
          v-for="tab in TABS"
          :key="tab.id"
          :variant="'ghost'"
          :type="'button'"
          :class="
            activeTab === tab.id
              ? '-mb-px rounded-none border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
              : 'rounded-none'
          "
          :test_id="`${props.test_id}-tab-${tab.id}`"
          @click="activeTab = tab.id"
        >
          {{ t(tab.label_key) }}
        </AdminBaseButton>
      </div>

      <template v-if="activeTab === 'overall'">
        <template v-if="reportFor('overall')">
          <StatsCards
            :stats="overviewStats"
            :columns="4"
            :test_id="`${props.test_id}-stats`"
          />
          <AnalyticsChart
            type="bar"
            :data="visitsSeries"
            :height="300"
            :is_loading="false"
            :title="t('admin.analytics.visits_over_time')"
            :test_id="`${props.test_id}-visits-chart`"
          />
        </template>
        <AdminEmptyState
          v-else
          mode="empty"
          :message="t('admin.analytics.no_data')"
          :test_id="`${props.test_id}-empty`"
        />
      </template>

      <template v-else-if="activeTab === 'blog'">
        <template v-if="reportFor('blog')">
          <DataTable
            :columns="topArticlesTable.columns"
            :rows="topArticlesTable.rows"
            :selected_ids="[]"
            row_key="id"
            :empty_message="t('admin.analytics.no_data')"
            :test_id="`${props.test_id}-top-articles`"
          />
          <StatsCards
            :stats="blogStats"
            :columns="4"
            :test_id="`${props.test_id}-blog-stats`"
          />
          <AnalyticsChart
            type="bar"
            :data="reactionsSeries"
            :height="300"
            :is_loading="false"
            :title="t('admin.analytics.reactions')"
            :test_id="`${props.test_id}-reactions-chart`"
          />
        </template>
        <AdminEmptyState
          v-else
          mode="empty"
          :message="t('admin.analytics.no_data')"
          :test_id="`${props.test_id}-empty`"
        />
      </template>

      <template v-else-if="activeTab === 'projects'">
        <template v-if="reportFor('projects')">
          <DataTable
            :columns="projectViewsTable.columns"
            :rows="projectViewsTable.rows"
            :selected_ids="[]"
            row_key="id"
            :empty_message="t('admin.analytics.no_data')"
            :test_id="`${props.test_id}-project-views`"
          />
        </template>
        <AdminEmptyState
          v-else
          mode="empty"
          :message="t('admin.analytics.no_data')"
          :test_id="`${props.test_id}-empty`"
        />
      </template>

      <template v-else>
        <template v-if="reportFor('feedback')">
          <AnalyticsChart
            type="line"
            :data="submissionsSeries"
            :height="300"
            :is_loading="false"
            :title="t('admin.analytics.submissions_over_time')"
            :test_id="`${props.test_id}-submissions-chart`"
          />
        </template>
        <AdminEmptyState
          v-else
          mode="empty"
          :message="t('admin.analytics.no_data')"
          :test_id="`${props.test_id}-empty`"
        />
      </template>
    </section>
  </AdminPageLayout>
</template>
