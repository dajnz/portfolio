<!-- AdminDatePicker (admin-section-components.md, ADR-0024) — calendar date
     picker for admin forms. The field is a READ-ONLY display of the
     value in the RU format `dd.mm.yyyy` (no locale switching — the user
     requested the RU format no matter the selected locale) with a clear
     (x) button when a value is set; clicking anywhere on the field opens
     the calendar popup for picking. Manual digit entry is NOT possible —
     the server-side `date`/`after` rules never see hand-typed garbage.
     The `modelValue` stays ISO (`YYYY-MM-DD`, `''` = empty — ongoing).
     Calendar: Monday-first week grid, prev/next month navigation, the
     selected day highlighted, today outlined; Escape closes, Arrow keys
     move the focus, Enter picks. The month+year heading opens a decade
     year grid (prev/next then shift the decade); picking a year returns
     to the day grid. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type ViewMode = 'days' | 'years';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        label?: string;
        error?: string | null;
        required?: boolean;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        label: undefined,
        error: null,
        required: false,
        disabled: false,
        test_id: 'date-picker',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { locale, t } = useI18n();

const WEEKDAY_LABELS = new Intl.DateTimeFormat(locale.value, { weekday: 'short' });
const MONTH_LABEL = new Intl.DateTimeFormat(locale.value, { month: 'long' });
const YEAR_LABEL = new Intl.DateTimeFormat(locale.value, { year: 'numeric' });

const isOpen = ref(false);
// The month currently shown in the calendar (not the selected value).
const viewDate = ref(selectedDate() ?? new Date());
const focusedDay = ref<Date | null>(selectedDate() ?? null);
const rootEl = ref<HTMLElement | null>(null);
// The calendar panel's level: the day grid or the year grid. The heading
// click toggles between them (ADR-0024: years must be pickable, not only
// months/days).
const viewMode = ref<ViewMode>('days');

const displayValue = computed(() => toDisplay(props.modelValue));

const labelText = computed(() => props.label ?? '');
// "April (4) 2026" — the 1-based month number next to the name (ADR-0024).
const monthTitle = computed(
    () =>
        `${MONTH_LABEL.format(viewDate.value)} (${viewDate.value.getMonth() + 1}) ${YEAR_LABEL.format(viewDate.value)}`,
);
const yearRangeTitle = computed(
    () => `${yearRangeStart.value} — ${yearRangeStart.value + YEAR_RANGE_SIZE - 1}`,
);
const weekdays = computed(() =>
    Array.from({ length: 7 }, (_, index) => WEEKDAY_LABELS.format(dayOfWeek(index))),
);

const YEAR_RANGE_SIZE = 10;

// The decade the year grid starts at (e.g. 2020 for 2026).
const yearRangeStart = computed(
    () => Math.floor(viewDate.value.getFullYear() / YEAR_RANGE_SIZE) * YEAR_RANGE_SIZE,
);

const years = computed(() =>
    Array.from({ length: YEAR_RANGE_SIZE }, (_, index) => yearRangeStart.value + index),
);

const gridCells = computed(() => {
    const year = viewDate.value.getFullYear();
    const month = viewDate.value.getMonth();
    const leading = mondayIndex(new Date(year, month, 1));
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells: Array<Date | null> = [];

    for (let i = 0; i < leading; i++) {
        cells.push(null);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(new Date(year, month, day));
    }

    return cells;
});

function selectedDate(): Date | null {
    if (props.modelValue === '') {
        return null;
    }

    const [year, month, day] = props.modelValue.split('-').map(Number);

    if (year === undefined || month === undefined || day === undefined) {
        return null;
    }

    return new Date(year, month - 1, day);
}

function toDisplay(iso: string): string {
    if (iso === '') {
        return '';
    }

    const [year, month, day] = iso.split('-');

    if (year === undefined || month === undefined || day === undefined) {
        return '';
    }

    return `${day}.${month}.${year}`;
}

function mondayIndex(date: Date): number {
    return (date.getDay() + 6) % 7;
}

function dayOfWeek(index: number): Date {
    // 2026-08-03 is a Monday — the reference for the weekday names.
    return new Date(2026, 7, 3 + index);
}

