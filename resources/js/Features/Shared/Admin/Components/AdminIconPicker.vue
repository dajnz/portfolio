// admin-section-components.md:251-283 — AdminIconPicker
<script setup lang="ts">
import type { IconifyJSON } from '@iconify/types';
import { Icon, type IconifyIcon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

type IconSet = 'brands' | 'engineering' | 'ui';
type UiCategory = 'Outline' | 'Solid' | 'Mini';

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

interface IconGroup {
    label: string | null;
    options: IconOption[];
}

const ENGINEERING_ICONS: ReadonlyArray<readonly [string, string]> = [
    ['academic-cap', 'Learning / Education'],
    ['archive-box', 'Architecture'],
    ['bolt', 'Performance'],
    ['chart-bar', 'Analytics'],
    ['check-badge', 'Quality'],
    ['circle-stack', 'Database'],
    ['cloud', 'Cloud / Deployment'],
    ['code-bracket', 'Development'],
    ['cog-8-tooth', 'Configuration'],
    ['cpu-chip', 'Hardware / Systems'],
    ['document-text', 'Documentation'],
    ['eye', 'Monitoring'],
    ['globe-alt', 'Web'],
    ['key', 'Authentication'],
    ['lock-closed', 'Security'],
    ['puzzle-piece', 'Integration'],
    ['rocket-launch', 'Launch / Startup'],
    ['server', 'Backend / Infrastructure'],
    ['shield-check', 'Testing / QA'],
    ['sparkles', 'Innovation'],
    ['swatch', 'Design / UI'],
    ['wrench-screwdriver', 'DevOps / Tooling'],
];

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        label?: string;
        iconSet?: IconSet;
        placeholder?: string;
        test_id?: string;
    }>(),
    {
        label: undefined,
        iconSet: 'ui',
        placeholder: undefined,
        test_id: 'icon-picker',
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

const { t } = useI18n();

const PANEL_WIDTH = 288;
const PANEL_EDGE_OFFSET = 8;
const setCache = new Map<IconSet, IconSetData>();

const isOpen = ref(false);
const isLoadingSet = ref(false);
const setData = ref<IconSetData | null>(null);
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

    const modulePromise =
        set === 'brands'
            ? import('@iconify-json/simple-icons/icons.json')
            : import('@iconify-json/heroicons/icons.json');

    modulePromise
        .then((module) => {
            // The vendored icon-set JSONs are too large for TS structural
            // inference — the dynamic import resolves to `{ default: {} }`.
            // IconifyJSON is the canonical shape of these files.
            const json = (module as { default: IconifyJSON }).default;
            const data: IconSetData = {
                width: json.width ?? 24,
                height: json.height ?? 24,
                icons: json.icons,
            };

            setCache.set(set, data);
            setData.value = data;
            isLoadingSet.value = false;
        })
        .catch(() => {
            setData.value = null;
            isLoadingSet.value = false;
        });
}

function uiCategoryOf(name: string): UiCategory {
    if (name.endsWith('-mini')) {
        return 'Mini';
    }

    if (name.endsWith('-solid')) {
        return 'Solid';
    }

    return 'Outline';
}

function stripUiSuffix(name: string): string {
    return name.replace(/-(solid|mini)$/, '');
}

function buildGroups(): IconGroup[] {
    const data = setData.value;

    if (data === null) {
        return [];
    }

    if (props.iconSet === 'brands') {
        return [
            {
                label: null,
                options: Object.keys(data.icons).map((name) => ({
                    value: `simple-icons:${name}`,
                    name,
                    label: name,
                })),
            },
        ];
    }

    if (props.iconSet === 'engineering') {
        return [
            {
                label: null,
                options: ENGINEERING_ICONS.map(([name, label]) => ({
                    value: `heroicons:${name}`,
                    name,
                    label,
                })),
            },
        ];
    }

    const grouped: Record<UiCategory, IconOption[]> = {
        Outline: [],
        Solid: [],
        Mini: [],
    };

    for (const name of Object.keys(data.icons)) {
        grouped[uiCategoryOf(name)].push({
            value: `heroicons:${name}`,
            name,
            label: stripUiSuffix(name),
        });
    }

    return (Object.entries(grouped) as Array<[UiCategory, IconOption[]]>)
        .filter(([, options]) => options.length > 0)
        .map(([label, options]) => ({ label, options }));
}

const groups = computed(() => buildGroups());

const allOptions = computed(() => groups.value.flatMap((group) => group.options));

const filteredGroups = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return groups.value;
    }

    return groups.value
        .map((group) => ({
            ...group,
            options: group.options.filter((option) => {
                const searchable = props.iconSet === 'engineering' ? option.name : option.label;

                return searchable.toLowerCase().includes(needle);
            }),
        }))
        .filter((group) => group.options.length > 0);
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
    const name = value.split(':').at(-1) ?? value;

    if (data === null) {
        return value;
    }

    const entry = data.icons[name];

    if (entry === undefined) {
        return value;
    }

    return {
        body: entry.body,
        width: data.width,
        height: data.height,
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
    loadSet(props.iconSet);

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
        closePanel();
    }
}

function onSearchKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
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

    if (props.modelValue !== null && !props.modelValue.startsWith('heroicons:')) {
        loadSet('brands');
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
          <template
            v-for="(group, groupIndex) in filteredGroups"
            :key="group.label ?? `group-${groupIndex}`"
          >
            <div
              v-if="group.label !== null"
              class="px-3 pt-3 pb-1 text-2xs font-bold uppercase tracking-wider text-slate-400"
            >
              {{ group.label }}
            </div>
            <div class="grid grid-cols-6 gap-1 p-2">
              <button
                v-for="option in group.options"
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
          </template>
          <p
            v-if="filteredGroups.length === 0"
            class="p-4 text-center text-sm text-slate-400"
          >
            {{ t('admin.icon_picker.no_icons_match') }}
          </p>
        </div>
      </div>
    </Teleport>
  </div>
</template>
