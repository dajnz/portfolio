<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, useSlots } from 'vue';

import AdminBaseCheckbox from './AdminBaseCheckbox.vue';
import AdminBaseSkeleton from './AdminBaseSkeleton.vue';
import AdminEmptyState from './AdminEmptyState.vue';

type ColumnDefinition = {
    key: string;
    label: string;
    sortable?: boolean;
    width?: string;
    /** Horizontal alignment of the column header + cells — `left` by
     *  default; the blog list centers its Published/Views columns and
     *  the tags list centers its Article Count column. */
    align?: 'left' | 'center' | 'right';
};
type SortDirection = 'asc' | 'desc';
type EmptyStateMode = 'empty' | 'no-results' | 'error';
type RowId = number | string;

const props = withDefaults(
    defineProps<{
        columns: Array<ColumnDefinition>;
        rows?: Array<Record<string, unknown>>;
        sort_column?: string | null;
        sort_direction?: SortDirection | null;
        selectable?: boolean;
        selected_ids: Array<RowId>;
        row_key?: string;
        is_loading?: boolean;
        trashed_ids?: Array<RowId>;
        /** Per-row extra classes (the `unread` feedback row state
         *  overrides the zebra background). Applied after the built-in
         *  state classes. */
        row_class?: (row: Record<string, unknown>) => string | Array<string> | undefined;
        /** Centers the actions column header + row action buttons
         *  (projects list — the single-delete actions read better
         *  centered; other lists keep the right alignment). */
        center_actions?: boolean;
        empty_mode?: EmptyStateMode;
        empty_message?: string;
        empty_action_label?: string | null;
        empty_action_url?: string | null;
        test_id?: string;
    }>(),
    {
        rows: () => [],
        sort_column: null,
        sort_direction: null,
        selectable: false,
        row_key: 'id',
        is_loading: false,
        trashed_ids: () => [],
        row_class: undefined,
        center_actions: false,
        empty_mode: 'empty',
        empty_message: undefined,
        empty_action_label: null,
        empty_action_url: null,
        test_id: 'data-table',
    },
);

const emit = defineEmits<{
    'update:selected_ids': [ids: Array<RowId>];
    'update:sort_column': [column: string | null];
    'update:sort_direction': [direction: SortDirection | null];
    'row-click': [row: Record<string, unknown>];
    'empty-action': [];
}>();

const slots = useSlots();
const { t } = useI18n();

const SKELETON_ROW_COUNT = 4;

const hasRowActions = computed(() => Boolean(slots['row-actions']));
const totalColumnCount = computed(
    () => props.columns.length + (props.selectable ? 1 : 0) + (hasRowActions.value ? 1 : 0),
);

const visibleRowIds = computed(() => props.rows.map((row) => row[props.row_key] as RowId));

const allVisibleSelected = computed(
    () =>
        visibleRowIds.value.length > 0 &&
        visibleRowIds.value.every((id) => props.selected_ids.includes(id)),
);

const someVisibleSelected = computed(() =>
    visibleRowIds.value.some((id) => props.selected_ids.includes(id)),
);

const selectAllModel = computed<boolean | 'indeterminate'>(() => {
    if (allVisibleSelected.value) {
        return true;
    }

    return someVisibleSelected.value ? 'indeterminate' : false;
});

function rowId(row: Record<string, unknown>): RowId {
    return row[props.row_key] as RowId;
}

function onSortClick(column: ColumnDefinition): void {
    if (props.sort_column !== column.key) {
        emit('update:sort_column', column.key);
        emit('update:sort_direction', 'asc');
    } else if (props.sort_direction === 'asc') {
        emit('update:sort_direction', 'desc');
    } else {
        // No unsorted state — a sortable column cycles only asc → desc → asc.
        emit('update:sort_direction', 'asc');
    }
}

function ariaSortFor(column: ColumnDefinition): 'none' | 'ascending' | 'descending' | undefined {
    if (!column.sortable) {
        return undefined;
    }

    if (props.sort_column !== column.key) {
        return 'none';
    }

    return props.sort_direction === 'asc'
        ? 'ascending'
        : props.sort_direction === 'desc'
          ? 'descending'
          : 'none';
}

function columnAlignClass(column: ColumnDefinition): string {
    if (column.align === 'center') {
        return 'text-center';
    }

    if (column.align === 'right') {
        return 'text-right';
    }

    return 'text-left';
}

function onSelectAllChange(checked: boolean): void {
    emit('update:selected_ids', checked ? visibleRowIds.value : []);
}

function onRowSelectChange(row: Record<string, unknown>, checked: boolean): void {
    const id = rowId(row);
    const next = checked
        ? [...props.selected_ids, id]
        : props.selected_ids.filter((item) => item !== id);

    emit('update:selected_ids', next);
}

function onRowClick(row: Record<string, unknown>): void {
    if (props.is_loading) {
        return;
    }

    emit('row-click', row);
}