function isoOf(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function isSelected(date: Date): boolean {
    return props.modelValue === isoOf(date);
}

function isToday(date: Date): boolean {
    const today = new Date();

    return isoOf(date) === isoOf(today);
}

function isFocused(date: Date): boolean {
    return focusedDay.value !== null && isoOf(focusedDay.value) === isoOf(date);
}

function isFocusedYear(year: number): boolean {
    return focusedDay.value !== null && focusedDay.value.getFullYear() === year;
}

function open(): void {
    if (props.disabled) {
        return;
    }

    viewDate.value = selectedDate() ?? new Date();
    focusedDay.value = selectedDate() ?? new Date();
    viewMode.value = 'days';
    isOpen.value = true;
}

function pick(date: Date): void {
    emit('update:modelValue', isoOf(date));
    isOpen.value = false;
}

function clear(): void {
    emit('update:modelValue', '');
    isOpen.value = false;
}

function shiftMonth(offset: number): void {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + offset, 1);
}

// Prev/next on the heading level: months on the day grid, decades on the
// year grid (the year range is one decade wide).
function shift(offset: number): void {
    if (viewMode.value === 'years') {
        viewDate.value = new Date(
            viewDate.value.getFullYear() + offset * YEAR_RANGE_SIZE,
            viewDate.value.getMonth(),
            1,
        );
        focusedDay.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);

        return;
    }

    shiftMonth(offset);
}

function toggleYearView(): void {
    if (viewMode.value === 'years') {
        viewMode.value = 'days';

        return;
    }

    focusedDay.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
    viewMode.value = 'years';
}

function pickYear(year: number): void {
    viewDate.value = new Date(year, viewDate.value.getMonth(), 1);
    focusedDay.value = new Date(year, viewDate.value.getMonth(), 1);
    viewMode.value = 'days';
}

function moveFocus(dayOffset: number): void {
    const base =
        focusedDay.value ?? new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
    const next = new Date(base.getFullYear(), base.getMonth(), base.getDate() + dayOffset);

    focusedDay.value = next;
    viewDate.value = new Date(next.getFullYear(), next.getMonth(), 1);
}

function moveFocusYear(yearOffset: number): void {
    const base =
        focusedDay.value ?? new Date(viewDate.value.getFullYear(), viewDate.value.getMonth(), 1);
    const next = new Date(base.getFullYear() + yearOffset, viewDate.value.getMonth(), 1);

    focusedDay.value = next;
    viewDate.value = new Date(next.getFullYear(), next.getMonth(), 1);
}

function onKeydown(event: KeyboardEvent): void {
    if (viewMode.value === 'years') {
        onYearsKeydown(event);

        return;
    }

    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            isOpen.value = false;
            break;
        case 'ArrowLeft':
            event.preventDefault();
            moveFocus(-1);
            break;
        case 'ArrowRight':
            event.preventDefault();
            moveFocus(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            moveFocus(-7);
            break;
        case 'ArrowDown':
            event.preventDefault();
            moveFocus(7);
            break;
        case 'Enter':
        case ' ':
            event.preventDefault();
            if (focusedDay.value !== null) {
                pick(focusedDay.value);
            }
            break;
    }
}

function onYearsKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            isOpen.value = false;
            break;
        case 'ArrowLeft':
            event.preventDefault();
            moveFocusYear(-1);
            break;
        case 'ArrowRight':
            event.preventDefault();
            moveFocusYear(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            moveFocusYear(-5);
            break;
        case 'ArrowDown':
            event.preventDefault();
            moveFocusYear(5);
            break;
        case 'Enter':
        case ' ':
            event.preventDefault();
            if (focusedDay.value !== null) {
                pickYear(focusedDay.value.getFullYear());
            }
            break;
    }
}

// A click outside the picker closes the calendar. The listener runs in
// the CAPTURE phase — before any element's own click handler — so the
// check always sees the click target still attached: picking a year
// re-renders the grid and removes the clicked button before the click
// finishes bubbling, and a bubble-phase check would see the detached
// target as an outside click, closing the calendar right after the year
// was picked (ADR-0024).
function onDocumentClick(event: MouseEvent): void {
    if (
        isOpen.value &&
        rootEl.value !== null &&
        rootEl.value.contains(event.target as Node) === false
    ) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick, true);
});
</script>

