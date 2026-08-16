<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import PublicBaseBadge from './PublicBaseBadge.vue';
import PublicBaseSkeleton from './PublicBaseSkeleton.vue';

interface FilterTag {
    id: number;
    name: string;
    type?: string;
}

interface TagSuggestion {
    id: number;
    name: string;
    type?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: FilterTag[];
        fetch_url: string;
        placeholder?: string;
        debounce_ms?: number;
        min_chars_for_search?: number;
        test_id?: string;
    }>(),
    {
        placeholder: undefined,
        debounce_ms: 300,
        min_chars_for_search: 1,
        test_id: 'tag-filter',
    },
);

const emit = defineEmits<{ 'update:modelValue': [tags: FilterTag[]] }>();

const { t } = useI18n();

const query = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const hasError = ref(false);
const highlighted = ref(-1);
const suggestions = ref<TagSuggestion[]>([]);

const inputEl = ref<HTMLInputElement | null>(null);
const rootEl = ref<HTMLElement | null>(null);
const listboxId = useId();
const debounceTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
let activeRequest: AbortController | null = null;

function tagType(tag: FilterTag | TagSuggestion): string {
    return tag.type ?? '';
}

function isSelected(suggestion: TagSuggestion): boolean {
    return props.modelValue.some(
        (tag) => tag.id === suggestion.id && tagType(tag) === tagType(suggestion),
    );
}

const visibleSuggestions = computed(() =>
    suggestions.value.filter((suggestion) => !isSelected(suggestion)),
);

function addTag(suggestion: TagSuggestion): void {
    const next: FilterTag = { id: suggestion.id, name: suggestion.name };

    if (suggestion.type !== undefined) {
        next.type = suggestion.type;
    }

    emit('update:modelValue', [...props.modelValue, next]);
    query.value = '';
    highlighted.value = -1;
    hasError.value = false;
    isLoading.value = false;

    nextTick(() => inputEl.value?.focus());
}

function removeTag(tag: FilterTag): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((item) => !(item.id === tag.id && tagType(item) === tagType(tag))),
    );
}

function removeLastTag(): void {
    const last = props.modelValue[props.modelValue.length - 1];

    if (last !== undefined) {
        removeTag(last);
    }
}

function onInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;
    highlighted.value = -1;

    if (query.value.length < props.min_chars_for_search) {
        isOpen.value = false;
        isLoading.value = false;
        hasError.value = false;
        return;
    }

    isLoading.value = true;
    hasError.value = false;
    isOpen.value = true;

    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
    }

    debounceTimeout.value = setTimeout(() => {
        void fetchSuggestions();
    }, props.debounce_ms);
}

