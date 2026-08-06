<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import PublicBaseSkeleton from './PublicBaseSkeleton.vue';

interface DropdownOption {
    value: string;
    label: string;
    disabled?: boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | string[];
        options?: DropdownOption[];
        multiple?: boolean;
        searchable?: boolean;
        loading?: boolean;
        placeholder?: string;
        label?: string;
        error?: string | null;
        required?: boolean;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        options: () => [],
        multiple: false,
        searchable: false,
        loading: false,
        placeholder: undefined,
        label: undefined,
        error: null,
        required: false,
        disabled: false,
        test_id: 'base-dropdown',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | string[]];
    search: [query: string];
}>();

const { t } = useI18n();

const SEARCH_DEBOUNCE_MS = 300;

const isOpen = ref(false);
const highlighted = ref(-1);
const query = ref('');
const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const controlEl = ref<HTMLElement | null>(null);
const triggerEl = ref<HTMLButtonElement | null>(null);
const searchEl = ref<HTMLInputElement | null>(null);

const listboxId = useId();

const selectedValues = computed(() =>
    props.multiple ? (props.modelValue as string[]) : ([props.modelValue] as string[]),
);

const selectedOptions = computed(() =>
    props.options.filter((option) => selectedValues.value.includes(option.value)),
);

const triggerLabel = computed(() => {
    if (selectedOptions.value.length === 0) {
        return props.placeholder ?? '';
    }

    return selectedOptions.value.map((option) => option.label).join(', ');
});

const filteredOptions = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(needle));
});

function firstEnabledIndex(): number {
    return filteredOptions.value.findIndex((option) => !option.disabled);
}

function isSelected(option: DropdownOption): boolean {
    return selectedValues.value.includes(option.value);
}

function selectOption(option: DropdownOption): void {
    if (option.disabled) {
        return;
    }

    if (props.multiple) {
        const next = selectedValues.value.includes(option.value)
            ? selectedValues.value.filter((value) => value !== option.value)
            : [...selectedValues.value, option.value];
        emit('update:modelValue', next);
    } else {
        emit('update:modelValue', option.value);
        close();
    }
}

function open(): void {
    if (props.disabled) {
        return;
    }

    isOpen.value = true;
    highlighted.value = firstEnabledIndex();

    if (props.searchable) {
        nextTick(() => searchEl.value?.focus());
    }
}

function close(): void {
    isOpen.value = false;
    highlighted.value = -1;
}

function toggleOpen(): void {
    if (isOpen.value) {
        close();
        return;
    }

    open();
}

function moveHighlight(direction: 1 | -1): void {
    const enabled = filteredOptions.value
        .map((option, index) => (option.disabled ? -1 : index))
        .filter((index) => index !== -1);

    if (enabled.length === 0) {
        highlighted.value = -1;
        return;
    }

    const currentPos = enabled.indexOf(highlighted.value);
    const nextPos = (currentPos + direction + enabled.length) % enabled.length;
    highlighted.value = enabled[nextPos];
}

function selectHighlighted(): void {
    const option = filteredOptions.value[highlighted.value];

    if (option !== undefined) {
        selectOption(option);
    }
}

function onControlKeydown(event: KeyboardEvent): void {
    if (!isOpen.value) {
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            moveHighlight(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            moveHighlight(-1);
            break;
        case 'Enter':
            event.preventDefault();
            selectHighlighted();
            break;
        case 'Escape':
            event.preventDefault();
            close();
            triggerEl.value?.focus();
            break;
        case 'Tab':
            if (highlighted.value !== -1) {
                selectHighlighted();
            }
            close();
            break;
    }
}

function onQueryInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;

    if (searchTimeout.value !== null) {
        clearTimeout(searchTimeout.value);
    }

    searchTimeout.value = setTimeout(() => {
        emit('search', query.value);
    }, SEARCH_DEBOUNCE_MS);
}