<template>
  <div
    ref="rootEl"
    :data-testid="props.test_id"
  >
    <label
      v-if="props.label"
      class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200"
    >
      {{ props.label }}
      <span
        v-if="props.required"
        class="text-danger-500"
        aria-hidden="true"
      >*</span>
    </label>
    <div class="relative">
      <button
        type="button"
        :disabled="props.disabled || undefined"
        class="block w-full h-10 px-3 pr-9 text-left text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
        :class="{
          'border-danger-300 dark:border-danger-700': props.error !== null,
          'opacity-45 pointer-events-none': props.disabled,
        }"
        :aria-haspopup="'dialog'"
        :aria-expanded="isOpen"
        :data-testid="`${props.test_id}-field`"
        @click="open"
      >
        <span
          v-if="displayValue !== ''"
          :data-testid="`${props.test_id}-value`"
        >{{ displayValue }}</span>
        <span
          v-else
          class="text-slate-400 dark:text-slate-500"
        >{{ t('admin.date_picker.placeholder') }}</span>
        <Icon
          class="absolute right-2.5 top-3 w-4 h-4 text-slate-400"
          icon="heroicons:calendar"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="displayValue !== '' && !props.disabled"
        type="button"
        class="absolute right-9 top-2 inline-flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
        :aria-label="t('common.clear_filters')"
        :data-testid="`${props.test_id}-clear`"
        @click="clear"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:x-mark"
          aria-hidden="true"
        />
      </button>
      <div
        v-if="isOpen"
        class="admin-autocomplete-panel absolute top-full left-0 right-0 mt-1 p-3 rounded-lg z-dropdown"
        role="dialog"
        :aria-label="`${labelText} ${monthTitle}`"
        :data-testid="`${props.test_id}-calendar`"
        @keydown="onKeydown"
      >
        <div class="mb-2 flex items-center justify-between">
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
            :aria-label="t('common.previous')"
            :data-testid="`${props.test_id}-prev-month`"
            @click="shift(-1)"
          >
            <Icon
              class="w-4 h-4"
              icon="heroicons:chevron-left"
              aria-hidden="true"
            />
          </button>
          <!-- The heading toggles the year grid (ADR-0024): clicking it opens
               the years for picking, clicking again returns to the day
               grid. -->
          <button
            type="button"
            class="px-2 py-1 rounded-lg text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            :aria-expanded="viewMode === 'years'"
            :aria-label="t('common.year_picker')"
            :data-testid="`${props.test_id}-month-title`"
            @click="toggleYearView"
          >
            <template v-if="viewMode === 'years'">
              {{ yearRangeTitle }}
            </template>
            <template v-else>
              {{ monthTitle }}
            </template>
          </button>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
            :aria-label="t('common.next')"
            :data-testid="`${props.test_id}-next-month`"
            @click="shift(1)"
          >
            <Icon
              class="w-4 h-4"
              icon="heroicons:chevron-right"
              aria-hidden="true"
            />
          </button>
        </div>
        <div
          v-if="viewMode === 'years'"
          class="grid grid-cols-5 gap-0.5 text-center"
          :data-testid="`${props.test_id}-years`"
        >
          <button
            v-for="year in years"
            :key="year"
            type="button"
            class="inline-flex h-9 items-center justify-center rounded-lg text-sm transition-colors"
            :class="[
              year === viewDate.getFullYear()
                ? 'bg-primary-600 text-white font-semibold'
                : 'text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950',
              isFocusedYear(year) ? 'ring-2 ring-primary-500 ring-inset' : '',
            ]"
            :aria-pressed="year === viewDate.getFullYear()"
            :data-testid="`${props.test_id}-year-${year}`"
            @click="pickYear(year)"
          >
            {{ year }}
          </button>
        </div>
        <div
          v-else
          class="grid grid-cols-7 gap-0.5 text-center"
        >
          <span
            v-for="(weekday, index) in weekdays"
            :key="`w-${index}`"
            class="py-1 text-xs font-semibold text-slate-400 dark:text-slate-500"
          >{{ weekday }}</span>
          <template
            v-for="(cell, index) in gridCells"
            :key="`c-${index}`"
          >
            <button
              v-if="cell !== null"
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm transition-colors mx-auto"
              :class="[
                isSelected(cell)
                  ? 'bg-primary-600 text-white font-semibold'
                  : 'text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950',
                isToday(cell) && !isSelected(cell)
                  ? 'border border-primary-500 text-primary-700 dark:text-primary-300'
                  : '',
                isFocused(cell) ? 'ring-2 ring-primary-500 ring-inset' : '',
              ]"
              :aria-selected="isSelected(cell)"
              :data-testid="`${props.test_id}-day-${String(cell.getDate()).padStart(2, '0')}-${String(cell.getMonth() + 1).padStart(2, '0')}-${cell.getFullYear()}`"
              @click="pick(cell)"
            >
              {{ cell.getDate() }}
            </button>
            <span
              v-else
              class="inline-block h-8 w-8"
              aria-hidden="true"
            />
          </template>
        </div>
      </div>
    </div>
    <p
      v-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
  </div>
</template>
