<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { onBeforeUnmount, ref, useId } from 'vue';

import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

interface SearchResultItem {
    [key: string]: unknown;
}

const { get } = useJsonApi();

const props = withDefaults(
    defineProps<{
        fetch_url: string;
        placeholder?: string;
        debounce_ms?: number;
        result_limit?: number;
        result_label_key?: string;
        result_id_key?: string;
        result_url_prefix?: string;
        result_suffix_meta_key?: string | null;
        test_id?: string;
    }>(),
    {
        placeholder: undefined,
        debounce_ms: 300,
        result_limit: 8,
        result_label_key: 'title',
        result_id_key: 'id',
        result_url_prefix: '',
        result_suffix_meta_key: 'published_at',
        test_id: 'search-autocomplete',
    },
);

const emit = defineEmits<{ submit: [query: string] }>();

const { t } = useI18n();

const query = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const hasError = ref(false);
const highlighted = ref(-1);
const results = ref<SearchResultItem[]>([]);

const inputEl = ref<HTMLInputElement | null>(null);
const listboxId = useId();
const debounceTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const closeTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
let activeRequest: AbortController | null = null;

function resultLabel(item: SearchResultItem): string {
    const value = item[props.result_label_key];

    return typeof value === 'string' ? value : '';
}

function resultMeta(item: SearchResultItem): string | null {
    if (props.result_suffix_meta_key === null) {
        return null;
    }

    const value = item[props.result_suffix_meta_key];

    return typeof value === 'string' ? formatMetaValue(value) : null;
}

// ISO timestamp meta values (published_at) render as `dd.mm.yyyy` — the
// admin's date convention (same formatter as the experience list) —
// instead of the raw timestamp. Non-date strings pass through.
function formatMetaValue(value: string): string {
    const [datePart] = value.split('T');
    const [year, month, day] = datePart.split('-');

    if (year === undefined || month === undefined || day === undefined) {
        return value;
    }

    return `${day}.${month}.${year}`;
}

function resultEditUrl(item: SearchResultItem): string {
    return `${props.result_url_prefix}${String(item[props.result_id_key])}/edit`;
}

function onInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;
    highlighted.value = -1;

    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
        debounceTimeout.value = null;
    }

    if (query.value.trim() === '') {
        isOpen.value = false;
        isLoading.value = false;
        hasError.value = false;
        return;
    }

    // The skeleton shows immediately on the first keystroke; the fetch
    // itself waits for the debounce.
    isOpen.value = true;
    isLoading.value = true;
    hasError.value = false;

    debounceTimeout.value = setTimeout(() => {
        void fetchResults();
    }, props.debounce_ms);
}

async function fetchResults(): Promise<void> {
    activeRequest?.abort();
    activeRequest = new AbortController();

    try {
        const url = `${props.fetch_url}?q=${encodeURIComponent(query.value)}&per_page=${props.result_limit}`;
        const payload = await get<{ data?: SearchResultItem[] }>(url, {
            signal: activeRequest.signal,
        });

        results.value = payload.data ?? [];
        highlighted.value = results.value.length > 0 ? 0 : -1;
        isLoading.value = false;
        hasError.value = false;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        results.value = [];
        isLoading.value = false;
        hasError.value = true;
    }
}

function goTo(item: SearchResultItem): void {
    router.visit(resultEditUrl(item));
}

function onKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            if (results.value.length > 0) {
                highlighted.value = (highlighted.value + 1) % results.value.length;
            }
            break;
        case 'ArrowUp':
            event.preventDefault();
            if (results.value.length > 0) {
                highlighted.value =
                    highlighted.value <= 0 ? results.value.length - 1 : highlighted.value - 1;
            }
            break;
        case 'Enter': {
            event.preventDefault();
            const item = results.value[highlighted.value];

            if (item !== undefined) {
                goTo(item);
            } else {
                emit('submit', query.value);
            }
            break;
        }
        case 'Escape':
            closeDropdown();
            inputEl.value?.blur();
            break;
    }
}

function closeDropdown(): void {
    isOpen.value = false;
    highlighted.value = -1;
}

function onBlur(): void {
    // Delayed so a result's mousedown (prevented) does not race the close.
    closeTimeout.value = setTimeout(() => {
        closeDropdown();
    }, 150);
}

function retry(): void {
    isLoading.value = true;
    hasError.value = false;
    void fetchResults();
}

function cleanup(): void {
    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
    }

    if (closeTimeout.value !== null) {
        clearTimeout(closeTimeout.value);
    }

    activeRequest?.abort();
}

onBeforeUnmount(cleanup);
</script>

<template>
  <div class="relative">
    <input
      ref="inputEl"
      type="search"
      data-shortcut-search=""
      class="block w-full h-10 px-3 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
      :value="query"
      :placeholder="props.placeholder"
      role="combobox"
      :aria-expanded="isOpen"
      :aria-controls="isOpen ? listboxId : undefined"
      :aria-activedescendant="isOpen && highlighted >= 0 ? `${listboxId}-option-${highlighted}` : undefined"
      :data-testid="`${props.test_id}-input`"
      @input="onInput"
      @focus="isOpen = true"
      @blur="onBlur"
      @keydown="onKeydown"
    >
    <div
      v-if="isOpen && query.trim() !== ''"
      :id="listboxId"
      class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 rounded-lg shadow-dropdown z-dropdown overflow-hidden"
      role="listbox"
      :data-testid="`${props.test_id}-dropdown`"
    >
      <div
        v-if="isLoading"
        class="flex flex-col gap-2 p-3"
      >
        <AdminBaseSkeleton
          v-for="row in 4"
          :key="row"
          shape="text"
          :width="row % 2 === 0 ? '60%' : '40%'"
        />
      </div>
      <div
        v-else-if="hasError"
        class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
      >
        {{ t('common.search_unavailable') }}
        <button
          type="button"
          class="ml-1 font-semibold text-primary-600 dark:text-primary-400 underline underline-offset-2"
          :data-testid="`${props.test_id}-retry`"
          @click="retry"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <template v-else-if="results.length > 0">
        <template
          v-for="(item, index) in results"
          :key="resultEditUrl(item)"
        >
          <slot
            name="result"
            :item="item"
            :highlighted="index === highlighted"
          >
            <div
              :id="`${listboxId}-option-${index}`"
              class="flex items-center justify-between gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950 cursor-pointer"
              :class="{ 'bg-primary-50 dark:bg-primary-950': index === highlighted }"
              role="option"
              :aria-selected="index === highlighted"
              :data-testid="`${props.test_id}-result-${index}`"
              @mousedown.prevent
              @mousemove="highlighted = index"
              @click="goTo(item)"
            >
              <span>{{ resultLabel(item) }}</span>
              <span
                v-if="resultMeta(item) !== null"
                class="text-xs text-slate-400"
              >
                {{ resultMeta(item) }}
              </span>
            </div>
          </slot>
        </template>
      </template>
      <p
        v-else
        class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
      >
        {{ t('common.no_matches') }}
      </p>
    </div>
  </div>
</template>
