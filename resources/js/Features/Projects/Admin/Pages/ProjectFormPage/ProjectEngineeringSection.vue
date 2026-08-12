<!-- ProjectEngineeringSection — the Engineering Details card of the
     project form: a thin wrapper around EngineeringDetailsList that
     owns the add/remove row mutations (uid + per-locale maps) and
     emits the updated rows array. -->
<script setup lang="ts">
import type { EngineeringDetailRow } from '@projects-admin/EngineeringDetailItem.vue';
import EngineeringDetailsList from '@projects-admin/EngineeringDetailsList.vue';
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { randomUuid } from '@shared-cross/Utils/randomUuid';

const props = withDefaults(
    defineProps<{
        detail_items: EngineeringDetailRow[];
        locale: string;
        errors: Record<string, string>;
        disabled: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'project-engineering-section',
    },
);

const emit = defineEmits<{
    'update:detail_items': [rows: EngineeringDetailRow[]];
}>();

function emptyLocaleMap(): Record<string, string> {
    return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
}

function addRow(): void {
    emit('update:detail_items', [
        ...props.detail_items,
        {
            uid: randomUuid(),
            icon: null,
            title: emptyLocaleMap(),
            description: emptyLocaleMap(),
        },
    ]);
}

function removeRow(index: number): void {
    emit(
        'update:detail_items',
        props.detail_items.filter((_, rowIndex) => rowIndex !== index),
    );
}
</script>

<template>
  <AdminBaseCard :test_id="`${props.test_id}-section-engineering`">
    <!-- The list's own sticky header carries the section heading
         + Expand all / Collapse all (screenshots pattern). -->
    <EngineeringDetailsList
      :model-value="props.detail_items"
      :locale="props.locale"
      :error="props.errors.detail_items ?? null"
      :errors="props.errors"
      :disabled="props.disabled"
      :test_id="`${props.test_id}-engineering-details`"
      @update:model-value="(rows) => emit('update:detail_items', rows)"
      @add="addRow"
      @remove="removeRow"
    />
  </AdminBaseCard>
</template>
