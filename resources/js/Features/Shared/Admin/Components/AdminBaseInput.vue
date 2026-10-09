<script setup lang="ts">
import { computed, ref, useId } from 'vue';

type InputType = 'text' | 'email' | 'password' | 'search' | 'url' | 'number' | 'date';
type InputMode = 'text' | 'numeric' | 'email' | 'tel';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        type?: InputType;
        inputmode?: InputMode;
        name?: string;
        label?: string;
        placeholder?: string;
        error?: string | null;
        help?: string;
        required?: boolean;
        disabled?: boolean;
        autocomplete?: string;
        maxlength?: number;
        min?: string | number;
        max?: string | number;
        step?: string | number;
        test_id?: string;
    }>(),
    {
        type: 'text',
        inputmode: undefined,
        name: undefined,
        label: undefined,
        placeholder: undefined,
        error: null,
        help: undefined,
        required: false,
        disabled: false,
        autocomplete: undefined,
        maxlength: undefined,
        min: undefined,
        max: undefined,
        step: undefined,
        test_id: 'base-input',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [event: FocusEvent];
    focus: [event: FocusEvent];
}>();

const inputId = useId();
const errorId = `${inputId}-error`;
const inputEl = ref<HTMLInputElement | null>(null);

const describedBy = computed(() => {
    const ids: string[] = [];

    if (props.error !== null) {
        ids.push(errorId);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
});

function onInput(event: Event): void {
    emit('update:modelValue', (event.target as HTMLInputElement).value);
}

// Exposed so auth pages can auto-focus the single-field forms.
defineExpose({
    focus: (): void => {
        inputEl.value?.focus();
    },
});
</script>

<template>
  <div>
    <label
      v-if="props.label"
      class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-slate-200"
      :for="inputId"
    >
      {{ props.label }}
      <span
        v-if="props.required"
        class="text-danger-500"
      >*</span>
    </label>
    <input
      :id="inputId"
      ref="inputEl"
      :name="props.name"
      :type="props.type"
      :inputmode="props.inputmode"
      :value="props.modelValue"
      :placeholder="props.placeholder"
      :required="props.required || undefined"
      :disabled="props.disabled || undefined"
      :autocomplete="props.autocomplete"
      :maxlength="props.maxlength"
      :min="props.min"
      :max="props.max"
      :step="props.step"
      class="block w-full h-10 px-3 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
      :class="{
        'border-danger-300 dark:border-danger-700 focus:border-danger-500 focus:ring-danger-100 dark:focus:ring-danger-900': props.error !== null,
        'bg-slate-50 dark:bg-slate-900 text-slate-400 dark:text-slate-600 cursor-not-allowed': props.disabled,
      }"
      :aria-required="props.required || undefined"
      :aria-invalid="props.error !== null || undefined"
      :aria-describedby="describedBy"
      :data-testid="props.test_id"
      @input="onInput"
      @blur="emit('blur', $event)"
      @focus="emit('focus', $event)"
    >
    <p
      v-if="props.error !== null"
      :id="errorId"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
    <p
      v-else-if="props.help"
      class="mt-1.5 text-xs text-slate-400 dark:text-slate-500"
    >
      {{ props.help }}
    </p>
  </div>
</template>