async function fetchSuggestions(): Promise<void> {
    activeRequest?.abort();
    activeRequest = new AbortController();

    try {
        const url = `${props.fetch_url}?q=${encodeURIComponent(query.value)}`;
        const response = await fetch(url, {
            signal: activeRequest.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Tag search request failed: ${response.status}`);
        }

        const payload = (await response.json()) as TagSuggestion[] | { data?: TagSuggestion[] };
        const fetched = Array.isArray(payload) ? payload : (payload.data ?? []);

        // Blog-tags responses are { id, name, type } objects; skills
        // responses carry no type. Normalize the shape.
        suggestions.value = fetched;
        isLoading.value = false;
        hasError.value = false;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        suggestions.value = [];
        isLoading.value = false;
        hasError.value = true;
    }
}

function retry(): void {
    isLoading.value = true;
    hasError.value = false;
    void fetchSuggestions();
}

function onKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            if (visibleSuggestions.value.length > 0) {
                highlighted.value = (highlighted.value + 1) % visibleSuggestions.value.length;
            }
            break;
        case 'ArrowUp':
            event.preventDefault();
            if (highlighted.value <= 0) {
                highlighted.value = -1;
            } else {
                highlighted.value -= 1;
            }
            break;
        case 'Enter': {
            event.preventDefault();
            const suggestion = visibleSuggestions.value[highlighted.value];

            if (suggestion !== undefined) {
                addTag(suggestion);
            }
            break;
        }
        case 'Escape':
            isOpen.value = false;
            highlighted.value = -1;
            inputEl.value?.blur();
            break;
        case 'Backspace':
            if (query.value === '' && props.modelValue.length > 0) {
                event.preventDefault();
                removeLastTag();
            }
            break;
    }
}

function cleanup(): void {
    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
    }

    activeRequest?.abort();
}

// A click anywhere outside the control closes the suggestion popup (the
// dropdown stayed open forever before — user rework 2026-08-16). Clicks
// on suggestions use `@mousedown.prevent`, so selecting one never races
// the document listener (the same pattern as PublicBaseDropdown).
function onDocumentClick(event: MouseEvent): void {
    if (isOpen.value && rootEl.value !== null && !rootEl.value.contains(event.target as Node)) {
        isOpen.value = false;
        highlighted.value = -1;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    cleanup();
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
  <div
    ref="rootEl"
    class="tag-filter"
    :data-testid="props.test_id"
  >
    <div class="tag-filter__pills">
      <!-- The icon is the row's FIRST child — it stays at the left
           edge even when pills are selected (user rework 2026-08-16;
           it used to live inside the input-wrap and slid after the
           pills). -->
      <Icon
        class="tag-filter__input-icon"
        icon="heroicons:tag"
        aria-hidden="true"
      />
      <PublicBaseBadge
        v-for="(tag, index) in props.modelValue"
        :key="`${tag.type ?? 'tag'}-${tag.id}`"
        variant="default"
        removable
        :test_id="`${props.test_id}-pill-${index}`"
        @remove="removeTag(tag)"
      >
        {{ tag.name }}
      </PublicBaseBadge>
      <div class="tag-filter__input-wrap">
        <input
          ref="inputEl"
          type="text"
          class="tag-filter__input"
          :value="query"
          :placeholder="props.placeholder ?? t('common.start_typing_to_search')"
          role="combobox"
          :aria-expanded="isOpen"
          :aria-controls="isOpen ? listboxId : undefined"
          :aria-activedescendant="isOpen && highlighted >= 0 ? `${listboxId}-option-${highlighted}` : undefined"
          :data-testid="`${props.test_id}-input`"
          @input="onInput"
          @focus="isOpen = true"
          @keydown="onKeydown"
        >
      </div>
    </div>
    <div
      v-if="isOpen"
      :id="listboxId"
      class="tag-filter__dropdown"
      role="listbox"
      :data-testid="`${props.test_id}-dropdown`"
    >
      <div
        v-if="isLoading"
        class="tag-filter__skeletons"
      >
        <PublicBaseSkeleton
          v-for="row in 3"
          :key="row"
          shape="pill"
          width="40%"
          height="1.5em"
        />
      </div>
      <div
        v-else-if="hasError"
        class="tag-filter__error"
      >
        {{ t('common.could_not_load_tags') }}
        <button
          type="button"
          class="tag-filter__retry"
          :data-testid="`${props.test_id}-retry`"
          @click="retry"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <template v-else-if="visibleSuggestions.length > 0">
        <template
          v-for="(suggestion, index) in visibleSuggestions"
          :key="`${suggestion.type ?? 'tag'}-${suggestion.id}`"
        >
          <slot
            name="suggestion"
            :suggestion="suggestion"
            :select="() => addTag(suggestion)"
          >
            <div
              :id="`${listboxId}-option-${index}`"
              class="tag-filter__suggestion"
              :class="{ 'is-highlighted': index === highlighted }"
              role="option"
              :aria-selected="index === highlighted"
              :data-testid="`${props.test_id}-suggestion-${index}`"
              @mousedown.prevent
              @mousemove="highlighted = index"
              @click="addTag(suggestion)"
            >
              <span>{{ suggestion.name }}</span>
              <span
                v-if="suggestion.type"
                class="tag-filter__suggestion-type"
              >
                {{ suggestion.type }}
              </span>
            </div>
          </slot>
        </template>
      </template>
      <p
        v-else
        class="tag-filter__empty"
      >
        {{ t('common.no_tags_found') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.tag-filter {
    position: relative;
    max-width: 400px;
}

/* The pill row keeps the exact height of a single input
   (`--input-height` — the same height as the search box and the sort
   dropdown; user rework 2026-08-16): the pills stay on one line and
   overflow horizontally (hidden scrollbar) instead of wrapping the
   control taller. */
.tag-filter__pills {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: var(--space-xs);
    height: var(--input-height);
    padding: 0 var(--space-md);
    overflow-x: auto;
    scrollbar-width: none;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--input-radius);
    transition: var(--input-transition);
}

.tag-filter__pills::-webkit-scrollbar {
    display: none;
}

.tag-filter__pills:focus-within {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgb(30 122 91 / 18%);
}

.tag-filter__input-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 160px;
}

/* The icon sits at the row's start as a static flex item (no absolute
   positioning — it stays before the pills, user rework 2026-08-16). */
.tag-filter__input-icon {
    flex-shrink: 0;
    width: 1.1em;
    height: 1.1em;
    margin-right: var(--space-xs);
    color: var(--color-text-tertiary);
    pointer-events: none;
}

.tag-filter__input {
    width: 100%;
    height: var(--input-height);
    padding: 0;
    border: none;
    background: transparent;
    font-size: var(--input-font-size);
    font-family: var(--font-sans);
    color: var(--color-text-primary);
}

.tag-filter__input::placeholder {
    color: var(--color-text-tertiary);
}

.tag-filter__input:focus {
    outline: none;
}

.tag-filter__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 240px;
    overflow-y: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: var(--z-dropdown);
}

.tag-filter__skeletons {
    display: flex;
    gap: var(--space-sm);
    padding: var(--space-md);
}

.tag-filter__suggestion {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-primary);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.tag-filter__suggestion:hover,
.tag-filter__suggestion.is-highlighted {
    background: var(--color-accent-muted);
}

.tag-filter__suggestion-type {
    font-size: var(--font-size-xs);
    color: var(--color-text-tertiary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.tag-filter__empty {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.tag-filter__error {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-error);
}

.tag-filter__retry {
    background: none;
    border: none;
    color: var(--color-accent);
    font-weight: var(--font-semibold);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
