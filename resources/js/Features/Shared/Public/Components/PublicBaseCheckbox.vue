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

// Indeterminate is a visual state only — the emitted value is always the
// checked boolean. Clicking an indeterminate box unchecks it (native
// checkbox behavior).
const isIndeterminate = computed(() => props.modelValue === 'indeterminate');
const isChecked = computed(() => props.modelValue === true);

function onChange(event: Event): void {
    // Indeterminate is a visual state only — clicking it unchecks.
    const wasIndeterminate = isIndeterminate.value;
    const checked = (event.target as HTMLInputElement).checked;

    emit('update:modelValue', wasIndeterminate ? false : checked);
}
</script>

<template>
  <label
    class="form-group__checkbox"
    :for="inputId"
  >
    <input
      :id="inputId"
      :name="props.name"
      type="checkbox"
      :checked="isChecked"
      :indeterminate.prop="isIndeterminate"
      :disabled="props.disabled || undefined"
      :data-testid="props.test_id"
      @change="onChange"
    >
    <slot>
      {{ props.label }}
    </slot>
  </label>
</template>

<style scoped>
.form-group__checkbox {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    cursor: pointer;
    font-size: var(--font-size-base);
    color: var(--color-text-primary);
}

.form-group__checkbox input {
    width: 1.125rem;
    height: 1.125rem;
    accent-color: var(--color-accent);
}

.form-group__checkbox input:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
</style>
