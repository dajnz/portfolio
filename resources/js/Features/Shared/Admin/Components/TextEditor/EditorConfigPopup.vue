<!-- EditorConfigPopup — the TextEditor's shared config popup (B232):
     ONE popup at a time, fixed-anchored under the clicked toolbar
     button (the parent passes the viewport position). Five variants:
     the heading levels (Paragraph + H1–H6 with the active level
     checkmarked), the link URL row (input prefilled from the active
     link + Apply/Remove, Enter applies), the code language list (none
     + 17, the thin-scrollbar list), the callout type list (4 types —
     switching only, the [!] toolbar button owns the toggle-off) and
     the interactive 6×6 table-size grid (hover highlights the
     top-left region, the live rows × cols label follows; arrow keys
     move the hover and Enter inserts — the grid's OWN keydown
     listener, since it owns the hover state). -->
<script lang="ts">
export const CODE_LANGUAGES: Array<{ value: string; label: string }> = [
    { value: '', label: 'none' },
    { value: 'javascript', label: 'JavaScript' },
    { value: 'typescript', label: 'TypeScript' },
    { value: 'php', label: 'PHP' },
    { value: 'python', label: 'Python' },
    { value: 'java', label: 'Java' },
    { value: 'go', label: 'Go' },
    { value: 'rust', label: 'Rust' },
    { value: 'c', label: 'C' },
    { value: 'cpp', label: 'C++' },
    { value: 'css', label: 'CSS' },
    { value: 'html', label: 'HTML' },
    { value: 'json', label: 'JSON' },
    { value: 'bash', label: 'Bash' },
    { value: 'sql', label: 'SQL' },
    { value: 'ruby', label: 'Ruby' },
    { value: 'markdown', label: 'Markdown' },
];
</script>

<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { useI18n } from '@shared-cross/Composables/useI18n';

import { CALLOUT_TYPES } from '../../EditorExtensions/Callout';
import { useEditorTableGrid } from './useEditorTableGrid';
import type { EditorPopupMode } from './useEditorPopup';

const props = withDefaults(
    defineProps<{
        mode: EditorPopupMode;
        /** The fixed viewport anchor — `{left, top}` in px. */
        position: { left: number; top: number };
        heading_level?: number;
        /** The active link URL — copied into the input when the link
         *  row opens (the parent refreshes it from the editor). */
        link_href?: string;
        active_code_language?: string;
        active_callout_type?: string;
        test_id?: string;
    }>(),
    {
        heading_level: 0,
        link_href: '',
        active_code_language: '',
        active_callout_type: 'warning',
        test_id: 'text-editor',
    },
);

const emit = defineEmits<{
    'apply-heading': [value: string];
    'apply-link': [href: string];
    'remove-link': [];
    'select-language': [value: string];
    'select-callout': [value: string];
    'apply-table': [rows: number, cols: number];
}>();

const { t } = useI18n();

// ── The table-size grid (B232) — destructured so the template gets
//    top-level unwrapped refs; declared BEFORE the mode watcher below
//    (the immediate watch callback touches it). ──
const {
    size: tableGridSize,
    hover: tableHover,
    hoverFromCell,
    cellActive,
    setHover,
    move,
    reset,
} = useEditorTableGrid();

const rootEl = ref<HTMLElement | null>(null);

defineExpose({ rootElement: rootEl });

// The link row's URL input — seeded from the active link on open,
// focused on open.
const linkHref = ref('');
const linkInputEl = ref<HTMLInputElement | null>(null);

watch(
    () => props.mode,
    (mode) => {
        if (mode === 'link') {
            linkHref.value = props.link_href;
            nextTick(() => linkInputEl.value?.focus());
        }

        if (mode === 'table') {
            reset();
        }
    },
    { immediate: true },
);

const headingOptions = computed(() => [
    { value: '', label: t('admin.editor.paragraph') },
    ...Array.from({ length: 6 }, (_, index) => ({
        value: String(index + 1),
        label: t('admin.editor.heading_level', { level: index + 1 }),
    })),
]);

const calloutOptions = computed(() =>
    CALLOUT_TYPES.map((type) => ({
        value: type,
        label: t(`admin.editor.callout_${type}`),
    })),
);

function isHeadingActive(value: string): boolean {
    if (value === '') {
        return props.heading_level === 0;
    }

    return Number(value) === props.heading_level;
}

// The table mode owns its arrow/Enter keys — the popup holds the
// hover state (Escape is the parent's global listener, mode-agnostic).
function onPopupKeydownCapture(event: KeyboardEvent): void {
    if (props.mode !== 'table') {
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        emit('apply-table', tableHover.value.rows, tableHover.value.cols);
    } else if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
        event.preventDefault();
        move(event.key);
    }
}

onMounted(() => {
    document.addEventListener('keydown', onPopupKeydownCapture, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onPopupKeydownCapture, true);
});

function applyTableCell(cell: number): void {
    const { rows, cols } = hoverFromCell(cell);

    emit('apply-table', rows, cols);
}

function applyLink(): void {
    const href = linkHref.value.trim();

    if (href !== '') {
        emit('apply-link', href);
    }
}
</script>

