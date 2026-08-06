<!-- TechSkillAutocomplete (public-section-components.md:529-556) — styling-agnostic base autocomplete for tech-skill picking, used by the admin picker and public tag filters. Fetches `GET /api/skills/search?q=...`; BEM by default, Tailwind via the `classes` prop. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, nextTick, onBeforeUnmount, ref, useId } from 'vue';

interface TechSkill {
    id: number;
    name: string;
    icon: string;
}

interface TechSkillAutocompleteClasses {
    input?: string;
    dropdown?: string;
    suggestion?: string;
    pill?: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: TechSkill[];
        placeholder?: string;
        disabled?: boolean;
        singleSelect?: boolean;
        classes?: TechSkillAutocompleteClasses;
        test_id?: string;
    }>(),
    {
        placeholder: undefined,
        disabled: false,
        singleSelect: false,
        classes: undefined,
        test_id: 'skill-autocomplete',
    },
);

const emit = defineEmits<{ 'update:modelValue': [skills: TechSkill[]] }>();

const { t } = useI18n();

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

function isSelected(skill: TechSkill): boolean {
    return props.modelValue.some((selected) => selected.id === skill.id);
}

const visibleSuggestions = computed(() => suggestions.value.filter((skill) => !isSelected(skill)));

function addSkill(skill: TechSkill): void {
    emit('update:modelValue', [...props.modelValue, skill]);
    query.value = '';
    highlighted.value = -1;
    hasError.value = false;
    isLoading.value = false;
    isOpen.value = false;

    if (props.singleSelect) {
        inputEl.value?.blur();
    } else {
        nextTick(() => inputEl.value?.focus());
    }
}

function removeSkill(skill: TechSkill): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((selected) => selected.id !== skill.id),
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

    if (query.value.trim() === '') {
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
    }, SEARCH_DEBOUNCE_MS);
}

