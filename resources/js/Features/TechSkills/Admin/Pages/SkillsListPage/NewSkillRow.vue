<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { NewSkillDraft, ProficiencyOption } from './types';

const props = withDefaults(
    defineProps<{
        group_id: number;
        draft: NewSkillDraft;
        errors?: Record<string, string>;
        saving?: boolean;
        proficiency_options?: ProficiencyOption[];
        test_id?: string;
    }>(),
    {
        errors: () => ({}),
        saving: false,
        proficiency_options: () => [],
        test_id: 'skills-list-page',
    },
);

const emit = defineEmits<{
    update: [field: string, value: unknown];
    save: [];
    cancel: [];
}>();

const { t } = useI18n();
</script>

<template>
  <div
    class="flex items-start gap-2.5 border-t border-slate-100 bg-slate-50/60 px-4 py-3 sm:items-center dark:border-slate-700 dark:bg-slate-800/30"
    :data-testid="`${props.test_id}-new-skill-${props.group_id}`"
  >
    <span
      class="hidden shrink-0 cursor-grab select-none justify-center text-sm text-slate-300 sm:flex sm:w-14 dark:text-slate-600"
      aria-hidden="true"
    >⠿</span>

    <div class="flex min-w-0 flex-1 flex-col gap-2.5 sm:flex-row sm:items-center">
      <span class="w-full shrink-0 sm:w-28">
        <AdminIconPicker
          :model-value="props.draft.icon"
          icon_set="brands"
          :placeholder="t('admin.icon_picker.select_placeholder')"
          :test_id="`${props.test_id}-new-skill-${props.group_id}-icon`"
          @update:model-value="(value) => emit('update', 'icon', value)"
        />
      </span>
      <AdminBaseInput
        :model-value="props.draft.name"
        :error="props.errors.name ?? null"
        :disabled="props.saving"
        :maxlength="100"
        :placeholder="t('admin.skills.skill_name_placeholder')"
        class="min-w-0 flex-1 sm:min-w-64"
        :test_id="`${props.test_id}-new-skill-${props.group_id}-name`"
        @update:model-value="(value) => emit('update', 'name', value)"
        @keydown.enter="emit('save')"
        @keydown.escape="emit('cancel')"
      />
      <span class="w-full shrink-0 sm:w-28">
        <AdminBaseDropdown
          :model-value="String(props.draft.proficiency)"
          :options="props.proficiency_options"
          :aria-label="t('admin.skills.proficiency')"
          :test_id="`${props.test_id}-new-skill-${props.group_id}-proficiency`"
          @update:model-value="(value) => emit('update', 'proficiency', Number(value))"
        />
      </span>
      <span class="mx-0 flex shrink-0 items-center sm:mx-6">
        <AdminBaseCheckbox
          :model-value="props.draft.includeInResume"
          :label="t('admin.skills.resume')"
          :test_id="`${props.test_id}-new-skill-${props.group_id}-resume`"
          @update:model-value="(value) => emit('update', 'includeInResume', Boolean(value))"
        />
      </span>
      <span class="mx-0 flex shrink-0 items-center gap-2.5 sm:mx-6">
        <AdminBaseButton
          type="button"
          variant="icon-only"
          icon="heroicons:check"
          :aria-label="t('common.save')"
          :disabled="props.saving"
          :test_id="`${props.test_id}-new-skill-${props.group_id}-save`"
          @click="emit('save')"
        />
        <AdminBaseButton
          type="button"
          variant="icon-only"
          icon="heroicons:x-mark"
          :aria-label="t('common.cancel')"
          :disabled="props.saving"
          :test_id="`${props.test_id}-new-skill-${props.group_id}-cancel`"
          @click="emit('cancel')"
        />
      </span>
    </div>
  </div>
</template>
