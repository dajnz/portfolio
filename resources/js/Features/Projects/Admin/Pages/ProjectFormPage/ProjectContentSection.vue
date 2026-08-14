<!-- ProjectContentSection — the Content card of the project form:
     locale-tabbed title/brief textarea + the three TextEditor Markdown
     fields. Receives the per-locale value maps and the active locale;
     emits field updates for that locale only. -->
<script setup lang="ts">
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import TextEditor, { PROJECT_EDITOR_BUTTONS } from '@shared-admin/TextEditor/TextEditor.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        title: Record<string, string>;
        brief_description: Record<string, string>;
        full_description: Record<string, string>;
        problem: Record<string, string>;
        solution: Record<string, string>;
        locale: string;
        errors: Record<string, string>;
        disabled: boolean;
        test_id?: string;
    }>(),
    {
        test_id: 'project-content-section',
    },
);

const emit = defineEmits<{
    'update:title': [locale: string, value: string];
    'update:brief_description': [locale: string, value: string];
    'update:full_description': [locale: string, value: string];
    'update:problem': [locale: string, value: string];
    'update:solution': [locale: string, value: string];
}>();

const { t } = useI18n();

function fieldError(field: string): string | null {
    return props.errors[`${field}.${props.locale}`] ?? props.errors[field] ?? null;
}
</script>

<template>
  <AdminBaseCard :test_id="`${props.test_id}-section-content`">
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.projects.content') }}
    </h2>
    <div class="space-y-4">
      <AdminBaseInput
        :model-value="props.title[props.locale] ?? ''"
        :label="t('common.title')"
        :required="true"
        :error="fieldError('title')"
        :disabled="props.disabled"
        :maxlength="200"
        :test_id="`${props.test_id}-title-${props.locale}`"
        @update:model-value="(value) => emit('update:title', props.locale, value)"
      />
      <AdminBaseTextarea
        :model-value="props.brief_description[props.locale] ?? ''"
        :label="t('admin.projects.brief_description')"
        :required="true"
        :error="fieldError('brief_description')"
        :disabled="props.disabled"
        :maxlength="300"
        :rows="3"
        :test_id="`${props.test_id}-brief-${props.locale}`"
        @update:model-value="(value) => emit('update:brief_description', props.locale, value)"
      />
      <TextEditor
        :model-value="props.full_description[props.locale] ?? ''"
        :label="t('admin.projects.full_description')"
        :error="fieldError('full_description')"
        :disabled="props.disabled"
        :buttons="PROJECT_EDITOR_BUTTONS"
        :test_id="`${props.test_id}-full-description-${props.locale}`"
        @update:model-value="(value) => emit('update:full_description', props.locale, value)"
      />
      <TextEditor
        :model-value="props.problem[props.locale] ?? ''"
        :label="t('admin.projects.problem')"
        :error="fieldError('problem')"
        :disabled="props.disabled"
        :buttons="PROJECT_EDITOR_BUTTONS"
        :test_id="`${props.test_id}-problem-${props.locale}`"
        @update:model-value="(value) => emit('update:problem', props.locale, value)"
      />
      <TextEditor
        :model-value="props.solution[props.locale] ?? ''"
        :label="t('admin.projects.solution')"
        :error="fieldError('solution')"
        :disabled="props.disabled"
        :buttons="PROJECT_EDITOR_BUTTONS"
        :test_id="`${props.test_id}-solution-${props.locale}`"
        @update:model-value="(value) => emit('update:solution', props.locale, value)"
      />
    </div>
  </AdminBaseCard>
</template>
