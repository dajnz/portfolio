<!-- FeedbackListPage (admin-section-components.md:1184-1211, admin-section-ui.md:404-408, feedback.html) — feedback submissions DataTable (Date / Sender Email / Excerpt / Status), row click navigates to the detail page; cursor pagination; read-only list (no create CTA). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import DataTable from '@shared-admin/DataTable.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import {
    type Cursor,
    type CursorNavigationPayload,
    cursorNavigationParams,
} from '@shared-cross/Utils/cursorNavigationParams';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed } from 'vue';

interface FeedbackItem {
    id: number;
    sender_name: string;
    sender_email: string;
    message: string;
    is_read: boolean;
    is_forwarded: boolean;
    created_at: string;
}

interface ListMeta {
    total: number;
    per_page: number;
    current_page: number;
    sort_column: string;
    sort_direction: 'asc' | 'desc';
    cursors: { first: Cursor; last: Cursor; page: number };
}

const EXCERPT_LENGTH = 120;

const props = withDefaults(
    defineProps<{
        feedback?: FeedbackItem[];
        meta?: ListMeta;
        test_id?: string;
    }>(),
    {
        feedback: () => [],
        meta: undefined,
        test_id: 'feedback-list-page',
    },
);

const { t } = useI18n();
const { menuGroups } = useAdminMenu();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();

const rows = computed<Array<Record<string, unknown>>>(() =>
    props.feedback.map((item) => ({ ...item })),
);

const columns = computed(() => [
    { key: 'created_at', label: t('admin.feedback.date'), width: '180px' },
    { key: 'sender_email', label: t('admin.feedback.sender_email') },
    { key: 'excerpt', label: t('admin.feedback.excerpt') },
    { key: 'status', label: t('admin.feedback.status') },
    { key: 'actions', label: t('admin.lists.actions') },
]);

function excerptOf(message: string): string {
    return message.length > EXCERPT_LENGTH ? `${message.slice(0, EXCERPT_LENGTH)}…` : message;
}

function formatDate(value: string): string {
    return value.replace('T', ' ').slice(0, 16);
}

function openRow(row: Record<string, unknown>): void {
    router.visit(`/admin/feedback/${Number(row.id)}`);
}

function navigate(payload: CursorNavigationPayload): void {
    router.get('/admin/feedback', cursorNavigationParams(payload, props.meta?.cursors), {
        only: ['feedback', 'meta'],
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/feedback'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-5xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.feedback.title') }}
      </h1>

      <DataTable
        :columns="columns"
        :rows="rows"
        :selected_ids="[]"
        :empty_mode="'empty'"
        :empty_message="t('admin.feedback.no_feedback')"
        :empty_action_label="null"
        :row_class="(row) =>
          row.is_read === false
            ? 'bg-primary-50/40 dark:bg-primary-950/20 hover:bg-primary-50/70 dark:hover:bg-primary-950/40'
            : ''
        "
        :test_id="`${props.test_id}-table`"
        @row-click="openRow"
      >
        <template #cell-created_at="{ row }">
          <span
            class="font-mono text-xs"
            :class="row.is_read === false ? 'font-semibold text-slate-700 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400'"
          >
            {{ formatDate(String(row.created_at ?? '')) }}
          </span>
        </template>
        <template #cell-sender_email="{ row }">
          <span class="font-semibold text-slate-900 dark:text-slate-100">
            {{ row.sender_email }}
          </span>
        </template>
        <template #cell-excerpt="{ row }">
          <span class="max-w-xs block truncate text-slate-600 dark:text-slate-300">
            {{ excerptOf(String(row.message ?? '')) }}
          </span>
        </template>
        <template #cell-status="{ row }">
          <div class="flex flex-wrap gap-1">
            <AdminBaseBadge
              :variant="row.is_read === false ? 'danger' : 'default'"
              :test_id="`${props.test_id}-read-badge-${String(row.id)}`"
            >
              {{ row.is_read === false ? t('admin.feedback.unread') : t('admin.feedback.read') }}
            </AdminBaseBadge>
            <AdminBaseBadge
              v-if="row.is_forwarded === true"
              variant="primary"
              :test_id="`${props.test_id}-answered-badge-${String(row.id)}`"
            >
              {{ t('admin.feedback.forwarded') }}
            </AdminBaseBadge>
          </div>
        </template>
        <template #cell-actions="{ row }">
          <span
            class="flex items-center justify-end text-slate-300 dark:text-slate-600"
            aria-hidden="true"
            :data-testid="`${props.test_id}-row-chevron-${String(row.id)}`"
          >
            ›
          </span>
        </template>
      </DataTable>

      <PaginationControls
        v-if="props.meta !== undefined"
        :total="props.meta.total"
        :per_page="props.meta.per_page"
        :current_page="props.meta.current_page"
        :cursors="props.meta.cursors"
        :sort_column="props.meta.sort_column"
        :sort_direction="props.meta.sort_direction"
        :test_id="`${props.test_id}-pagination`"
        @navigate="navigate"
      />
    </section>
  </AdminPageLayout>
</template>
