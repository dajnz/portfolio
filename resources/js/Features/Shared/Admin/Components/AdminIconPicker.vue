// admin-section-components.md:251-283 — AdminIconPicker (restricted to
// the strict icon manifests — brands.json for skills, technical.json
// (mixed sets) for everything else, see scripts/build-icon-subsets.mjs)
<script setup lang="ts">
import type { IconifyJSON } from '@iconify/types';
import { Icon, type IconifyIcon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

type IconSet = 'brands' | 'technical';

interface IconSetData {
    width: number;
    height: number;
    icons: Record<string, { body: string }>;
}

interface IconOption {
    value: string;
    name: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        label?: string;
        icon_set?: IconSet;
        placeholder?: string;
        error?: string | null;
        required?: boolean;
        test_id?: string;
    }>(),
    {
        label: undefined,
        icon_set: 'technical',
        placeholder: undefined,
        error: null,
        required: false,
        test_id: 'icon-picker',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

const { t } = useI18n();

const PANEL_WIDTH = 288;
const PANEL_EDGE_OFFSET = 8;
const setCache = new Map<IconSet, Record<string, IconSetData>>();

const isOpen = ref(false);
const isLoadingSet = ref(false);
const setData = ref<Record<string, IconSetData> | null>(null);
const query = ref('');
const panelStyle = ref({ top: '0px', left: '0px' });
const panelId = useId();

const triggerWrapEl = ref<HTMLElement | null>(null);
const triggerEl = ref<HTMLButtonElement | null>(null);
const panelEl = ref<HTMLElement | null>(null);
const searchEl = ref<HTMLInputElement | null>(null);

function loadSet(set: IconSet): void {
    const cached = setCache.get(set);

    if (cached !== undefined) {
        setData.value = cached;
        return;
    }

    isLoadingSet.value = true;

    // The picker offers only the icons listed in the strict manifests
    // (brands.json — devicon for skills; technical.json — the mixed
    // heroicons/simple-icons/... sets). The generated subsets are tiny,
    // so both technical sets can load together.
    const modulePromises =
        set === 'brands'
            ? [import('@/iconSubsets/devicon.json')]
            : [import('@/iconSubsets/heroicons.json'), import('@/iconSubsets/simple-icons.json')];

    Promise.all(modulePromises)
        .then((modules) => {
            const data: Record<string, IconSetData> = {};

            for (const module of modules) {
                // The vendored icon-set JSONs are too large for TS
                // structural inference — the dynamic import resolves to
                // `{ default: {} }`. IconifyJSON is the canonical shape.
                const json = (module as { default: IconifyJSON }).default;
                data[json.prefix] = {
                    width: json.width ?? 24,
                    height: json.height ?? 24,
                    icons: json.icons,
                };
            }

            setCache.set(set, data);
            setData.value = data;
            isLoadingSet.value = false;
        })
        .catch(() => {
            setData.value = null;
            isLoadingSet.value = false;
        });
}

function buildOptions(): IconOption[] {
    const data = setData.value;

    if (data === null) {
        return [];
    }

    return Object.entries(data).flatMap(([prefix, set]) =>
        Object.keys(set.icons).map((name) => ({
            value: `${prefix}:${name}`,
            name,
            label: name,
        })),
    );
}

const allOptions = computed(() => buildOptions());

const filteredOptions = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return allOptions.value;
    }

    return allOptions.value.filter((option) => option.label.toLowerCase().includes(needle));
});

const selectedLabel = computed(() => {
    if (props.modelValue === null) {
        return '';
    }

    const known = allOptions.value.find((option) => option.value === props.modelValue);

    if (known !== undefined) {
        return known.label;
    }

    return props.modelValue.split(':').at(-1) ?? props.modelValue;
});

function iconFor(value: string): string | IconifyIcon {
    const data = setData.value;
    const [prefix, name] = value.split(':');

    if (data === undefined || data === null || prefix === undefined || name === undefined) {
        return value;
    }

    const entry = data[prefix]?.icons[name];

    if (entry === undefined) {
        return value;
    }

    return {
        body: entry.body,
        width: data[prefix].width,
        height: data[prefix].height,
    };
}

