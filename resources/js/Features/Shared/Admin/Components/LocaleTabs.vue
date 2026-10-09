<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';

interface LocaleOption {
    code: string;
    native_name: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        locales?: LocaleOption[];
        /** Locales whose required translations are incomplete — their tab
         *  gets a warning marker. */
        warned_locales?: string[];
        test_id?: string;
    }>(),
    {
        locales: () => [],
        warned_locales: () => [],
        test_id: 'locale-tabs',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();

function select(code: string): void {
    if (code !== props.modelValue) {
        emit('update:modelValue', code);
    }
}

function isWarned(locale: LocaleOption): boolean {
    return props.warned_locales.includes(locale.code);
}

function tabAriaLabel(locale: LocaleOption): string {
    return isWarned(locale)
        ? t('admin.toasts.translation_incomplete', { locale: locale.native_name })
        : locale.native_name;
}
</script>

<template>
  <div
    class="flex gap-0"
    role="tablist"
    aria-label="Locales"
    :data-testid="props.test_id"
  >
    <button
      v-for="(locale, index) in props.locales"
      :key="locale.code"
      type="button"
      role="tab"
      :aria-selected="locale.code === props.modelValue"
      :aria-label="tabAriaLabel(locale)"
      :class="[
        index === 0 ? 'rounded-l-lg' : '',
        index === props.locales.length - 1 ? 'rounded-r-lg' : '',
        locale.code === props.modelValue
          ? 'bg-primary-600 text-white border-primary-600'
          : 'bg-white dark:bg-surface-dark-secondary border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800',
        isWarned(locale) && locale.code !== props.modelValue
          ? 'border-warning-500 text-warning-700 dark:text-warning-400'
          : '',
      ]"
      class="px-4 py-2 text-sm font-semibold border transition-colors"
      :data-testid="`${props.test_id}-tab-${locale.code}`"
      @click="select(locale.code)"
    >
      {{ locale.native_name }}
      <span
        v-if="isWarned(locale)"
        class="ml-1 inline-block h-2 w-2 rounded-full bg-warning-500 align-middle"
        aria-hidden="true"
        :data-testid="`${props.test_id}-warn-${locale.code}`"
      />
    </button>
  </div>
</template>
