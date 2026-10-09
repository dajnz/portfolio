// admin-section-components.md:587-621 — AdminTechSkillPicker
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import AdminBaseBadge from './AdminBaseBadge.vue';
import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

interface TechSkill {
    id: number;
    name: string;
    icon: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: TechSkill[];
        label?: string;
        error?: string | null;
        required?: boolean;
        help?: string;
        placeholder?: string;
        singleSelect?: boolean;
        disabled?: boolean;
        /** Fixed container width (CSS length) — the controls row stops
         *  reflowing when pills are added (the projects filter uses
         *  500px; user request 2026-08-12). Capped at the available
         *  width (`max-width: 100%`) so narrow screens shrink it
         *  instead of overflowing. */
        width?: string;
        test_id?: string;
    }>(),
    {
        label: undefined,
        error: null,
        required: false,
        help: undefined,
        placeholder: undefined,
        singleSelect: false,
        disabled: false,
        width: undefined,
        test_id: 'tech-skill-picker',
    },
);

const emit = defineEmits<{
    'update:modelValue': [skills: TechSkill[]];
    select: [skill: TechSkill];
    remove: [skill: TechSkill];
}>();

const { t } = useI18n();
const { get } = useJsonApi();

const SKILLS_SEARCH_URL = '/api/skills/search';
const SEARCH_DEBOUNCE_MS = 300;

const query = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const hasError = ref(false);
const highlighted = ref(-1);
const suggestions = ref<TechSkill[]>([]);

const inputEl = ref<HTMLInputElement | null>(null);
const listboxId = useId();
const debounceTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
let activeRequest: AbortController | null = null;

const visibleSuggestions = computed(() =>
    suggestions.value.filter(
        (suggestion) => !props.modelValue.some((skill) => skill.id === suggestion.id),
    ),
);

function addSkill(skill: TechSkill): void {
    if (props.singleSelect) {
        emit('select', skill);
        query.value = '';
        highlighted.value = -1;
        isOpen.value = false;
        return;
    }

    emit('update:modelValue', [...props.modelValue, skill]);
    query.value = '';
    highlighted.value = -1;
    hasError.value = false;
    isLoading.value = false;

    nextTick(() => inputEl.value?.focus());
}

function removeSkill(skill: TechSkill): void {
    if (props.singleSelect) {
        emit('remove', skill);
        return;
    }

    emit(
        'update:modelValue',
        props.modelValue.filter((item) => item.id !== skill.id),
    );
}

function removeLastSkill(): void {
    const last = props.modelValue[props.modelValue.length - 1];

    if (last !== undefined) {
        removeSkill(last);
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
        const url = `${SKILLS_SEARCH_URL}?q=${encodeURIComponent(query.value)}`;
        const payload = await get<TechSkill[] | { data?: TechSkill[] }>(url, {
            signal: activeRequest.signal,
        });

        suggestions.value = Array.isArray(payload) ? payload : (payload.data ?? []);
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
                addSkill(suggestion);
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
                removeLastSkill();
            }
            break;
    }
}

function cleanup(): void {
    if (debounceTimeout.value !== null) {
        clearTimeout(debounceTimeout.value);
    }

    activeRequest?.abort();
    document.removeEventListener('click', onDocumentClick, true);
}

// A click outside the picker closes the dropdown AND clears the typed
// query (ADR-0024) — the picked tags stay, only the search text is reset.
// The listener runs in the CAPTURE phase — before any element's own
// click handler — so the check always sees the click target still
// attached: adding a skill re-filters the suggestions and removes the
// clicked row before the click finishes bubbling, and a bubble-phase
// check would see the detached row as an outside click, closing the
// dropdown after every added tag (ADR-0024).
function onDocumentClick(event: MouseEvent): void {
    if (rootEl.value !== null && rootEl.value.contains(event.target as Node) === false) {
        isOpen.value = false;
        highlighted.value = -1;
        query.value = '';
    }
}

const rootEl = ref<HTMLElement | null>(null);

onMounted(() => {
    document.addEventListener('click', onDocumentClick, true);
});

onBeforeUnmount(cleanup);
</script>

<template>
  <div
    ref="rootEl"
    class="max-w-full"
    :data-testid="`${props.test_id}-root`"
  >
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
        class="flex min-h-10 w-full flex-wrap items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark-secondary px-2 py-1.5 focus-within:border-primary-500"
        :class="{
          'border-danger-300 dark:border-danger-700': props.error !== null,
          'opacity-45 pointer-events-none': props.disabled,
        }"
        :style="
          props.width !== undefined
            ? { width: props.width, maxWidth: '100%' }
            : undefined
        "
        :data-testid="props.test_id"
      >
        <AdminBaseBadge
          v-for="(skill, index) in props.modelValue"
          :key="skill.id"
          variant="primary"
          removable
          :icon="skill.icon"
          :test_id="`${props.test_id}-pill-${index}`"
          @remove="removeSkill(skill)"
        >
          <slot
            name="pill"
            :skill="skill"
            :remove="() => removeSkill(skill)"
          >
            {{ skill.name }}
          </slot>
        </AdminBaseBadge>
        <input
          ref="inputEl"
          type="text"
          class="flex-1 min-w-[8rem] bg-transparent text-sm outline-none placeholder-slate-400 dark:placeholder-slate-500"
          :value="query"
          :placeholder="props.placeholder ?? t('admin.skills.search_placeholder')"
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
        class="admin-autocomplete-panel absolute top-full left-0 right-0 mt-1 rounded-lg z-dropdown overflow-hidden"
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
          {{ t('admin.skills.could_not_load') }}
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
            :key="suggestion.id"
          >
            <slot
              name="suggestion"
              :skill="suggestion"
              :select="() => addSkill(suggestion)"
            >
              <div
                :id="`${listboxId}-option-${index}`"
                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950 transition-colors cursor-pointer"
                :class="{
                  'bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300':
                    index === highlighted,
                }"
                role="option"
                :aria-selected="index === highlighted"
                :data-testid="`${props.test_id}-suggestion-${index}`"
                @mousedown.prevent
                @mousemove="highlighted = index"
                @click="addSkill(suggestion)"
              >
                <Icon
                  v-if="suggestion.icon"
                  class="flex-shrink-0 w-4 h-4"
                  :icon="suggestion.icon"
                  aria-hidden="true"
                />
                <span>{{ suggestion.name }}</span>
              </div>
            </slot>
          </template>
        </template>
        <p
          v-else
          class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
        >
          {{ t('admin.skills.no_skills') }}
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
