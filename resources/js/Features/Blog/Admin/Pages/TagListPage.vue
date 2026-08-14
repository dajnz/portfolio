<!-- TagListPage (admin-section-ui.md:315-319, admin-section-components.md:925-953, tags.html) — the cursor-paginated article-tags management list: search by name across all locales (server-side), the DataTable (Name / Slug / Article Count / Actions — no selection, no bulk endpoints per routes.md), PaginationControls and the delete confirmation that warns when the tag is in use. -->
<script setup lang="ts">
import type { AdminListMeta, TagTableRow } from '@blog/Admin/Types/article';
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import DataTable from '@shared-admin/DataTable.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import {
    type CursorNavigationPayload,
    cursorNavigationParams,
} from '@shared-cross/Utils/cursorNavigationParams';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        tags?: TagTableRow[];
        meta?: AdminListMeta;
        test_id?: string;
    }>(),
    {
        tags: () => [],
        meta: undefined,
        test_id: 'tag-list-page',
    },
);

const { t } = useI18n();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

const searchInput = ref('');
const searchTerm = ref('');
const isDeleting = ref(false);
const deleteTarget = ref<number | null>(null);

const tableRows = computed<Array<Record<string, unknown>>>(() =>
    props.tags.map((tag) => ({ ...tag })),
);

const columns = computed(() => [
    { key: 'name', label: t('admin.tags.name') },
    { key: 'slug', label: t('admin.tags.slug') },
    {
        key: 'articles_use_count',
        label: t('admin.tags.article_count'),
        width: '140px',
        align: 'center' as const,
    },
]);

const hasSearch = computed(() => searchTerm.value !== '');

const deleteWarning = computed(() => {
    if (deleteTarget.value === null) {
        return '';
    }

    const tag = props.tags.find((item) => item.id === deleteTarget.value);

    return tag !== undefined && tag.articles_use_count > 0
        ? t('admin.tags.articles_use_count', { count: tag.articles_use_count })
        : '';
});

const deleteDialogTitle = computed(
    () =>
        t('admin.tags.delete_confirm') +
        (deleteWarning.value !== '' ? ` ${deleteWarning.value}` : ''),
);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(() => {
    // The `/` shortcut focus is handled by useAdminShortcuts — this
    // search is a plain debounced input filter.
    searchInput.value = initialQueryParam('q');
    searchTerm.value = searchInput.value;
});

function initialQueryParam(name: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return new URLSearchParams(window.location.search).get(name) ?? '';
}

function onSearchInput(value: string): void {
    searchInput.value = value;

    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        searchTerm.value = value;
        applySearch();
    }, 300);
}

function applySearch(): void {
    const params: Record<string, string | number> = {};

    if (searchTerm.value !== '') {
        params.q = searchTerm.value;
    }

    router.get('/admin/tags', params, {
        only: ['tags', 'meta'],
        preserveState: true,
        preserveScroll: true,
    });
}

function navigate(payload: CursorNavigationPayload): void {
    const params: Record<string, string | number> = {};

    if (searchTerm.value !== '') {
        params.q = searchTerm.value;
    }

    Object.assign(params, cursorNavigationParams(payload, props.meta?.cursors));

    router.get('/admin/tags', params, {
        only: ['tags', 'meta'],
        preserveState: true,
        preserveScroll: true,
    });
}

function openCreate(): void {
    router.visit('/admin/tags/create');
}

function openEdit(row: Record<string, unknown>): void {
    router.visit(`/admin/tags/${Number(row.id)}/edit`);
}

function requestDelete(row: Record<string, unknown>): void {
    deleteTarget.value = Number(row.id);
}

function dismissDelete(): void {
    deleteTarget.value = null;
}

function confirmDelete(): void {
    if (deleteTarget.value === null || isDeleting.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(`/admin/tags/${deleteTarget.value}`, {
        onFinish: () => {
            isDeleting.value = false;
            deleteTarget.value = null;
        },
        onError: () => {
            isDeleting.value = false;
            deleteTarget.value = null;
        },
    });
}

function clearSearch(): void {
    searchInput.value = '';
    searchTerm.value = '';
    applySearch();
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/tags'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-4xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-4 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.tags.title') }}
      </h1>

      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <div class="flex w-full items-center gap-3">
          <!-- The search takes the whole available width so the New Tag
               button sits right-aligned (user request 2026-08-13). -->
          <div class="min-w-0 flex-1">
            <AdminBaseInput
              :model-value="searchInput"
              type="search"
              :placeholder="t('admin.tags.search_placeholder')"
              :test_id="`${props.test_id}-search`"
              @update:model-value="onSearchInput"
            />
          </div>
          <AdminBaseButton
            variant="primary"
            icon="heroicons:plus"
            icon_position="left"
            :test_id="`${props.test_id}-add`"
            @click="openCreate"
          >
            {{ t('admin.tags.add') }}
          </AdminBaseButton>
        </div>
      </AdminStickyActionBar>

      <DataTable
        :columns="columns"
        :rows="tableRows"
        :sort_column="'id'"
        :sort_direction="'desc'"
        :selected_ids="[]"
        :center_actions="true"
        :empty_mode="hasSearch ? 'no-results' : 'empty'"
        :empty_message="hasSearch ? t('admin.tags.no_tags_match') : t('admin.tags.no_tags')"
        :empty_action_label="hasSearch ? t('common.clear_filters') : t('admin.tags.add')"
        :test_id="`${props.test_id}-table`"
        @empty-action="hasSearch ? clearSearch() : openCreate()"
        @row-click="openEdit"
      >
        <template #cell-name="{ row }">
          <span class="font-semibold text-slate-900 dark:text-slate-100">
            {{ row.name }}
          </span>
        </template>
        <template #cell-slug="{ row }">
          <span class="font-mono text-slate-500 dark:text-slate-400">
            {{ row.slug }}
          </span>
        </template>
        <template #cell-articles_use_count="{ row }">
          <span class="font-mono text-slate-500 dark:text-slate-400">
            {{ row.articles_use_count }}
          </span>
        </template>
        <template #row-actions="{ row }">
          <!-- @click.stop — the delete must not bubble into the row's
               edit-navigation click handler (no Edit button — the row
               click opens the form, user request 2026-08-13). -->
          <div
            class="flex items-center gap-1"
            @click.stop
          >
            <AdminBaseButton
              type="button"
              variant="icon-outline-danger"
              icon="heroicons:trash"
              :aria-label="t('common.delete')"
              :test_id="`${props.test_id}-delete-${String(row.id)}`"
              @click="requestDelete(row)"
            />
          </div>
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

      <ConfirmationDialog
        :model-value="deleteTarget !== null"
        :title="deleteDialogTitle"
        :confirm_label="t('common.delete')"
        :is_loading="isDeleting"
        :test_id="`${props.test_id}-delete-dialog`"
        @update:model-value="(open) => {
          if (!open) dismissDelete();
        }"
        @confirm="confirmDelete"
      />
    </section>
  </AdminPageLayout>
</template>
