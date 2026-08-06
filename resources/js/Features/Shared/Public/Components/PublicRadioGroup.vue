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
    class="form-group"
    :class="`form-group--${props.direction}`"
    :data-testid="props.test_id"
  >
    <legend
      v-if="props.label"
      class="form-group__label"
      :class="{ 'form-group__label--required': props.required }"
    >
      {{ props.label }}
    </legend>
    <label
      v-for="option in props.options"
      :key="option.value"
      class="form-group__radio"
      :class="{ 'is-disabled': props.disabled }"
    >
      <input
        type="radio"
        :name="groupName"
        :value="option.value"
        :checked="isChecked(option)"
        :required="props.required || undefined"
        :disabled="props.disabled || undefined"
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
      class="form-group__error"
      role="alert"
    >
      {{ props.error }}
    </p>
  </fieldset>
</template>

<style scoped>
.form-group {
    margin-bottom: var(--space-lg);
    max-width: 400px;
    border: 0;
    padding: 0;
}

.form-group--horizontal {
    display: flex;
    align-items: center;
    gap: var(--space-xl);
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

.form-group__radio {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    cursor: pointer;
    font-size: var(--font-size-base);
    color: var(--color-text-primary);
}

.form-group__radio input {
    width: 1.125rem;
    height: 1.125rem;
    accent-color: var(--color-accent);
}

.form-group__radio.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
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
</style>
