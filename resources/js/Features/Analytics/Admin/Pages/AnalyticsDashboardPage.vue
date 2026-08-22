<!-- AnalyticsDashboardPage (admin-section-components.md:1295-1352, admin-section-ui.md:441-452, mockup analytics.html) — the admin analytics dashboard root: the header ("Generate Now"), the three tabs, the clickable whole-time metric blocks per tab and the empty state. All the selection/fetch/view-model wiring lives in `useAnalyticsDashboardDetail` (chart/rankings detail semantics) and `useAnalytics` (fetch state); the detail section renders through the `AnalyticsDetailArea` sub-component (the span/variant dropdowns, the chart and the rankings table). "Generate Now" (A9/B284) dispatches report generation via POST /admin/analytics/generate-reports; the job runs async and the dashboard reloads (202 → toast + reload, existing). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import type { components } from '@shared-cross/Types/inertia';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { ref } from 'vue';

import AnalyticsDetailArea from '../Components/AnalyticsDetailArea.vue';
import AnalyticsMetricBlocks from '../Components/AnalyticsMetricBlocks.vue';
import { useAnalytics } from '../Composables/useAnalytics';
import { useAnalyticsDashboardDetail } from '../Composables/useAnalyticsDashboardDetail';

type DashboardPageProps = NonNullable<
    components['schemas']['AnalyticsDashboardPageProps']['props']
>;

const props = withDefaults(
    defineProps<{
        overview_blocks: DashboardPageProps['overview_blocks'];
        blog_blocks: DashboardPageProps['blog_blocks'];
        projects_blocks: DashboardPageProps['projects_blocks'];
        default_chart: DashboardPageProps['default_chart'];
        default_rankings: DashboardPageProps['default_rankings'];
        test_id?: string;
    }>(),
    {
        test_id: 'analytics-dashboard-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();
const { post } = useJsonApi();

const analytics = useAnalytics(props);
const detail = useAnalyticsDashboardDetail(analytics, props);

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
          v-for="tab in detail.tabs"
          :key="tab"
          :variant="'ghost'"
          :type="'button'"
          :class="
            detail.activeTab.value === tab
              ? '-mb-px rounded-none border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
              : 'rounded-none'
          "
          :test_id="`${props.test_id}-tab-${tab}`"
          @click="detail.selectTab(tab)"
        >
          {{ t(`admin.analytics.${tab}`) }}
        </AdminBaseButton>
      </div>

      <div
        v-for="tab in detail.tabs"
        :key="tab"
      >
        <template v-if="detail.activeTab.value === tab">
          <p
            v-if="tab !== 'projects'"
            class="mb-4 text-sm text-slate-500 dark:text-slate-400"
            :data-testid="`${props.test_id}-note`"
          >
            {{ t('admin.analytics.whole_time_note') }}
          </p>

          <template v-if="detail.activeBlocks.value.length > 0">
            <AnalyticsMetricBlocks
              :blocks="detail.activeBlocks.value"
              :test_id="`${props.test_id}-blocks`"
              @select="detail.onBlockSelect"
            />

            <AnalyticsDetailArea
              :detail="detail"
              :test_id="props.test_id"
            />
          </template>

          <AdminEmptyState
            v-else
            mode="empty"
            :message="t('admin.analytics.no_data')"
            :test_id="`${props.test_id}-empty`"
          />
        </template>
      </div>
    </section>
  </AdminPageLayout>
</template>
