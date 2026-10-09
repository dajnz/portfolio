<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { usePage } from '@inertiajs/vue3';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface LocaleOption {
    code: string;
    native_name: string;
}

interface LanguageSelectorClasses {
    button?: string;
    dropdown?: string;
    option?: string;
}

/** Fallback when the `supported_locales` setting is not (yet) shared. */
const DEFAULT_SUPPORTED_LOCALES: LocaleOption[] = [
    { code: 'en', native_name: 'English' },
    { code: 'ru', native_name: 'Русский' },
];

const props = withDefaults(
    defineProps<{
        active_locale?: string;
        supported_locales?: LocaleOption[];
        classes?: LanguageSelectorClasses;
        track_locale_changes?: boolean;
        test_id?: string;
    }>(),
    {
        active_locale: undefined,
        supported_locales: undefined,
        classes: undefined,
        track_locale_changes: false,
        test_id: 'language-selector',
    },
);

const { t, setLocale } = useI18n();
const analytics = useAnalytics();

// The public header renders this component without props — the active
// locale then resolves from the page's shared `locale` prop. Must stay
// REACTIVE: the component tree survives Inertia visits, so a value
// captured once at setup would keep the previous visit's locale and
// break every switch after the first one (the trigger would show the old
// locale and selecting the "new" locale early-returns as unchanged).
const page = usePage<{ locale?: string }>();
const pageLocale = computed(() => page.props?.locale);
const resolvedLocale = computed(() => props.active_locale ?? pageLocale.value ?? 'en');
const resolvedOptions = computed(() =>
    props.supported_locales && props.supported_locales.length > 0
        ? props.supported_locales
        : DEFAULT_SUPPORTED_LOCALES,
);

// The trigger carries the current language in its accessible name (the
// visible "EN"/"RU" text is overridden by `aria-label`) so switching to
// the selector announces the active language.
const activeNativeName = computed(
    () =>
        resolvedOptions.value.find((option) => option.code === resolvedLocale.value)?.native_name ??
        resolvedLocale.value.toUpperCase(),
);
const triggerLabel = computed(() => `${t('nav.switch_language')}: ${activeNativeName.value}`);

const isOpen = ref(false);
const highlighted = ref(-1);
const controlEl = ref<HTMLElement | null>(null);
const triggerEl = ref<HTMLButtonElement | null>(null);

function selectLocale(option: LocaleOption): void {
    if (option.code === resolvedLocale.value) {
        close();
        return;
    }

    if (props.track_locale_changes) {
        // Public surface only.
        analytics.track('cta_click_language', { locale: option.code });
    }

    close();
    setLocale(option.code);
}

function open(): void {
    isOpen.value = true;
    highlighted.value = resolvedOptions.value.findIndex(
        (option) => option.code === resolvedLocale.value,
    );
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
    const count = resolvedOptions.value.length;
    if (count === 0) {
        return;
    }

    highlighted.value = (highlighted.value + direction + count) % count;
}

function onKeydown(event: KeyboardEvent): void {
    if (!isOpen.value) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
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
        case 'Enter': {
            event.preventDefault();
            const option = resolvedOptions.value[highlighted.value];
            if (option !== undefined) {
                selectLocale(option);
            }
            break;
        }
        case 'Escape':
            event.preventDefault();
            close();
            triggerEl.value?.focus();
            break;
        case 'Tab':
            close();
            break;
    }
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
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
  <div
    v-if="resolvedOptions.length > 1"
    ref="controlEl"
    class="language-selector"
    :data-testid="props.test_id"
    data-component="language-selector"
    :data-state="isOpen ? 'open' : 'closed'"
    @keydown="onKeydown"
  >
    <button
      ref="triggerEl"
      type="button"
      class="language-selector__button"
      :class="props.classes?.button"
      :aria-label="triggerLabel"
      :aria-haspopup="'listbox'"
      :aria-expanded="isOpen"
      :data-testid="`${props.test_id}-button`"
      @click="toggleOpen"
    >
      {{ resolvedLocale.toUpperCase() }}
    </button>
    <div
      v-if="isOpen"
      class="language-selector__dropdown"
      :class="props.classes?.dropdown"
      role="listbox"
      :aria-label="t('nav.switch_language')"
      :data-testid="`${props.test_id}-dropdown`"
    >
      <div
        v-for="(option, index) in resolvedOptions"
        :id="`${props.test_id}-option-${index}`"
        :key="option.code"
        class="language-selector__option"
        :class="[
          { 'is-highlighted': index === highlighted, 'is-active': option.code === resolvedLocale },
          props.classes?.option,
        ]"
        role="option"
        :aria-selected="option.code === resolvedLocale"
        :aria-current="option.code === resolvedLocale ? 'true' : undefined"
        :data-testid="`${props.test_id}-option-${option.code}`"
        @mousemove="highlighted = index"
        @click="selectLocale(option)"
      >
        <span class="language-selector__option-name">{{ option.native_name }}</span>
        <Icon
          v-if="option.code === resolvedLocale"
          class="language-selector__option-check"
          icon="heroicons:check"
          aria-hidden="true"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.language-selector {
    position: relative;
    display: inline-flex;
}

.language-selector__button {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 var(--space-sm);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    cursor: pointer;
    font-size: var(--font-size-sm);
    font-weight: var(--font-semibold);
    color: var(--color-text-secondary);
    transition: all var(--transition-fast);
}

.language-selector__button:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.language-selector__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    min-width: 140px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    z-index: var(--z-dropdown);
    padding: var(--space-xs);
}

.language-selector__option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-md);
    padding: var(--space-sm) var(--space-md);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-sm);
    color: var(--color-text-primary);
    cursor: pointer;
    transition: background var(--transition-fast);
}

.language-selector__option:hover,
.language-selector__option.is-highlighted {
    background: var(--color-accent-muted);
}

.language-selector__option.is-active {
    font-weight: var(--font-semibold);
}

.language-selector__option-check {
    width: 1em;
    height: 1em;
    color: var(--color-accent);
}
</style>
