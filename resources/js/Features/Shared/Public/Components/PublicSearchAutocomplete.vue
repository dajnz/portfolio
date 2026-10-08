<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import PublicBaseSkeleton from './PublicBaseSkeleton.vue';

const props = withDefaults(
    defineProps<{
        fetch_url: string;
        placeholder?: string;
        debounce_ms?: number;
        result_limit?: number;
        result_label_key?: string;
        result_url_key?: string;
        result_url_prefix?: string;
        test_id?: string;
    }>(),
    {
        placeholder: undefined,
        debounce_ms: 300,
        result_limit: 8,
        result_label_key: 'title',
        result_url_key: 'slug',
        result_url_prefix: '',
        test_id: 'search-autocomplete',
    },
);

const emit = defineEmits<{ submit: [query: string] }>();

const { t } = useI18n();

interface SearchResultItem {
    [key: string]: unknown;
}

const query = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const hasError = ref(false);
const highlighted = ref(-1);
const results = ref<SearchResultItem[]>([]);

const inputEl = ref<HTMLInputElement | null>(null);
const rootEl = ref<HTMLElement | null>(null);
const listboxId = useId();
const debounceTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
let activeRequest: AbortController | null = null;

function resultLabel(item: SearchResultItem): string {
    const value = item[props.result_label_key];

    return typeof value === 'string' ? value : '';
}

// The publication meta of a result row: the localized "published:"
// label (`common.published`) plus the numeric "dd.mm.yyyy" date — the
// numeric form carries no month names, only the label needs the
// catalog (user rework 2026-08-16). An empty string hides the meta
// when the raw value is missing or unparseable.
function publishedMeta(item: SearchResultItem): string {
    const value = item.published_at;
    const date = typeof value === 'string' ? new Date(value) : null;

    if (date === null || Number.isNaN(date.getTime())) {
        return '';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');

    return `${t('common.published')} ${day}.${month}.${date.getFullYear()}`;
}

function resultUrl(item: SearchResultItem): string {
    const value = item[props.result_url_key];

    return props.result_url_prefix + (typeof value === 'string' ? value : '');
}

function onInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;
    highlighted.value = -1;

    // An empty query has nothing to search — close the (stale-results)
    // dropdown instead of fetching or showing a count (a11y 13.6 review).
    if (query.value.trim() === '') {
        if (debounceTimeout.value !== null) {
            clearTimeout(debounceTimeout.value);
            debounceTimeout.value = null;
        }

        activeRequest?.abort();
        results.value = [];
        isLoading.value = false;
        hasError.value = false;
        closeDropdown();
        return;
    }

    // The skeleton shows immediately on the first keystroke; the fetch
    // itself waits for the debounce (public-section-ui.md:490).
    isLoading.value = true;
    hasError.value = false;
    isOpen.value = true;

    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
    }

    debounceTimeout.value = setTimeout(() => {
        void fetchResults();
    }, props.debounce_ms);
}

