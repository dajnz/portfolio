// admin-section-components.md:61-63 — mirrors PublicRadioGroup
<script setup lang="ts">
import { computed, useId } from 'vue';

interface RadioOption {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string;
        name?: string;
        label?: string;
        options?: RadioOption[];
        error?: string | null;
        required?: boolean;
        disabled?: boolean;
        direction?: 'vertical' | 'horizontal';
        test_id?: string;
    }>(),
    {
        name: undefined,
        label: undefined,
        options: () => [],
        error: null,
        required: false,
        disabled: false,
        direction: 'vertical',
        test_id: 'radio-group',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const generatedName = useId();
const groupName = computed(() => props.name ?? `radio-group-${generatedName}`);

function isChecked(option: RadioOption): boolean {
    return props.modelValue === option.value;
}

function onChange(option: RadioOption): void {
    emit('update:modelValue', option.value);
}
</script>

<template>
  <fieldset
    class="border-0 p-0"
    :class="props.direction === 'horizontal' ? 'flex items-center gap-6' : 'flex flex-col gap-3'"
    :data-testid="props.test_id"
  >
    <legend
      v-if="props.label"
      class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-slate-200"
    >
      {{ props.label }}
      <span
        v-if="props.required"
        class="text-danger-500"
      >*</span>
    </legend>
    <label
      v-for="option in props.options"
      :key="option.value"
      class="flex items-center gap-2 text-sm font-medium cursor-pointer text-slate-700 dark:text-slate-200"
      :class="{ 'opacity-45 cursor-not-allowed': props.disabled }"
    >
      <input
        type="radio"
        :name="groupName"
        :value="option.value"
        :checked="isChecked(option)"
        :required="props.required || undefined"
        :disabled="props.disabled || undefined"
        class="w-4 h-4 border-slate-300 text-primary-600 focus:ring-primary-500"
        :data-testid="`${props.test_id}-${option.value}`"
        @change="onChange(option)"
      >
      <slot
        name="option"
        :option="option"
        :checked="isChecked(option)"
      >
        {{ option.label }}
      </slot>
    </label>
    <p
      v-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
  </fieldset>
</template>