async function fetchSuggestions(): Promise<void> {
    activeRequest?.abort();
    activeRequest = new AbortController();

    try {
        const url = `${SKILLS_SEARCH_URL}?q=${encodeURIComponent(query.value.trim())}`;
        const response = await fetch(url, {
            signal: activeRequest.signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Skill search request failed: ${response.status}`);
        }

        const payload = (await response.json()) as TechSkill[];
        suggestions.value = Array.isArray(payload) ? payload : [];
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
            const skill = visibleSuggestions.value[highlighted.value];

            if (skill !== undefined) {
                addSkill(skill);
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
}

onBeforeUnmount(cleanup);
</script>

<template>
  <div
    class="skill-autocomplete"
    :class="{ 'is-disabled': props.disabled }"
    :data-testid="props.test_id"
  >
    <div
      class="skill-autocomplete__pills"
      :class="props.classes?.input"
    >
      <slot
        v-for="skill in props.modelValue"
        name="pill"
        :skill="skill"
        :remove="() => removeSkill(skill)"
      >
        <span
          :key="skill.id"
          class="skill-autocomplete__pill"
          :class="props.classes?.pill"
          :data-testid="`${props.test_id}-pill-${skill.id}`"
        >
          <Icon
            v-if="skill.icon"
            class="skill-autocomplete__pill-icon"
            :icon="skill.icon"
            aria-hidden="true"
          />
          {{ skill.name }}
          <button
            type="button"
            class="skill-autocomplete__pill-remove"
            :aria-label="`${t('common.remove')} ${skill.name}`"
            :data-testid="`${props.test_id}-pill-remove-${skill.id}`"
            @click="removeSkill(skill)"
          >
            <Icon
              icon="heroicons:x-mark"
              aria-hidden="true"
            />
          </button>
        </span>
      </slot>
      <input
        ref="inputEl"
        type="text"
        class="skill-autocomplete__input"
        :class="props.classes?.input"
        :value="query"
        :placeholder="props.placeholder ?? t('skills.search_placeholder')"
        :disabled="props.disabled"
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
    <div
      v-if="isOpen"
      :id="listboxId"
      class="skill-autocomplete__dropdown"
      :class="props.classes?.dropdown"
      role="listbox"
      :aria-label="t('skills.search_placeholder')"
      :data-testid="`${props.test_id}-dropdown`"
    >
      <div
        v-if="isLoading"
        class="skill-autocomplete__skeletons"
      >
        <span
          v-for="row in 3"
          :key="row"
          class="skill-autocomplete__skeleton"
        />
      </div>
      <div
        v-else-if="hasError"
        class="skill-autocomplete__error"
      >
        {{ t('skills.could_not_load_skills') }}
        <button
          type="button"
          class="skill-autocomplete__retry"
          :data-testid="`${props.test_id}-retry`"
          @click="retry"
        >
          {{ t('common.retry') }}
        </button>
      </div>
      <template v-else-if="visibleSuggestions.length > 0">
        <slot
          v-for="(skill, index) in visibleSuggestions"
          name="suggestion"
          :skill="skill"
          :select="() => addSkill(skill)"
        >
          <div
            :id="`${listboxId}-option-${index}`"
            :key="skill.id"
            class="skill-autocomplete__suggestion"
            :class="[{ 'is-highlighted': index === highlighted }, props.classes?.suggestion]"
            role="option"
            :aria-selected="index === highlighted"
            :data-testid="`${props.test_id}-suggestion-${index}`"
            @mousedown.prevent
            @mousemove="highlighted = index"
            @click="addSkill(skill)"
          >
            <Icon
              v-if="skill.icon"
              class="skill-autocomplete__suggestion-icon"
              :icon="skill.icon"
              aria-hidden="true"
            />
            <span>{{ skill.name }}</span>
          </div>
        </slot>
      </template>
      <p
        v-else-if="query.trim() === ''"
        class="skill-autocomplete__empty"
      >
        {{ t('skills.start_typing') }}
      </p>
      <p
        v-else
        class="skill-autocomplete__empty"
      >
        {{ t('skills.no_skills_found') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.skill-autocomplete {
    position: relative;
    max-width: 400px;
}

.skill-autocomplete.is-disabled {
    opacity: 0.45;
    pointer-events: none;
}

.skill-autocomplete__pills {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-xs) var(--space-md);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--input-radius);
    transition: var(--input-transition);
}

.skill-autocomplete__pills:focus-within {
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgb(30 122 91 / 18%);
}

.skill-autocomplete__pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35em;
    padding: 0.15em 0.5em;
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    color: var(--color-text-secondary);
    background: var(--color-bg-secondary);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-full);
    white-space: nowrap;
}

.skill-autocomplete__pill-icon {
    width: 1em;
    height: 1em;
}

.skill-autocomplete__pill-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.1em;
    height: 1.1em;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--color-text-tertiary);
    cursor: pointer;
    border-radius: var(--radius-full);
    transition: color var(--transition-fast);
}

.skill-autocomplete__pill-remove:hover {
    color: var(--color-error);
}

.skill-autocomplete__pill-remove .iconify {
    width: 1em;
    height: 1em;
}

.skill-autocomplete__input {
    flex: 1;
    min-width: 160px;
    height: var(--input-height);
    border: none;
    background: transparent;
    font-size: var(--input-font-size);
    font-family: var(--font-sans);
    color: var(--color-text-primary);
}

.skill-autocomplete__input::placeholder {
    color: var(--color-text-tertiary);
}

.skill-autocomplete__input:focus {
    outline: none;
}

.skill-autocomplete__dropdown {
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

.skill-autocomplete__skeletons {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    padding: var(--space-md);
}

.skill-autocomplete__skeleton {
    height: 1.5em;
    border-radius: var(--radius-sm);
    background: linear-gradient(
        90deg,
        var(--color-bg-secondary) 25%,
        var(--color-bg-tertiary) 50%,
        var(--color-bg-secondary) 75%
    );
    background-size: 200% 100%;
    animation: skill-autocomplete-shimmer 1.2s infinite;
}

@keyframes skill-autocomplete-shimmer {
    from {
        background-position: 200% 0;
    }

    to {
        background-position: -200% 0;
    }
}

.skill-autocomplete__suggestion {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-primary);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.skill-autocomplete__suggestion:hover,
.skill-autocomplete__suggestion.is-highlighted {
    background: var(--color-accent-muted);
}

.skill-autocomplete__suggestion-icon {
    width: 1.1em;
    height: 1.1em;
    color: var(--color-text-tertiary);
    flex-shrink: 0;
}

.skill-autocomplete__empty {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.skill-autocomplete__error {
    padding: var(--space-lg);
    text-align: center;
    font-size: var(--font-size-sm);
    color: var(--color-error);
}

.skill-autocomplete__retry {
    background: none;
    border: none;
    color: var(--color-accent);
    font-weight: var(--font-semibold);
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
