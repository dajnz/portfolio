// admin-section-components.md:31-33 — mirrors PublicBaseTextarea
<script setup lang="ts">
import { computed, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        name?: string;
        label?: string;
        placeholder?: string;
        error?: string | null;
        help?: string;
        required?: boolean;
        disabled?: boolean;
        character_counter?: boolean;
        rows?: number;
        max?: number;
        resizable?: boolean;
        test_id?: string;
    }>(),
    {
        name: undefined,
        label: undefined,
        placeholder: undefined,
        error: null,
        help: undefined,
        required: false,
        disabled: false,
        character_counter: false,
        rows: 4,
        max: undefined,
        resizable: true,
        test_id: 'base-textarea',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [event: FocusEvent];
    focus: [event: FocusEvent];
}>();

const inputId = useId();
const errorId = `${inputId}-error`;
const counterId = `${inputId}-counter`;

const showCounter = computed(() => props.character_counter && props.max !== undefined);
const counterText = computed(() => `${props.modelValue.length}/${props.max}`);

const describedBy = computed(() => {
    const ids: string[] = [];

    if (props.error !== null) {
        ids.push(errorId);
    }

    if (showCounter.value) {
        ids.push(counterId);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
});

function onInput(event: Event): void {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
}
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
    <textarea
      :id="inputId"
      :name="props.name"
      :value="props.modelValue"
      :placeholder="props.placeholder"
      :required="props.required || undefined"
      :disabled="props.disabled || undefined"
      :rows="props.rows"
      :maxlength="props.max"
      class="block w-full min-h-[80px] px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
      :class="{
        'border-danger-300 dark:border-danger-700 focus:border-danger-500 focus:ring-danger-100 dark:focus:ring-danger-900': props.error !== null,
        'bg-slate-50 dark:bg-slate-900 text-slate-400 dark:text-slate-600 cursor-not-allowed': props.disabled,
        'resize-y': props.resizable,
        'resize-none': !props.resizable,
      }"
      :aria-required="props.required || undefined"
      :aria-invalid="props.error !== null || undefined"
      :aria-describedby="describedBy"
      :data-testid="props.test_id"
      @input="onInput"
      @blur="emit('blur', $event)"
      @focus="emit('focus', $event)"
    />
    <p
      v-if="props.error !== null"
      :id="errorId"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
    <div
      v-if="showCounter"
      :id="counterId"
      class="mt-1.5 text-xs text-slate-400 dark:text-slate-500 text-right"
    >
      {{ counterText }}
    </div>
    <p
      v-else-if="props.help"
      class="mt-1.5 text-xs text-slate-400 dark:text-slate-500"
    >
      {{ props.help }}
    </p>
  </div>
</template>
