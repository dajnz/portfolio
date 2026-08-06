<!-- LocaleTabs (admin-section-components.md:521-535) — locale switcher for translatable forms. -->
<script setup lang="ts">
interface LocaleOption {
    code: string;
    native_name: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        locales?: LocaleOption[];
        test_id?: string;
    }>(),
    {
        locales: () => [],
        test_id: 'locale-tabs',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

function select(code: string): void {
    if (code !== props.modelValue) {
        emit('update:modelValue', code);
    }
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
      :class="[
        index === 0 ? 'rounded-l-lg' : '',
        index === props.locales.length - 1 ? 'rounded-r-lg' : '',
        locale.code === props.modelValue
          ? 'bg-primary-600 text-white border-primary-600'
          : 'bg-white dark:bg-surface-dark-secondary border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800',
      ]"
      class="px-4 py-2 text-sm font-semibold border transition-colors"
      :data-testid="`${props.test_id}-tab-${locale.code}`"
      @click="select(locale.code)"
    >
      {{ locale.native_name }}
    </button>
  </div>
</template>
