<script setup lang="ts">
import { computed, useId } from 'vue';

type CheckboxValue = boolean | 'indeterminate';

const props = withDefaults(
    defineProps<{
        modelValue: CheckboxValue;
        name?: string;
        label?: string;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        name: undefined,
        label: undefined,
        disabled: false,
        test_id: 'base-checkbox',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

const inputId = useId();

const isIndeterminate = computed(() => props.modelValue === 'indeterminate');
const isChecked = computed(() => props.modelValue === true);

function onChange(event: Event): void {
    const wasIndeterminate = isIndeterminate.value;
    const checked = (event.target as HTMLInputElement).checked;

    emit('update:modelValue', wasIndeterminate ? false : checked);
}
</script>

<template>
  <label
    class="flex items-center gap-2 text-sm font-medium cursor-pointer text-slate-700 dark:text-slate-200"
    :class="{ 'opacity-45 cursor-not-allowed': props.disabled }"
    :for="inputId"
  >
    <input
      :id="inputId"
      :name="props.name"
      type="checkbox"
      :checked="isChecked"
      :indeterminate.prop="isIndeterminate"
      :disabled="props.disabled || undefined"
      class="w-4 h-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500 cursor-pointer"
      :data-testid="props.test_id"
      @change="onChange"
    >
    <slot>
      {{ props.label }}
    </slot>
  </label>
</template>
