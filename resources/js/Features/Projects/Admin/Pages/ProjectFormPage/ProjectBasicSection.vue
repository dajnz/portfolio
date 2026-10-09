<script setup lang="ts">
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        slug: string;
        slug_error?: string | null;
        disabled: boolean;
        test_id?: string;
    }>(),
    {
        slug_error: null,
        test_id: 'project-basic-section',
    },
);

const emit = defineEmits<{
    'update:slug': [value: string];
}>();

const { t } = useI18n();
</script>

<template>
  <AdminBaseCard :test_id="`${props.test_id}-section-basic`">
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.projects.basic') }}
    </h2>
    <AdminBaseInput
      :model-value="props.slug"
      :label="t('common.slug')"
      :required="true"
      :error="props.slug_error"
      :help="t('admin.projects.slug_hint')"
      :disabled="props.disabled"
      :maxlength="200"
      class="font-mono"
      :test_id="`${props.test_id}-slug`"
      @update:model-value="(value) => emit('update:slug', value)"
    />
  </AdminBaseCard>
</template>
