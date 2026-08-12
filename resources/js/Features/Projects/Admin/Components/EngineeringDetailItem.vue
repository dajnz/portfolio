<!-- EngineeringDetailItem (admin-section-components.md:1054-1066, project-form.html) — one engineering-detail row of the project form: the engineering icon picker (required per item), the per-locale title input (max 200) and description textarea, the SortableList drag handle and the remove button. Emits deep `update:modelValue` on any field change and `remove`. -->
<script setup lang="ts">
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

export interface EngineeringDetailRow {
    uid: string;
    id?: number;
    icon: string | null;
    title: Record<string, string>;
    description: Record<string, string>;
}

const props = withDefaults(
    defineProps<{
        modelValue: EngineeringDetailRow;
        locale: string;
        /** Per-field validation errors (`icon`, `title`, `description`)
         *  of THIS row — shown under the matching input (user request
         *  2026-08-12: the missing-translation errors must be visible
         *  on the exact fields). */
        icon_error?: string | null;
        title_error?: string | null;
        description_error?: string | null;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        icon_error: null,
        title_error: null,
        description_error: null,
        disabled: false,
        test_id: 'engineering-detail-item',
    },
);

const emit = defineEmits<{
    'update:modelValue': [row: EngineeringDetailRow];
}>();

const { t } = useI18n();

function setIcon(value: string | null): void {
    emit('update:modelValue', { ...props.modelValue, icon: value });
}

function setTitle(value: string): void {
    emit('update:modelValue', {
        ...props.modelValue,
        title: { ...props.modelValue.title, [props.locale]: value },
    });
}

function setDescription(value: string): void {
    emit('update:modelValue', {
        ...props.modelValue,
        description: { ...props.modelValue.description, [props.locale]: value },
    });
}
</script>

<template>
  <!-- The outer border + the drag knob and the delete button live on the
       list's collapsed/expanded wrapper (user request 2026-08-12 — only
       ONE knob and ONE "x", both in the list's headers). -->
  <div
    class="p-4"
    :data-testid="props.test_id"
  >
    <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
      <AdminIconPicker
        :model-value="props.modelValue.icon"
        icon_set="engineering"
        :label="t('admin.projects.icon')"
        :required="true"
        :error="props.icon_error"
        :test_id="`${props.test_id}-icon`"
        @update:model-value="setIcon"
      />
      <AdminBaseInput
        :model-value="props.modelValue.title[props.locale] ?? ''"
        :label="`${t('common.title')} [${props.locale}]`"
        :required="true"
        :error="props.title_error"
        :maxlength="200"
        :disabled="props.disabled"
        :test_id="`${props.test_id}-title-${props.locale}`"
        @update:model-value="setTitle"
      />
      <div class="sm:col-span-2">
        <AdminBaseTextarea
          :model-value="props.modelValue.description[props.locale] ?? ''"
          :label="`${t('common.description')} [${props.locale}]`"
          :required="true"
          :error="props.description_error"
          :rows="3"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-description-${props.locale}`"
          @update:model-value="setDescription"
        />
      </div>
    </div>
  </div>
</template>
