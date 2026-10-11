<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

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
        /** Fixed trigger width (CSS length) — the trigger keeps the
         *  width regardless of the selected option's label. */
        width?: string;
        /** Native tooltip of the trigger button — the multi-select
         *  blog form's Related Articles field shows the full
         *  alphabetical list of the linked article names when the
         *  truncated trigger label cannot (newlines render as line
         *  breaks in native tooltips). */
        title?: string;
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
        width: undefined,
        title: undefined,
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

const triggerClass = computed(() =>
    isOpen.value ? 'border-primary-500 ring-[3px] ring-primary-100 dark:ring-primary-900' : '',
);

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
    // The search query must not leak into the next open — a reopened
    // panel would otherwise stay filtered by the previous term (the
    // timezone picker renders 400+ options).
    query.value = '';
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
            // Stop the bubble — the global Escape shortcut (cancel inline
            // editing) must not fire while the panel is open.
            event.preventDefault();
            event.stopPropagation();
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
  <div>
    <label
      v-if="props.label"
      class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-slate-200"
    >
      {{ props.label }}
      <span
        v-if="props.required"
        class="text-danger-500"
        aria-hidden="true"
      >
        *
      </span>
    </label>
    <div
      ref="controlEl"
      class="relative"
      :class="{ 'opacity-45 pointer-events-none': props.disabled }"
      :style="props.width !== undefined ? { width: props.width } : undefined"
      @keydown="onControlKeydown"
    >
      <button
        ref="triggerEl"
        type="button"
        class="flex w-full items-center justify-between gap-2 h-10 px-3 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all text-left cursor-pointer"
        :class="triggerClass"
        :disabled="props.disabled"
        :title="props.title"
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
          <span class="flex-1 truncate">{{ triggerLabel }}</span>
          <Icon
            class="flex-shrink-0 w-5 h-5 transition-transform"
            :class="{ 'rotate-180': isOpen }"
            icon="heroicons:chevron-down"
            aria-hidden="true"
          />
        </slot>
      </button>
      <div
        v-if="isOpen"
        :id="listboxId"
        class="thin-scrollbar absolute top-full left-0 right-0 mt-1 max-h-[280px] overflow-y-auto bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 rounded-lg shadow-dropdown z-dropdown"
        role="listbox"
        :aria-label="props.label"
      >
        <div
          v-if="props.searchable"
          class="p-2 pb-1"
        >
          <input
            ref="searchEl"
            v-model="query"
            type="text"
            class="w-full h-9 px-3 text-sm bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
            :placeholder="props.placeholder"
            :data-testid="`${props.test_id}-search`"
            @input="onQueryInput"
          >
        </div>
        <div
          v-if="props.loading"
          class="flex flex-col gap-2 px-3 py-2"
        >
          <AdminBaseSkeleton
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
            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950 cursor-pointer text-left font-medium"
            :class="{
              'bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300':
                index === highlighted,
              'opacity-45 pointer-events-none': option.disabled,
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
              <span class="flex-1 truncate">{{ option.label }}</span>
            </slot>
          </div>
        </template>
        <p
          v-else
          class="px-3 py-4 text-sm text-center text-slate-400 dark:text-slate-500"
        >
          {{ t('common.no_options') }}
        </p>
      </div>
    </div>
    <p
      v-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
  </div>
</template>
