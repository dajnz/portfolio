<!-- RankingsTable — wrapper over the shared DataTable for the top/bottom/liked/disliked 10 lists: a variant dropdown above the table, skeleton loading, an empty state and an error state with a Retry button (the error and empty states reuse DataTable's `empty_mode` machinery). The `row_key` prop is a DataTable detail the wrapper must surface — the page rankings rows carry `id` while the time-on-page rankings carry `page_url`. -->
<script setup lang="ts">
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import DataTable from '@shared-admin/DataTable.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { AnalyticsVariant, RichText } from '../Composables/useAnalytics';
import AnalyticsRichValue from './AnalyticsRichValue.vue';

interface ColumnDefinition {
    key: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        columns: Array<ColumnDefinition>;
        rows: Array<Record<string, unknown>>;
        variant: AnalyticsVariant;
        variant_options: Array<{ value: string; label: string }>;
        is_loading?: boolean;
        error?: boolean;
        row_key?: string;
        test_id?: string;
    }>(),
    {
        is_loading: false,
        error: false,
        row_key: 'id',
        test_id: 'rankings-table',
    },
);

const emit = defineEmits<{
    'update:variant': [variant: AnalyticsVariant];
    retry: [];
}>();

const { t } = useI18n();

function onVariantChange(value: string | string[]): void {
    emit('update:variant', value as AnalyticsVariant);
}

// The cell value is `unknown` — narrowed via the helper instead of an
// inline cast (`|` in a template expression is Vue filter syntax).
function toRichValue(value: unknown): number | string | RichText {
    return value as number | string | RichText;
}
</script>

<template>
  <div
    class="mt-6"
    :data-testid="props.test_id"
  >
    <div class="mb-3 flex items-center justify-end">
      <AdminBaseDropdown
        :model-value="props.variant"
        :options="props.variant_options"
        :disabled="props.is_loading"
        :test_id="`${props.test_id}-variant`"
        @update:model-value="onVariantChange"
      />
    </div>
    <DataTable
      :columns="props.columns"
      :rows="props.rows"
      :selected_ids="[]"
      :row_key="props.row_key"
      :is_loading="props.is_loading"
      :empty_mode="props.error ? 'error' : 'empty'"
      :empty_message="props.error ? t('admin.analytics.could_not_load_rankings') : t('admin.analytics.no_data')"
      :empty_action_label="props.error ? t('common.retry') : null"
      :test_id="`${props.test_id}-table`"
      @empty-action="emit('retry')"
    >
      <!-- The duration columns (avg/min/max) render the bold-number +
           dull-unit segments; the other cells render their plain values. -->
      <template
        v-for="columnKey in ['avg', 'min', 'max']"
        :key="columnKey"
        #[`cell-${columnKey}`]="{ value }"
      >
        <AnalyticsRichValue :parts="toRichValue(value)" />
      </template>
    </DataTable>
  </div>
</template>