function positionPanel(): void {
    const rect = triggerEl.value?.getBoundingClientRect();

    if (rect === undefined) {
        return;
    }

    const left = Math.max(
        PANEL_EDGE_OFFSET,
        Math.min(rect.left, window.innerWidth - PANEL_WIDTH - PANEL_EDGE_OFFSET),
    );

    panelStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${left}px`,
    };
}

function openPanel(): void {
    isOpen.value = true;
    query.value = '';
    positionPanel();
    loadSet(props.icon_set);

    nextTick(() => searchEl.value?.focus());
}

function closePanel(): void {
    isOpen.value = false;
}

function togglePanel(): void {
    if (isOpen.value) {
        closePanel();
        return;
    }

    openPanel();
}

function selectIcon(option: IconOption): void {
    if (option.value === props.modelValue) {
        return;
    }

    emit('update:modelValue', option.value);
    closePanel();
}

function onTriggerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        // Stop the bubble while the panel is open — the global Escape
        // shortcut (cancel inline editing) must not fire on the first
        // press. With the panel closed the Escape falls through to the
        // global cancel.
        if (isOpen.value) {
            event.stopPropagation();
        }

        closePanel();
    }
}

function onSearchKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        // Stop the bubble — the global Escape shortcut (cancel inline
        // editing) must not fire while the panel is open.
        event.stopPropagation();
        closePanel();
        searchEl.value?.blur();
    }
}

function onDocumentClick(event: MouseEvent): void {
    if (!isOpen.value) {
        return;
    }

    const target = event.target as Node;
    const insidePanel = panelEl.value?.contains(target) ?? false;
    const insideTrigger = triggerWrapEl.value?.contains(target) ?? false;

    if (!insidePanel && !insideTrigger) {
        closePanel();
    }
}

function onWindowScroll(): void {
    if (isOpen.value) {
        closePanel();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('scroll', onWindowScroll);

    if (props.modelValue !== null) {
        loadSet(props.modelValue.startsWith('devicon:') ? 'brands' : 'technical');
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('scroll', onWindowScroll);
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
    <div ref="triggerWrapEl">
      <button
        ref="triggerEl"
        type="button"
        class="flex w-full items-center justify-between gap-2 h-10 px-3 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
        :class="{
          'border-primary-500 ring-[3px] ring-primary-100 dark:ring-primary-900': isOpen,
        }"
        :aria-haspopup="'listbox'"
        :aria-expanded="isOpen"
        :aria-required="props.required || undefined"
        :aria-controls="isOpen ? panelId : undefined"
        :data-testid="props.test_id"
        @click="togglePanel"
        @keydown="onTriggerKeydown"
      >
        <span
          v-if="props.modelValue !== null"
          class="flex flex-1 items-center gap-2 truncate"
        >
          <Icon
            class="flex-shrink-0 w-5 h-5"
            :icon="iconFor(props.modelValue)"
            aria-hidden="true"
          />
          <span class="truncate">{{ selectedLabel }}</span>
        </span>
        <span
          v-else
          class="flex-1 truncate text-slate-400"
        >
          {{ props.placeholder ?? t('admin.icon_picker.select_placeholder') }}
        </span>
        <Icon
          class="flex-shrink-0 w-5 h-5 transition-transform"
          :class="{ 'rotate-180': isOpen }"
          icon="heroicons:chevron-down"
          aria-hidden="true"
        />
      </button>
    </div>
    <Teleport to="body">
      <div
        v-if="isOpen"
        :id="panelId"
        ref="panelEl"
        class="fixed w-72 max-h-96 bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 rounded-lg shadow-dropdown z-dropdown flex flex-col overflow-hidden"
        :style="panelStyle"
        :data-testid="`${props.test_id}-panel`"
      >
        <input
          ref="searchEl"
          v-model="query"
          type="search"
          class="h-11 shrink-0 appearance-none rounded-none px-3.5 text-sm bg-transparent border-b border-slate-200 dark:border-slate-700 outline-none placeholder-slate-400 dark:placeholder-slate-500"
          :placeholder="t('admin.icon_picker.search_placeholder')"
          :data-testid="`${props.test_id}-search`"
          @keydown="onSearchKeydown"
        >
        <div
          v-if="isLoadingSet"
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
          v-else
          class="thin-scrollbar flex-1 min-h-0 overflow-y-auto"
        >
          <div class="grid grid-cols-6 gap-1 p-2">
            <button
              v-for="option in filteredOptions"
              :key="option.value"
              type="button"
              class="flex flex-col items-center gap-1 p-1.5 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-950 transition-colors"
              :class="{
                'bg-primary-50 dark:bg-primary-950 ring-1 ring-primary-500':
                  option.value === props.modelValue,
              }"
              :data-testid="`${props.test_id}-icon-${option.value}`"
              @click="selectIcon(option)"
            >
              <Icon
                class="w-6 h-6"
                :icon="iconFor(option.value)"
                aria-hidden="true"
              />
              <span
                class="text-[0.55rem] text-slate-400 truncate w-full text-center"
              >
                {{ option.label }}
              </span>
            </button>
          </div>
          <p
            v-if="filteredOptions.length === 0"
            class="p-4 text-center text-sm text-slate-400"
          >
            {{ t('admin.icon_picker.no_icons_match') }}
          </p>
        </div>
      </div>
    </Teleport>
    <p
      v-if="props.error !== null && props.error !== undefined"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400"
      role="alert"
      :data-testid="`${props.test_id}-error`"
    >
      {{ props.error }}
    </p>
  </div>
</template>