<template>
  <div
    ref="rootEl"
    class="fixed z-dropdown w-max min-w-44 rounded-lg border border-slate-200 bg-white shadow-dropdown dark:border-slate-700 dark:bg-surface-dark-secondary"
    :style="{ left: `${props.position.left}px`, top: `${props.position.top}px` }"
    :data-testid="`${props.test_id}-popup`"
  >
    <template v-if="props.mode === 'heading'">
      <div
        class="w-44 p-1.5"
        role="listbox"
        :aria-label="t('admin.editor.heading_level_label')"
      >
        <button
          v-for="(option, index) in headingOptions"
          :key="option.value"
          type="button"
          class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-sm transition-colors"
          :class="
            isHeadingActive(option.value)
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
              : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
          "
          :aria-pressed="isHeadingActive(option.value)"
          :data-testid="`${props.test_id}-heading-option-${index}`"
          @click="emit('apply-heading', option.value)"
        >
          <span>{{ option.label }}</span>
          <Icon
            v-if="isHeadingActive(option.value)"
            class="h-4 w-4"
            icon="heroicons:check"
            aria-hidden="true"
          />
        </button>
      </div>
    </template>
    <template v-else-if="props.mode === 'link'">
      <div class="flex items-center gap-2 p-3">
        <input
          ref="linkInputEl"
          v-model="linkHref"
          type="url"
          class="h-9 w-56 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark-secondary px-2 text-sm outline-none focus:border-primary-500"
          :placeholder="t('admin.editor.link_placeholder')"
          :data-testid="`${props.test_id}-link-input`"
          @keydown.enter="applyLink"
        >
        <button
          type="button"
          class="select-none h-9 px-3 text-xs font-semibold rounded-md bg-primary-600 hover:bg-primary-700 text-white transition-colors"
          :data-testid="`${props.test_id}-link-apply`"
          @click="applyLink"
        >
          {{ t('admin.editor.apply_link') }}
        </button>
        <button
          v-if="props.link_href !== ''"
          type="button"
          class="select-none h-9 px-3 text-xs font-semibold rounded-md border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          :data-testid="`${props.test_id}-link-remove`"
          @click="emit('remove-link')"
        >
          {{ t('admin.editor.remove_link') }}
        </button>
      </div>
    </template>
    <template v-else-if="props.mode === 'language'">
      <div
        class="thin-scrollbar max-h-64 w-48 overflow-y-auto p-1.5"
        role="listbox"
        :aria-label="t('admin.editor.code_language')"
      >
        <button
          v-for="(option, index) in CODE_LANGUAGES"
          :key="option.value"
          type="button"
          class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-sm transition-colors"
          :class="
            props.active_code_language === option.value
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
              : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
          "
          :aria-pressed="props.active_code_language === option.value"
          :data-testid="`${props.test_id}-language-option-${index}`"
          @click="emit('select-language', option.value)"
        >
          <span>{{ option.label }}</span>
          <Icon
            v-if="props.active_code_language === option.value"
            class="h-4 w-4"
            icon="heroicons:check"
            aria-hidden="true"
          />
        </button>
      </div>
    </template>
    <template v-else-if="props.mode === 'callout'">
      <div
        class="w-44 p-1.5"
        role="listbox"
        :aria-label="t('admin.editor.callout')"
      >
        <button
          v-for="(option, index) in calloutOptions"
          :key="option.value"
          type="button"
          class="flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-sm transition-colors"
          :class="
            props.active_callout_type === option.value
              ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
              : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
          "
          :aria-pressed="props.active_callout_type === option.value"
          :data-testid="`${props.test_id}-callout-option-${index}`"
          @click="emit('select-callout', option.value)"
        >
          <span>{{ option.label }}</span>
          <Icon
            v-if="props.active_callout_type === option.value"
            class="h-4 w-4"
            icon="heroicons:check"
            aria-hidden="true"
          />
        </button>
      </div>
    </template>
    <template v-else>
      <!-- The table-size grid (B232): hover highlights the top-left
           region up to the hovered cell; the label shows the live
           rows × cols; click inserts and closes. -->
      <div
        class="p-3"
        role="grid"
        :aria-label="t('admin.editor.table_size')"
        :data-testid="`${props.test_id}-table-grid`"
      >
        <div class="grid grid-cols-6 gap-1">
          <button
            v-for="cell in tableGridSize * tableGridSize"
            :key="cell"
            type="button"
            class="h-4 w-4 rounded-sm transition-colors"
            :class="
              cellActive(cell)
                ? 'bg-primary-500'
                : 'bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600'
            "
            :aria-label="`${hoverFromCell(cell).rows} × ${hoverFromCell(cell).cols}`"
            :data-testid="`${props.test_id}-table-cell-${cell}`"
            @mouseenter="setHover(cell)"
            @click="applyTableCell(cell)"
          />
        </div>
        <p
          class="mt-2 text-center text-xs font-medium text-slate-500 dark:text-slate-400"
          :data-testid="`${props.test_id}-table-size-label`"
        >
          {{ tableHover.rows }} × {{ tableHover.cols }}
        </p>
      </div>
    </template>
  </div>
</template>
