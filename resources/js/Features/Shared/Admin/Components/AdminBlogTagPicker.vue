// admin-section-components.md:623-645 — AdminBlogTagPicker
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue';

import AdminBaseBadge from './AdminBaseBadge.vue';
import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

type TagType = 'skill' | 'tag';
type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'danger';

interface BlogTag {
    id: number;
    name: string;
    type: TagType;
}

const TYPE_VARIANT: Record<TagType, BadgeVariant> = {
    skill: 'primary',
    tag: 'default',
};

const props = withDefaults(
    defineProps<{
        modelValue: BlogTag[];
        label?: string;
        error?: string | null;
        required?: boolean;
        help?: string;
        placeholder?: string;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        label: undefined,
        error: null,
        required: false,
        help: undefined,
        placeholder: undefined,
        disabled: false,
        test_id: 'blog-tag-picker',
    },
);

const emit = defineEmits<{ 'update:modelValue': [tags: BlogTag[]] }>();

const { t } = useI18n();
const { get } = useJsonApi();

const TAGS_SEARCH_URL = '/api/blog-tags/search';
const SEARCH_DEBOUNCE_MS = 300;

const query = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const hasError = ref(false);
const highlighted = ref(-1);
const suggestions = ref<BlogTag[]>([]);

const inputEl = ref<HTMLInputElement | null>(null);
const listboxId = useId();
const debounceTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
let activeRequest: AbortController | null = null;

const visibleSuggestions = computed(() =>
    suggestions.value.filter(
        (suggestion) =>
            !props.modelValue.some(
                (tag) => tag.id === suggestion.id && tag.type === suggestion.type,
            ),
    ),
);

function addTag(tag: BlogTag): void {
    emit('update:modelValue', [...props.modelValue, tag]);
    query.value = '';
    highlighted.value = -1;
    hasError.value = false;
    isLoading.value = false;

    nextTick(() => inputEl.value?.focus());
}

function removeTag(tag: BlogTag): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((item) => !(item.id === tag.id && item.type === tag.type)),
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

    isOpen.value = true;
    isLoading.value = true;
    hasError.value = false;

    debounceTimeout.value = setTimeout(() => {
        void fetchSuggestions();
    }, SEARCH_DEBOUNCE_MS);
}

async function fetchSuggestions(): Promise<void> {
    activeRequest?.abort();
    activeRequest = new AbortController();

    try {
        const url = `${TAGS_SEARCH_URL}?q=${encodeURIComponent(query.value)}`;
        const payload = await get<{ data?: BlogTag[] }>(url, {
            signal: activeRequest.signal,
        });

        suggestions.value = payload.data ?? [];
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
    if (props.disabled) {
        return;
    }

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

onBeforeUnmount(cleanup);
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
    <div class="relative">
      <div
        class="flex w-full flex-wrap items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark-secondary px-2 py-1.5 focus-within:border-primary-500"
        :class="{
          'border-danger-300 dark:border-danger-700': props.error !== null,
          'opacity-45 pointer-events-none': props.disabled,
        }"
        :data-testid="props.test_id"
      >
        <AdminBaseBadge
          v-for="(tag, index) in props.modelValue"
          :key="`${tag.type}-${tag.id}`"
          :variant="TYPE_VARIANT[tag.type]"
          size="sm"
          removable
          :test_id="`${props.test_id}-pill-${index}`"
          @remove="removeTag(tag)"
        >
          <slot
            name="pill"
            :tag="tag"
            :remove="() => removeTag(tag)"
          >
            {{ tag.name }}
          </slot>
        </AdminBaseBadge>
        <input
          ref="inputEl"
          type="text"
          class="flex-1 min-w-[8rem] bg-transparent text-sm outline-none placeholder-slate-400 dark:placeholder-slate-500"
          :value="query"
          :placeholder="props.placeholder ?? t('admin.tags.search_placeholder')"
          :disabled="props.disabled || undefined"
          role="combobox"
          :aria-expanded="isOpen"
          :aria-controls="isOpen ? listboxId : undefined"
          :aria-activedescendant="isOpen && highlighted >= 0 ? `${listboxId}-option-${highlighted}` : undefined"
          :aria-invalid="props.error !== null || undefined"
          :data-testid="`${props.test_id}-input`"
          @input="onInput"
          @focus="isOpen = true"
          @keydown="onKeydown"
        >
      </div>
      <div
        v-if="isOpen"
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
            v-for="row in 3"
            :key="row"
            shape="pill"
            width="40%"
            height="1.5em"
          />
        </div>
        <div
          v-else-if="hasError"
          class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
        >
          {{ t('common.could_not_load_tags') }}
          <button
            type="button"
            class="ml-1 font-semibold text-primary-600 dark:text-primary-400 underline underline-offset-2"
            :data-testid="`${props.test_id}-retry`"
            @click="retry"
          >
            {{ t('common.retry') }}
          </button>
        </div>
        <template v-else-if="query.trim() === ''">
          <p
            class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
          >
            {{ t('common.start_typing_to_search') }}
          </p>
        </template>
        <template v-else-if="visibleSuggestions.length > 0">
          <template
            v-for="(suggestion, index) in visibleSuggestions"
            :key="`${suggestion.type}-${suggestion.id}`"
          >
            <slot
              name="suggestion"
              :suggestion="suggestion"
              :select="() => addTag(suggestion)"
            >
              <div
                :id="`${listboxId}-option-${index}`"
                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950 transition-colors cursor-pointer"
                :class="{
                  'bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300':
                    index === highlighted,
                }"
                role="option"
                :aria-selected="index === highlighted"
                :data-testid="`${props.test_id}-suggestion-${index}`"
                @mousedown.prevent
                @mousemove="highlighted = index"
                @click="addTag(suggestion)"
              >
                <span>{{ suggestion.name }}</span>
                <AdminBaseBadge
                  :variant="TYPE_VARIANT[suggestion.type]"
                  size="sm"
                >
                  {{ suggestion.type }}
                </AdminBaseBadge>
              </div>
            </slot>
          </template>
        </template>
        <p
          v-else
          class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
        >
          {{ t('common.no_tags_found') }}
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
    <p
      v-else-if="props.help"
      class="mt-1.5 text-xs text-slate-400 dark:text-slate-500"
    >
      {{ props.help }}
    </p>
  </div>
</template>
