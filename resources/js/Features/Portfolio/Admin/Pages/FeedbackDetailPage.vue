<!-- FeedbackDetailPage (admin-section-components.md:1184-1211, admin-section-ui.md:410-416, feedback-detail.html) — full feedback message: auto mark-as-read on mount (useAutoMarkRead), Forward (mailto: best-effort + PATCH forwarded), Delete (confirmation + redirect to list). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useAutoMarkRead } from '@shared-cross/Composables/useAutoMarkRead';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { computed, ref } from 'vue';

interface FeedbackItem {
    id: number;
    sender_name: string;
    sender_email: string;
    message: string;
    is_read: boolean;
    is_forwarded: boolean;
    created_at: string;
}

const props = withDefaults(
    defineProps<{
        item?: FeedbackItem;
        test_id?: string;
    }>(),
    {
        item: undefined,
        test_id: 'feedback-detail-page',
    },
);

const { t } = useI18n();
const { menuGroups } = useAdminMenu();
const toast = useToast();
const page = usePage<{ locale?: string }>();
const { patch } = useJsonApi();

const item = props.item;

const isDeleting = ref(false);
const showDeleteDialog = ref(false);
const isForwarding = ref(false);
const forwarded = ref(item?.is_forwarded ?? false);

// Mark-as-read fires automatically on page open; failure shows a toast
// and the badge stays as-is (admin-section-ui.md:119,412). `isRead`
// flips the badge once the JSON PATCH succeeds.
const autoMarkRead = item === undefined ? null : useAutoMarkRead(item.id);

const isRead = computed(() => item?.is_read === true || autoMarkRead?.isRead.value === true);

function senderInitials(): string {
    const name = item?.sender_name ?? '';
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return (item?.sender_email ?? '?').charAt(0).toUpperCase();
    }

    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function formatDate(value: string): string {
    return value.replace('T', ' ').slice(0, 16);
}

function forward(): void {
    // mailto: opens regardless of the PATCH result (best-effort per
    // feedback-detail.html:218-226); a failed PATCH shows a toast.
    window.location.href = `mailto:${encodeURIComponent(item?.sender_email ?? '')}?subject=${encodeURIComponent(`Re: ${t('admin.feedback.from', { email: item?.sender_email ?? '' })}`)}`;

    if (forwarded.value) {
        return;
    }

    isForwarding.value = true;

    // The endpoint returns a plain JSON envelope — the shared JSON API
    // client, not an Inertia visit (same transport as useAutoMarkRead).
    patch(`/admin/feedback/${item?.id ?? 0}/forwarded`)
        .then(() => {
            forwarded.value = true;
        })
        .catch(() => {
            toast.show(t('admin.toasts.forward_failed'), { variant: 'error' });
        })
        .finally(() => {
            isForwarding.value = false;
        });
}

function confirmDelete(): void {
    if (isDeleting.value) {
        return;
    }

    isDeleting.value = true;

    // The controller redirects to the list on success (4.2 deviation —
    // `redirect()->back()` would land on the now-404 detail URL); the
    // server flash renders the success toast.
    router.delete(`/admin/feedback/${item?.id ?? 0}`, {
        onFinish: () => {
            isDeleting.value = false;
            showDeleteDialog.value = false;
        },
    });
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'feedback'"
    :locale_props="{ active_locale: page.props?.locale ?? 'en', supported_locales: [] }"
    :data-testid="`${props.test_id}-layout`"
  >
    <article
      class="mx-auto max-w-3xl"
      :data-testid="props.test_id"
    >
      <AdminBaseButton
        tag="a"
        href="/admin/feedback"
        variant="ghost"
        icon="heroicons:arrow-left"
        icon_position="left"
        :test_id="`${props.test_id}-back-btn`"
      >
        {{ t('admin.feedback.back_to_feedback') }}
      </AdminBaseButton>

      <h1 class="mb-4 mt-4 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.feedback.from', { email: item?.sender_email ?? '' }) }}
      </h1>

      <div class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-600 text-sm font-bold text-white">
          {{ senderInitials() }}
        </span>
        <div class="flex-1">
          <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ item?.sender_name ?? '' }}
          </p>
          <p class="font-mono text-xs text-slate-400 dark:text-slate-500">
            {{ item?.sender_email ?? '' }}
          </p>
        </div>
        <span
          v-if="item"
          class="font-mono text-xs text-slate-400 dark:text-slate-500"
        >
          {{ formatDate(item.created_at) }}
        </span>
        <div class="flex gap-1">
          <AdminBaseBadge
            :variant="isRead ? 'default' : 'danger'"
            :test_id="`${props.test_id}-read-badge`"
          >
            {{ isRead ? t('admin.feedback.read') : t('admin.feedback.unread') }}
          </AdminBaseBadge>
          <AdminBaseBadge
            v-if="forwarded"
            variant="primary"
            :test_id="`${props.test_id}-answered-badge`"
          >
            {{ t('admin.feedback.forwarded') }}
          </AdminBaseBadge>
        </div>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
        <p
          class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-slate-200"
          :data-testid="`${props.test_id}-message`"
        >
          {{ item?.message ?? '' }}
        </p>
      </div>

      <div class="mt-6 flex items-center gap-3">
        <AdminBaseButton
          variant="secondary"
          icon="heroicons:paper-airplane"
          icon_position="left"
          :loading="isForwarding"
          :test_id="`${props.test_id}-forward-btn`"
          @click="forward"
        >
          {{ t('admin.feedback.respond') }}
        </AdminBaseButton>
        <AdminBaseButton
          variant="danger"
          :test_id="`${props.test_id}-delete-btn`"
          @click="showDeleteDialog = true"
        >
          {{ t('common.delete') }}
        </AdminBaseButton>
      </div>

      <ConfirmationDialog
        v-model="showDeleteDialog"
        :title="t('common.confirm_delete')"
        :confirm_label="t('common.delete')"
        :is_loading="isDeleting"
        :test_id="`${props.test_id}-delete-dialog`"
        @confirm="confirmDelete"
      />
    </article>
  </AdminPageLayout>
</template>