function onDocumentClick(event: MouseEvent): void {
    if (
        isOpen.value &&
        controlEl.value !== null &&
        !controlEl.value.contains(event.target as Node)
    ) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    if (searchTimeout.value !== null) {
        clearTimeout(searchTimeout.value);
    }

    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
  <div class="dropdown">
    <label
      v-if="props.label"
      class="dropdown__label"
      :class="{ 'dropdown__label--required': props.required }"
    >
      {{ props.label }}
    </label>
    <div
      ref="controlEl"
      class="dropdown__control"
      :class="{ 'is-disabled': props.disabled }"
      @keydown="onControlKeydown"
    >
      <button
        ref="triggerEl"
        type="button"
        class="dropdown__trigger"
        :class="{ 'is-open': isOpen }"
        :disabled="props.disabled"
        :aria-haspopup="'listbox'"
        :aria-expanded="isOpen"
        :aria-required="props.required || undefined"
        :data-testid="props.test_id"
        @click="toggleOpen"
      >
        <slot
          name="trigger"
          :selected_options="selectedOptions"
          :placeholder="props.placeholder"
          :open="isOpen"
        >
          <span class="dropdown__trigger-value">{{ triggerLabel }}</span>
          <Icon
            class="dropdown__chevron"
            icon="heroicons:chevron-down"
            aria-hidden="true"
          />
        </slot>
      </button>
      <div
        v-if="isOpen"
        :id="listboxId"
        class="dropdown__panel"
        role="listbox"
        :aria-label="props.label"
      >
        <input
          v-if="props.searchable"
          ref="searchEl"
          v-model="query"
          type="text"
          class="dropdown__search"
          :placeholder="props.placeholder"
          :data-testid="`${props.test_id}-search`"
          @input="onQueryInput"
        >
        <div
          v-if="props.loading"
          class="dropdown__skeletons"
        >
          <PublicBaseSkeleton
            v-for="row in 3"
            :key="row"
            shape="pill"
            width="80%"
            height="1.5em"
          />
        </div>
        <template v-else-if="filteredOptions.length > 0">
          <div
            v-for="(option, index) in filteredOptions"
            :id="`${listboxId}-option-${index}`"
            :key="option.value"
            class="dropdown__option"
            :class="{
              'is-highlighted': index === highlighted,
              'is-disabled': option.disabled,
            }"
            role="option"
            :aria-selected="isSelected(option)"
            :aria-disabled="option.disabled || undefined"
            :data-testid="`${props.test_id}-option-${option.value}`"
            @click="selectOption(option)"
            @mousemove="highlighted = index"
          >
            <slot
              name="option"
              :option="option"
              :selected="isSelected(option)"
            >
              <span class="dropdown__option-label">{{ option.label }}</span>
            </slot>
          </div>
        </template>
        <p
          v-else
          class="dropdown__empty"
        >
          {{ t('common.no_options') }}
        </p>
      </div>
    </div>
    <p
      v-if="props.error !== null"
      class="dropdown__error"
      role="alert"
    >
      {{ props.error }}
    </p>
  </div>
</template>

<style scoped>
.dropdown {
    margin-bottom: var(--space-lg);
    max-width: 400px;
}

.dropdown__label {
    display: block;
    font-size: var(--font-size-sm);
    font-weight: var(--font-semibold);
    color: var(--color-text-primary);
    margin-bottom: var(--space-xs);
}

.dropdown__label--required::after {
    content: ' *';
    color: var(--color-error);
}

.dropdown__control {
    position: relative;
}

.dropdown__trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-sm);
    width: 100%;
    height: var(--input-height);
    padding: var(--input-padding-y) var(--input-padding-x);
    font-size: var(--input-font-size);
    font-family: var(--font-sans);
    color: var(--color-text-primary);
    background: var(--color-surface);
    border: var(--input-border-width) solid var(--color-border);
    border-radius: var(--input-radius);
    cursor: pointer;
    transition: var(--input-transition);
    text-align: left;
}

.dropdown__trigger:hover {
    border-color: var(--color-border-strong);
}

.dropdown__trigger:focus,
.dropdown__trigger.is-open {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgb(30 122 91 / 18%);
    outline: none;
}

.dropdown__trigger-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dropdown__chevron {
    flex-shrink: 0;
    width: 1.1em;
    height: 1.1em;
    color: var(--color-text-tertiary);
    transition: transform var(--transition-fast) var(--ease-default);
}

.dropdown__trigger.is-open .dropdown__chevron {
    transform: rotate(180deg);
}

.dropdown__control.is-disabled {
    opacity: 0.45;
    pointer-events: none;
    cursor: not-allowed;
}

.dropdown__panel {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: var(--z-dropdown);
}

.dropdown__search {
    width: 100%;
    height: var(--input-height);
    padding: var(--input-padding-y) var(--input-padding-x);
    font-size: var(--input-font-size);
    font-family: var(--font-sans);
    color: var(--color-text-primary);
    background: var(--color-surface);
    border: none;
    border-bottom: 1px solid var(--color-border);
}

.dropdown__search::placeholder {
    color: var(--color-text-tertiary);
}

.dropdown__search:focus {
    outline: none;
}

.dropdown__skeletons {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    padding: var(--space-md);
}

.dropdown__option {
    display: flex;
    align-items: center;
    padding: var(--space-sm) var(--space-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-primary);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.dropdown__option:hover,
.dropdown__option.is-highlighted {
    background: var(--color-accent-muted);
}

.dropdown__option.is-disabled {
    opacity: 0.45;
    pointer-events: none;
}

.dropdown__option-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dropdown__empty {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.dropdown__error {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    margin-top: var(--space-xs);
    font-size: var(--font-size-sm);
    color: var(--color-error);
    font-weight: var(--font-medium);
}
</style>