function extraRowClasses(row: Record<string, unknown>): Array<string> {
    const extra = props.row_class !== undefined ? props.row_class(row) : undefined;

    if (extra === undefined) {
        return [];
    }

    return Array.isArray(extra) ? extra : [extra];
}

function rowClasses(row: Record<string, unknown>): Array<string> {
    const id = rowId(row);
    const isTrashed = props.trashed_ids.includes(id);
    const isSelected = props.selected_ids.includes(id);

    return [
        'odd:bg-surface-secondary dark:odd:bg-surface-dark-secondary',
        'hover:bg-primary-50/30 dark:hover:bg-primary-950/20 transition-colors',
        isTrashed ? 'opacity-50 italic' : '',
        isSelected ? 'bg-primary-50/50 dark:bg-primary-950/30' : '',
        props.is_loading ? '' : 'cursor-pointer',
        ...extraRowClasses(row),
    ];
}

function skeletonWidth(columnIndex: number): string {
    return columnIndex === 0 ? '60%' : '40%';
}

function formatCellValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}
</script>

<template>
  <div
    class="overflow-x-auto border border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark rounded-xl"
    :data-testid="props.test_id"
  >
    <slot
      v-if="props.selectable && props.selected_ids.length > 0 && $slots['bulk-actions']"
      name="bulk-actions"
    />
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
          <th
            v-if="props.selectable"
            class="w-10 px-4 py-3"
          >
            <AdminBaseCheckbox
              :model-value="selectAllModel"
              :test_id="`${props.test_id}-select-all`"
              @update:model-value="onSelectAllChange"
            />
          </th>
          <th
            v-for="column in props.columns"
            :key="column.key"
            class="px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider"
            :class="columnAlignClass(column)"
            :style="column.width ? { width: column.width } : undefined"
            :aria-sort="ariaSortFor(column)"
          >
            <button
              v-if="column.sortable"
              class="inline-flex items-center gap-1 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
              @click="onSortClick(column)"
            >
              {{ column.label }}
              <Icon
                v-if="props.sort_column === column.key && props.sort_direction === 'asc'"
                class="w-3.5 h-3.5"
                icon="heroicons:chevron-up"
                aria-hidden="true"
              />
              <Icon
                v-else-if="props.sort_column === column.key && props.sort_direction === 'desc'"
                class="w-3.5 h-3.5"
                icon="heroicons:chevron-down"
                aria-hidden="true"
              />
            </button>
            <span v-else>
              {{ column.label }}
            </span>
          </th>
          <th
            v-if="hasRowActions"
            class="px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider"
            :class="props.center_actions ? 'text-center' : 'text-right'"
          >
            {{ t('admin.lists.actions') }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <template v-if="props.is_loading">
          <tr
            v-for="rowIndex in SKELETON_ROW_COUNT"
            :key="rowIndex"
            :data-testid="`${props.test_id}-skeleton-row-${rowIndex}`"
          >
            <td
              v-if="props.selectable"
              class="px-4 py-3"
            />
            <td
              v-for="(column, columnIndex) in props.columns"
              :key="column.key"
              class="px-4 py-3"
            >
              <AdminBaseSkeleton
                shape="text"
                :width="skeletonWidth(columnIndex)"
              />
            </td>
            <td
              v-if="hasRowActions"
              class="px-4 py-3"
              :class="props.center_actions ? 'text-center' : 'text-right'"
            >
              <AdminBaseSkeleton
                shape="text"
                width="32px"
              />
            </td>
          </tr>
        </template>
        <template v-else-if="props.rows.length === 0">
          <tr>
            <td
              :colspan="totalColumnCount"
              class="px-4 py-3"
            >
              <AdminEmptyState
                :mode="props.empty_mode"
                :message="props.empty_message"
                :action_label="props.empty_action_label"
                :action_url="props.empty_action_url"
                :test_id="`${props.test_id}-empty`"
                @action="emit('empty-action')"
              />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr
            v-for="row in props.rows"
            :key="String(rowId(row))"
            :class="rowClasses(row)"
            @click="onRowClick(row)"
          >
            <td
              v-if="props.selectable"
              class="px-4 py-3"
              @click.stop
            >
              <AdminBaseCheckbox
                :model-value="props.selected_ids.includes(rowId(row))"
                :test_id="`${props.test_id}-row-${String(rowId(row))}`"
                @update:model-value="(checked) => onRowSelectChange(row, checked)"
              />
            </td>
            <td
              v-for="column in props.columns"
              :key="column.key"
              class="px-4 py-3 text-slate-600 dark:text-slate-300"
              :class="columnAlignClass(column)"
            >
              <slot
                :name="`cell-${column.key}`"
                :row="row"
                :value="row[column.key]"
              >
                {{ formatCellValue(row[column.key]) }}
              </slot>
            </td>
            <td
              v-if="hasRowActions"
              class="px-4 py-3"
              :class="props.center_actions ? 'text-center' : 'text-right'"
            >
              <div
                class="flex items-center gap-1"
                :class="props.center_actions ? 'justify-center' : 'justify-end'"
              >
                <slot
                  name="row-actions"
                  :row="row"
                />
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
