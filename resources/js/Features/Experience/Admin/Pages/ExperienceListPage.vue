<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import DataTable from '@shared-admin/DataTable.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import {
    type Cursor,
    type CursorNavigationPayload,
    cursorNavigationParams,
} from '@shared-cross/Utils/cursorNavigationParams';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, nextTick, ref, watch } from 'vue';

interface TimelineItemRow {
    id: number;
    position: string;
    company_name: string;
    started_at: string;
    ended_at: string | null;
    is_published: boolean;
    include_in_resume: boolean;
}

interface ListMeta {
    total: number;
    per_page: number;
    current_page: number;
    sort_column: string;
    sort_direction: 'asc' | 'desc';
    cursors: { first: Cursor; last: Cursor; page: number };
}

interface DeleteTarget {
    id: number;
    kind: 'soft' | 'force';
}

const props = withDefaults(
    defineProps<{
        items?: TimelineItemRow[];
        meta?: ListMeta;
        test_id?: string;
    }>(),
    {
        items: () => [],
        meta: undefined,
        test_id: 'experience-list-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const { patch } = useJsonApi();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

const SEARCH_DEBOUNCE_MS = 400;

// Filter state — initialized from the URL query (SSR-safe; the admin list
// filters travel as query params) and kept alive across Inertia visits via
// preserveState.
function initialQueryParam(name: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return new URLSearchParams(window.location.search).get(name) ?? '';
}

const searchInput = ref(initialQueryParam('q'));
const showTrashed = ref(initialQueryParam('trashed') === '1');
const searchTerm = ref(searchInput.value);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const sortColumn = ref(props.meta?.sort_column ?? 'id');
const sortDirection = ref<'asc' | 'desc'>(props.meta?.sort_direction ?? 'desc');

const deleteTarget = ref<DeleteTarget | null>(null);
const isDeleting = ref(false);

// The DataTable emits `update:sort_column` + `update:sort_direction` in
// one click when switching columns — merge them into a single navigation.
let sortNavQueued = false;

const hasActiveFilters = computed(() => searchTerm.value !== '' || showTrashed.value);

// Local working copy — the publish toggle updates it optimistically and
// reverts on server errors; Inertia prop refreshes re-seed it.
const localItems = ref<TimelineItemRow[]>(props.items.map((item) => ({ ...item })));

watch(
    () => props.items,
    (next) => {
        localItems.value = next.map((item) => ({ ...item }));
    },
);

const rows = computed<Array<Record<string, unknown>>>(() =>
    localItems.value.map((item) => ({ ...item })),
);

// Trashed rows are styled muted/italic (the trashed view lists only
// soft-deleted items). No selection checkboxes — the list has no bulk
// operations.
const trashedIds = computed<Array<number | string>>(() =>
    showTrashed.value ? localItems.value.map((item) => item.id) : [],
);

const columns = computed(() => [
    { key: 'position', label: t('admin.experience.position') },
    { key: 'company_name', label: t('admin.experience.company') },
    { key: 'started_at', label: t('admin.experience.dates'), sortable: true },
    { key: 'is_published', label: t('admin.experience.published'), width: '112px' },
    { key: 'include_in_resume', label: t('admin.experience.include_in_resume') },
]);

function currentParams(
    extra: Record<string, string | number> = {},
): Record<string, string | number> {
    const params: Record<string, string | number> = {};

    if (searchTerm.value !== '') {
        params.q = searchTerm.value;
    }

    if (showTrashed.value) {
        params.trashed = '1';
    }

    if (sortColumn.value !== 'id') {
        params.sort_column = sortColumn.value;
        params.sort_direction = sortDirection.value;
    }

    return { ...params, ...extra };
}

function applyFilters(): void {
    router.get('/admin/experience', currentParams(), {
        only: ['items', 'meta'],
        preserveState: true,
        preserveScroll: true,
    });
}

function onSearchInput(value: string): void {
    searchInput.value = value;

    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }

    searchTimeout = setTimeout(() => {
        searchTerm.value = searchInput.value.trim();
        applyFilters();
    }, SEARCH_DEBOUNCE_MS);
}

function onTrashedChange(checked: boolean): void {
    showTrashed.value = checked;
    searchTerm.value = searchInput.value.trim();
    applyFilters();
}

function queueSortNavigation(): void {
    if (sortNavQueued) {
        return;
    }

    sortNavQueued = true;
    void nextTick(() => {
        sortNavQueued = false;
        applyFilters();
    });
}

function onSortColumn(column: string | null): void {
    sortColumn.value = column ?? 'id';
    sortDirection.value = 'asc';
    queueSortNavigation();
}

function onSortDirection(direction: 'asc' | 'desc' | null): void {
    if (direction === null) {
        sortColumn.value = 'id';
        sortDirection.value = 'desc';
    } else {
        sortDirection.value = direction;
    }
    queueSortNavigation();
}

function navigate(payload: CursorNavigationPayload): void {
    router.get(
        '/admin/experience',
        currentParams(cursorNavigationParams(payload, props.meta?.cursors)),
        {
            only: ['items', 'meta'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function openCreate(): void {
    router.visit('/admin/experience/create');
}

// Row click switches to the edit form. Trashed rows are NOT editable (the
// backend's edit lookup excludes soft-deleted items — a trashed row click
// would 404), so the navigation only applies to the live view; the actions
// controls stop their own clicks from bubbling into the row handler.
function openEdit(row: Record<string, unknown>): void {
    if (showTrashed.value) {
        return;
    }

    router.visit(`/admin/experience/${Number(row.id)}/edit`);
}

function clearFilters(): void {
    searchInput.value = '';
    searchTerm.value = '';
    showTrashed.value = false;
    applyFilters();
}

function requestDelete(id: number, kind: DeleteTarget['kind']): void {
    deleteTarget.value = { id, kind };
}

function dismissDelete(): void {
    deleteTarget.value = null;
}

function confirmDelete(): void {
    const target = deleteTarget.value;

    if (target === null || isDeleting.value) {
        return;
    }

    isDeleting.value = true;

    const url =
        target.kind === 'force'
            ? `/admin/experience/${target.id}/force`
            : `/admin/experience/${target.id}`;

    router.delete(url, {
        onSuccess: () => {
            isDeleting.value = false;
            deleteTarget.value = null;
        },
        onError: () => {
            isDeleting.value = false;
            deleteTarget.value = null;
            toast.show(t('admin.experience.delete_failed'), { variant: 'error' });
        },
    });
}

function restoreItem(id: number): void {
    router.patch(`/admin/experience/${id}/restore`, undefined, {
        onError: () => {
            toast.show(t('admin.experience.delete_failed'), { variant: 'error' });
        },
    });
}

function replaceRow(updated: TimelineItemRow): void {
    const index = localItems.value.findIndex((item) => item.id === updated.id);

    if (index !== -1) {
        localItems.value[index] = { ...updated };
    }
}

async function togglePublication(row: Record<string, unknown>): Promise<void> {
    const item = row as unknown as TimelineItemRow;
    const target = !item.is_published;
    const previous = { ...item };

    localItems.value = localItems.value.map((entry) =>
        entry.id === item.id ? { ...entry, is_published: target } : entry,
    );

    try {
        const response = await patch<{ data: TimelineItemRow }>(
            `/admin/experience/${item.id}/${target ? 'publish' : 'unpublish'}`,
        );
        replaceRow(response.data);
    } catch {
        replaceRow(previous);
        toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
    }
}

function formatDate(iso: string): string {
    const [year, month, day] = iso.split('-');

    if (year === undefined || month === undefined || day === undefined) {
        return iso;
    }

    return `${day}.${month}.${year}`;
}

// Full dates (dd.mm.yyyy) — the year-month form is for the public timeline
// cards only.
function formatDates(row: TimelineItemRow): string {
    const started = formatDate(row.started_at);
    const ended = row.ended_at === null ? t('experience.present') : formatDate(row.ended_at);

    return `${started} — ${ended}`;
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/experience'"
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
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.experience.title') }}
      </h1>

      <!-- Sticky filter/action bar: the search, the Show trashed toggle
           and the Add Experience button share ONE line — no wrapping
           between the search and its checkbox. -->
      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <div
          class="flex flex-1 flex-wrap items-center gap-3"
          :data-testid="`${props.test_id}-filters`"
        >
          <div class="relative w-72 max-w-full">
            <AdminBaseInput
              :model-value="searchInput"
              type="search"
              :placeholder="t('admin.experience.search_placeholder')"
              :test_id="`${props.test_id}-search`"
              @update:model-value="onSearchInput"
            />
          </div>
          <AdminBaseCheckbox
            :model-value="showTrashed"
            :label="t('admin.lists.show_trashed')"
            :test_id="`${props.test_id}-trashed`"
            @update:model-value="onTrashedChange"
          />
        </div>
        <AdminBaseButton
          variant="primary"
          icon="heroicons:plus"
          icon_position="left"
          :test_id="`${props.test_id}-add`"
          @click="openCreate"
        >
          {{ t('admin.experience.add') }}
        </AdminBaseButton>
      </AdminStickyActionBar>

      <DataTable
        :columns="columns"
        :rows="rows"
        :sort_column="sortColumn"
        :sort_direction="sortDirection"
        :selected_ids="[]"
        :trashed_ids="trashedIds"
        :empty_mode="hasActiveFilters ? 'no-results' : 'empty'"
        :empty_message="
          hasActiveFilters ? t('admin.experience.no_results') : t('admin.experience.no_items')
        "
        :empty_action_label="hasActiveFilters ? t('common.clear_filters') : t('admin.experience.add')"
        :test_id="`${props.test_id}-table`"
        @update:sort_column="onSortColumn"
        @update:sort_direction="onSortDirection"
        @empty-action="hasActiveFilters ? clearFilters() : openCreate()"
        @row-click="openEdit"
      >
        <template #cell-position="{ row }">
          <span class="font-semibold text-slate-900 dark:text-slate-100">
            {{ row.position }}
          </span>
        </template>
        <template #cell-company_name="{ row }">
          <span class="text-slate-600 dark:text-slate-300">
            {{ row.company_name }}
          </span>
        </template>
        <template #cell-started_at="{ row }">
          <span
            class="font-mono text-xs text-slate-500 dark:text-slate-400"
            :data-testid="`${props.test_id}-dates-${String(row.id)}`"
          >
            {{ formatDates(row as unknown as TimelineItemRow) }}
          </span>
        </template>
        <template #cell-is_published="{ row }">
          <div @click.stop>
            <AdminBaseBadge
              v-if="showTrashed"
              variant="default"
              size="sm"
              :test_id="`${props.test_id}-badge-trashed-${String(row.id)}`"
            >
              {{ t('admin.experience.trashed') }}
            </AdminBaseBadge>
            <!-- The published cell is an active control (the projects /
                 blog convention): a state-styled button whose label is
                 the action — Publish on drafts, Unpublish on live items. -->
            <AdminBaseButton
              v-else
              type="button"
              variant="secondary"
              size="sm"
              :class="
                row.is_published === true
                  ? 'bg-success-600! text-white! border-success-600! hover:bg-success-700! dark:border-success-600!'
                  : 'border-primary-600! text-primary-600! hover:bg-primary-600! hover:text-white!'
              "
              :aria-pressed="row.is_published === true"
              :test_id="`${props.test_id}-publish-toggle-${String(row.id)}`"
              @click="togglePublication(row)"
            >
              {{
                row.is_published === true
                  ? t('admin.lists.unpublish')
                  : t('admin.lists.publish')
              }}
            </AdminBaseButton>
          </div>
        </template>
        <template #cell-include_in_resume="{ row }">
          <AdminBaseBadge
            v-if="row.include_in_resume === true"
            variant="primary"
            icon="heroicons:check"
            size="sm"
            :test_id="`${props.test_id}-resume-${String(row.id)}`"
          >
            {{ t('admin.experience.include_in_resume') }}
          </AdminBaseBadge>
          <AdminBaseBadge
            v-else
            variant="default"
            size="sm"
            :test_id="`${props.test_id}-not-resume-${String(row.id)}`"
          >
            {{ t('admin.experience.not_in_resume') }}
          </AdminBaseBadge>
        </template>
        <template #row-actions="{ row }">
          <!-- @click.stop — the actions must not bubble into the row's
               edit-navigation click handler. -->
          <div
            class="flex items-center justify-end gap-1"
            @click.stop
          >
            <template v-if="showTrashed">
              <AdminBaseButton
                type="button"
                variant="icon-outline-danger"
                icon="heroicons:arrow-path"
                :aria-label="t('admin.lists.restore')"
                :test_id="`${props.test_id}-restore-${String(row.id)}`"
                @click="restoreItem(Number(row.id))"
              />
              <AdminBaseButton
                type="button"
                variant="icon-outline-danger"
                icon="heroicons:trash"
                :aria-label="t('admin.lists.delete_permanently')"
                :test_id="`${props.test_id}-force-delete-${String(row.id)}`"
                @click="requestDelete(Number(row.id), 'force')"
              />
            </template>
            <AdminBaseButton
              v-else
              type="button"
              variant="icon-outline-danger"
              icon="heroicons:trash"
              :aria-label="t('common.delete')"
              :test_id="`${props.test_id}-delete-${String(row.id)}`"
              @click="requestDelete(Number(row.id), 'soft')"
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
        :title="
          deleteTarget?.kind === 'force'
            ? t('admin.experience.force_delete_confirm')
            : t('admin.experience.delete_confirm')
        "
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
