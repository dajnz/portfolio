<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        label: string;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        disabled: false,
        test_id: 'base-switch',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

function onChange(event: Event): void {
    emit('update:modelValue', (event.target as HTMLInputElement).checked);
}
</script>

<template>
  <label
    class="relative inline-flex min-w-0 items-center gap-2 cursor-pointer"
    :class="{ 'opacity-45 cursor-not-allowed': props.disabled }"
  >
    <input
      type="checkbox"
      class="sr-only peer"
      role="switch"
      :checked="props.modelValue"
      :aria-checked="props.modelValue"
      :disabled="props.disabled || undefined"
      :data-testid="props.test_id"
      @change="onChange"
    >
    <span class="relative inline-block h-8 w-14 shrink-0 rounded-full bg-slate-300 transition-colors peer-checked:bg-amber-500 after:absolute after:left-1 after:top-1 after:h-6 after:w-6 after:rounded-full after:bg-white after:shadow-md after:transition-transform peer-checked:after:translate-x-6 dark:bg-slate-600" />
    <span class="min-w-0 truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ props.label }}</span>
  </label>
</template>
