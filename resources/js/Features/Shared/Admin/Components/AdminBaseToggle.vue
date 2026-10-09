<!-- A compact primary-colored on/off toggle for per-row states, distinct
     from the large amber AdminBaseSwitch used for maintenance mode. -->
<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        label?: string;
        aria_label?: string;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        label: undefined,
        aria_label: undefined,
        disabled: false,
        test_id: 'base-toggle',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

function onChange(event: Event): void {
    emit('update:modelValue', (event.target as HTMLInputElement).checked);
}
</script>

<template>
  <label
    class="relative inline-flex cursor-pointer items-center gap-2"
    :class="{ 'opacity-45 cursor-not-allowed': props.disabled }"
  >
    <input
      type="checkbox"
      class="sr-only peer"
      role="switch"
      :checked="props.modelValue"
      :aria-checked="props.modelValue"
      :aria-label="props.label ?? props.aria_label"
      :disabled="props.disabled || undefined"
      :data-testid="props.test_id"
      @change="onChange"
    >
    <div
      class="relative inline-block h-6 w-11 rounded-full bg-slate-200 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform after:content-[''] peer-checked:bg-primary-600 peer-checked:after:translate-x-full dark:bg-slate-700"
    />
    <span
      v-if="props.label"
      class="text-sm font-medium text-slate-700 dark:text-slate-200"
    >
      {{ props.label }}
    </span>
  </label>
</template>