async function fetchResults(): Promise<void> {
    activeRequest?.abort();
    activeRequest = new AbortController();

    try {
        const url = `${props.fetch_url}?q=${encodeURIComponent(query.value)}&per_page=${props.result_limit}`;
        const response = await fetch(url, {
            signal: activeRequest.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Search request failed: ${response.status}`);
        }

        const payload = (await response.json()) as { data?: SearchResultItem[] };

        results.value = payload.data ?? [];
        // First result carries a subtle highlight outline (results state).
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
    router.visit(resultUrl(item));
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

// Close on a pointer press OUTSIDE the control (the PublicTagFilter
// pattern) rather than on input blur: a screen reader navigating into the
// result list would blur the input and lose the list (a11y 13.6 #11).
function onDocumentPointerDown(event: PointerEvent): void {
    if (!isOpen.value || rootEl.value === null) {
        return;
    }

    if (!rootEl.value.contains(event.target as Node)) {
        closeDropdown();
    }
}

// The result count announcement (a11y 13.6 #10) — cleared while loading
// or errored so those states are not double-announced.
const announcement = computed(() => {
    if (!isOpen.value || isLoading.value || hasError.value) {
        return '';
    }

    if (results.value.length === 0) {
        return t('common.no_matches');
    }

    return t('common.results_found', { count: results.value.length });
});

function retry(): void {
    isLoading.value = true;
    hasError.value = false;
    void fetchResults();
}

function cleanup(): void {
    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
    }

    activeRequest?.abort();
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
});

onBeforeUnmount(() => {
    cleanup();
    document.removeEventListener('pointerdown', onDocumentPointerDown);
});
</script>

<template>
  <div
    ref="rootEl"
    class="search-autocomplete"
    :data-testid="props.test_id"
  >
    <Icon
      class="search-autocomplete__icon"
      icon="heroicons:magnifying-glass"
      aria-hidden="true"
    />
    <input
      ref="inputEl"
      type="search"
      class="search-autocomplete__input"
      :value="query"
      :placeholder="props.placeholder"
      role="combobox"
      :aria-expanded="isOpen"
      :aria-controls="isOpen ? listboxId : undefined"
      :aria-activedescendant="isOpen && highlighted >= 0 ? `${listboxId}-option-${highlighted}` : undefined"
      :data-testid="`${props.test_id}-input`"
      @input="onInput"
      @keydown="onKeydown"
    >
    <p
      class="sr-only"
      role="status"
      aria-live="polite"
      :data-testid="`${props.test_id}-status`"
    >
      {{ announcement }}
    </p>
    <div
      v-if="isOpen"
      :id="listboxId"
      class="search-autocomplete__dropdown"
      role="listbox"
      :data-testid="`${props.test_id}-dropdown`"
    >
      <div
        v-if="isLoading"
        class="search-autocomplete__skeletons"
      >
        <PublicBaseSkeleton
          v-for="row in 4"
          :key="row"
          shape="text"
          :width="row % 2 === 0 ? '60%' : '40%'"
        />
      </div>
      <div
        v-else-if="hasError"
        class="search-autocomplete__error"
      >
        {{ t('common.search_unavailable') }}
        <button
          type="button"
          class="search-autocomplete__retry"
          :data-testid="`${props.test_id}-retry`"
          @click="retry"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <template v-else-if="results.length > 0">
        <template
          v-for="(item, index) in results"
          :key="resultUrl(item)"
        >
          <slot
            name="result"
            :item="item"
            :highlighted="index === highlighted"
          >
            <a
              :id="`${listboxId}-option-${index}`"
              class="search-autocomplete__result"
              :class="{ 'search-autocomplete__result--highlighted': index === highlighted }"
              role="option"
              :aria-selected="index === highlighted"
              :href="resultUrl(item)"
              :data-testid="`${props.test_id}-result-${index}`"
              @mousedown.prevent
              @mousemove="highlighted = index"
              @click.prevent="goTo(item)"
            >
              <span class="search-autocomplete__result-title">{{ resultLabel(item) }}</span>
              <span
                v-if="typeof item.published_at === 'string'"
                class="search-autocomplete__result-meta"
              >
                {{ publishedMeta(item) }}
              </span>
            </a>
          </slot>
        </template>
      </template>
      <p
        v-else
        class="search-autocomplete__empty"
      >
        {{ t('common.no_matches') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.search-autocomplete {
    position: relative;
    max-width: 400px;
}

.search-autocomplete__icon {
    position: absolute;
    left: var(--input-padding-x);
    top: 50%;
    transform: translateY(-50%);
    width: 1.25em;
    height: 1.25em;
    color: var(--color-text-tertiary);
    pointer-events: none;
}

.search-autocomplete__input {
    display: block;
    width: 100%;
    height: var(--input-height);
    padding: var(--input-padding-y) var(--input-padding-x) var(--input-padding-y) 2.75rem;
    font-size: var(--input-font-size);
    color: var(--color-text-primary);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--input-radius);
    transition: var(--input-transition);
}

.search-autocomplete__input::placeholder {
    color: var(--color-text-tertiary);
}

.search-autocomplete__input:focus {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgb(30 122 91 / 18%);
    outline: none;
}

.search-autocomplete__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: var(--z-dropdown);
    overflow: hidden;
}

.search-autocomplete__skeletons {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    padding: var(--space-md);
}

/* The result row is a flex line: the title takes the available width
   (ellipsized) and the publication meta sits flush right (user rework
   2026-08-16 — the raw date used to wrap under the title). */
.search-autocomplete__result {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-md);
    padding: var(--space-sm) var(--space-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-primary);
    cursor: pointer;
    text-decoration: none;
    transition: background var(--transition-fast);
}

.search-autocomplete__result:hover,
.search-autocomplete__result--highlighted {
    background: var(--color-accent-muted);
}

.search-autocomplete__result-title {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: var(--font-semibold);
}

.search-autocomplete__result-meta {
    flex-shrink: 0;
    font-size: var(--font-size-xs);
    color: var(--color-text-tertiary);
    white-space: nowrap;
}

.search-autocomplete__empty {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.search-autocomplete__error {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-error);
}

.search-autocomplete__retry {
    background: none;
    border: none;
    color: var(--color-accent);
    font-weight: var(--font-semibold);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
