<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { LocaleOption } from '@techskills-admin/SkillGroupCard.vue';

const props = withDefaults(
    defineProps<{
        locale: string;
        names: Record<string, string>;
        icon: string | null;
        error_name?: string | null;
        warned_locales?: string[];
        locales?: LocaleOption[];
        saving?: boolean;
        test_id?: string;
    }>(),
    {
        error_name: null,
        warned_locales: () => [],
        locales: () => [],
        saving: false,
        test_id: 'skills-list-page',
    },
);

const emit = defineEmits<{
    'update:locale': [locale: string];
    'update:name': [locale: string, value: string];
    'update:icon': [value: string | null];
    save: [];
    cancel: [];
}>();

const { t } = useI18n();
</script>

<template>
  <div
    class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="`${props.test_id}-new-group`"
  >
    <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-[auto_minmax(0,1fr)_auto]">
      <div class="flex flex-col gap-1.5">
        <label class="h-5 text-xs font-semibold text-slate-500 dark:text-slate-400">
          {{ t('admin.skills.icon') }}
        </label>
        <AdminIconPicker
          :model-value="props.icon"
          icon_set="technical"
          :placeholder="t('admin.icon_picker.select_placeholder')"
          :test_id="`${props.test_id}-new-group-icon`"
          @update:model-value="(value) => emit('update:icon', value)"
        />
        <div class="hidden h-9 sm:block" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="h-5 text-xs font-semibold text-slate-500 dark:text-slate-400">
          {{ t('admin.skills.group_name') }}
        </label>
        <AdminBaseInput
          :model-value="props.names[props.locale] ?? ''"
          :error="props.error_name"
          :disabled="props.saving"
          :maxlength="100"
          :test_id="`${props.test_id}-new-group-name-${props.locale}`"
          @update:model-value="
            (value) => emit('update:name', props.locale, value)
          "
          @keydown.enter="emit('save')"
          @keydown.escape="emit('cancel')"
        />
        <LocaleTabs
          :model-value="props.locale"
          :locales="props.locales"
          :warned_locales="props.warned_locales"
          :test_id="`${props.test_id}-new-group-tabs`"
          @update:model-value="(value) => emit('update:locale', value)"
        />
      </div>
      <div class="flex flex-col gap-1.5">
        <div class="hidden h-5 sm:block" />
        <div class="flex items-center gap-2">
          <AdminBaseButton
            type="button"
            variant="icon-only"
            icon="heroicons:check"
            :aria-label="t('common.save')"
            :disabled="props.saving"
            :test_id="`${props.test_id}-new-group-save`"
            @click="emit('save')"
          />
          <AdminBaseButton
            type="button"
            variant="icon-only"
            icon="heroicons:x-mark"
            :aria-label="t('common.cancel')"
            :disabled="props.saving"
            :test_id="`${props.test_id}-new-group-cancel`"
            @click="emit('cancel')"
          />
        </div>
        <div class="hidden h-9 sm:block" />
      </div>
    </div>
  </div>
</template>
