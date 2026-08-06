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
  <div class="form-group">
    <label
      v-if="props.label"
      class="form-group__label"
      :class="{ 'form-group__label--required': props.required }"
      :for="inputId"
    >
      {{ props.label }}
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
      class="form-group__input form-group__textarea"
      :class="{
        'is-error': props.error !== null,
        'is-disabled': props.disabled,
        'form-group__textarea--fixed': !props.resizable,
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
      class="form-group__error"
      role="alert"
    >
      {{ props.error }}
    </p>
    <div
      v-if="showCounter"
      :id="counterId"
      class="form-group__counter"
    >
      {{ counterText }}
    </div>
    <p
      v-else-if="props.help"
      class="form-group__help"
    >
      {{ props.help }}
    </p>
  </div>
</template>

<style scoped>
.form-group {
    margin-bottom: var(--space-lg);
    max-width: 400px;
}

.form-group__label {
    display: block;
    font-size: var(--font-size-sm);
    font-weight: var(--font-semibold);
    color: var(--color-text-primary);
    margin-bottom: var(--space-xs);
}

.form-group__label--required::after {
    content: ' *';
    color: var(--color-error);
}

.form-group__input {
    display: block;
    width: 100%;
    padding: var(--input-padding-y) var(--input-padding-x);
    font-size: var(--input-font-size);
    font-family: var(--font-sans);
    color: var(--color-text-primary);
    background: var(--color-surface);
    border: var(--input-border-width) solid var(--color-border);
    border-radius: var(--input-radius);
    transition: var(--input-transition);
}

.form-group__input::placeholder {
    color: var(--color-text-tertiary);
}

.form-group__input:hover {
    border-color: var(--color-border-strong);
}

.form-group__input:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgb(30 122 91 / 18%);
    outline: none;
}

.form-group__input.is-error {
    border-color: var(--color-error-border);
}

.form-group__input.is-error:focus {
    box-shadow: 0 0 0 3px rgb(184 74 62 / 18%);
}

.form-group__input.is-disabled {
    opacity: 0.45;
    pointer-events: none;
    cursor: not-allowed;
    background: var(--color-bg-secondary);
}

.form-group__textarea {
    min-height: 100px;
    resize: vertical;
    height: auto;
    line-height: var(--line-height-normal);
}

.form-group__textarea--fixed {
    resize: none;
}

.form-group__error {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    margin-top: var(--space-xs);
    font-size: var(--font-size-sm);
    color: var(--color-error);
    font-weight: var(--font-medium);
}

.form-group__help {
    margin-top: var(--space-xs);
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.form-group__counter {
    margin-top: var(--space-xs);
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
    text-align: right;
}
</style>
