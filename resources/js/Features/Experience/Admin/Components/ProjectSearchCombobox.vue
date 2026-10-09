<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

interface ProjectOption {
    id: number;
    title: string;
    slug: string;
}

interface ProjectSelection {
    project_id: number | null;
    external_project_name: string | null;
}

type ComboboxState = 'empty' | 'portfolio_project' | 'external_project';

const props = withDefaults(
    defineProps<{
        modelValue: ProjectSelection;
        projects?: ProjectOption[];
        label?: string;
        error?: string | null;
        disabled?: boolean;
        placeholder?: string;
        test_id?: string;
    }>(),
    {
        projects: () => [],
        label: undefined,
        error: null,
        disabled: false,
        placeholder: undefined,
        test_id: 'project-search-combobox',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: ProjectSelection];
}>();

const { t } = useI18n();

const query = ref('');
const isOpen = ref(false);
const highlighted = ref(-1);
const listboxId = useId();
const rootEl = ref<HTMLElement | null>(null);

const selectedProject = computed(() =>
    props.projects.find((project) => project.id === props.modelValue.project_id),
);

const state = computed<ComboboxState>(() => {
    if (props.modelValue.project_id !== null) {
        return 'portfolio_project';
    }

    if (props.modelValue.external_project_name !== null) {
        return 'external_project';
    }

    return 'empty';
});

const filteredProjects = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return props.projects;
    }

    return props.projects.filter((project) => project.title.toLowerCase().includes(needle));
});

// The input text mirrors the selection — the selected project's title or
// the typed external name; a server re-render (validation round-trip)
// resyncs it from the props.
watch(
    () => props.modelValue,
    (value) => {
        if (value.project_id !== null) {
            query.value = selectedProject.value?.title ?? '';
        } else {
            query.value = value.external_project_name ?? '';
        }
        highlighted.value = -1;
    },
    { immediate: true },
);

function onInput(event: Event): void {
    query.value = (event.target as HTMLInputElement).value;
    highlighted.value = -1;

    // Clearing the input resets both fields (contract: "Clearing the
    // input resets both project_id and external_project_name to null").
    if (query.value.trim() === '') {
        emit('update:modelValue', { project_id: null, external_project_name: null });
        isOpen.value = false;
        return;
    }

    isOpen.value = true;
}

function selectProject(project: ProjectOption): void {
    emit('update:modelValue', { project_id: project.id, external_project_name: null });
    query.value = project.title;
    highlighted.value = -1;
    isOpen.value = false;
}

function confirmCustomValue(): void {
    const trimmed = query.value.trim();

    if (trimmed === '') {
        return;
    }

    emit('update:modelValue', { project_id: null, external_project_name: trimmed });
    highlighted.value = -1;
    isOpen.value = false;
}

function clear(): void {
    query.value = '';
    emit('update:modelValue', { project_id: null, external_project_name: null });
    highlighted.value = -1;
    isOpen.value = false;
}

function onKeydown(event: KeyboardEvent): void {
    if (props.disabled) {
        return;
    }

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            if (filteredProjects.value.length > 0) {
                highlighted.value = (highlighted.value + 1) % filteredProjects.value.length;
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
            const option = filteredProjects.value[highlighted.value];

            if (option !== undefined) {
                selectProject(option);
            } else {
                confirmCustomValue();
            }
            break;
        }
        case 'Escape':
            isOpen.value = false;
            highlighted.value = -1;
            break;
    }
}

// Close the dropdown when a click lands outside the combobox (the
// dropdown's own options preventDefault the mousedown — preventing the
// input blur — and select on click, so they never reach this handler).
// The listener runs in the CAPTURE phase — before any element's own
// click handler — so the check always sees the click target still
// attached: a click handler may re-render and remove the target before
// the click finishes bubbling, which a bubble-phase check would
// misread as an outside click.
function onDocumentClick(event: MouseEvent): void {
    if (
        isOpen.value &&
        rootEl.value !== null &&
        rootEl.value.contains(event.target as Node) === false
    ) {
        isOpen.value = false;
        highlighted.value = -1;
    }
}

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick, true);
});

onMounted(() => {
    document.addEventListener('click', onDocumentClick, true);
});

function onFocus(): void {
    if (!props.disabled) {
        isOpen.value = true;
    }
}

// Losing focus confirms a pending custom value — clicking the form's Save
// button blurs this input, and without the commit the typed external
// project name was never emitted (the payload saved null and the name
// "disappeared" after the save round-trip). Mirrors the Enter behavior:
// an exact title match selects that project, anything else becomes the
// external name; an empty query commits nothing.
function onBlur(): void {
    if (props.disabled) {
        return;
    }

    const trimmed = query.value.trim();

    if (trimmed === '') {
        return;
    }

    const exact = props.projects.find(
        (project) => project.title.toLowerCase() === trimmed.toLowerCase(),
    );

    if (exact !== undefined) {
        selectProject(exact);
    } else {
        confirmCustomValue();
    }
}
</script>

<template>
  <div
    ref="rootEl"
    :data-component="'project-search-combobox'"
    :data-state="state"
    :data-testid="props.test_id"
  >
    <label
      v-if="props.label"
      class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200"
    >
      {{ props.label }}
    </label>
    <div class="relative">
      <input
        type="text"
        role="combobox"
        :aria-expanded="isOpen"
        :aria-controls="isOpen ? listboxId : undefined"
        :aria-activedescendant="isOpen && highlighted >= 0 ? `${listboxId}-option-${highlighted}` : undefined"
        :aria-invalid="props.error !== null || undefined"
        :value="query"
        :placeholder="props.placeholder ?? t('admin.experience.project_placeholder')"
        :disabled="props.disabled || undefined"
        class="block w-full h-10 px-3 pr-9 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-surface-dark-secondary text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-primary-500 focus:ring-[3px] focus:ring-primary-100 dark:focus:ring-primary-900 outline-none transition-all"
        :class="{
          'border-danger-300 dark:border-danger-700': props.error !== null,
          'opacity-45 pointer-events-none': props.disabled,
        }"
        :data-testid="`${props.test_id}-input`"
        @input="onInput"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="onKeydown"
      >
      <span
        v-if="state === 'portfolio_project'"
        class="absolute right-3 top-2.5 inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-2xs font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
        :data-testid="`${props.test_id}-portfolio-badge`"
      >
        {{ t('admin.experience.portfolio_project') }}
      </span>
      <button
        v-else-if="query !== '' || state === 'external_project'"
        type="button"
        class="absolute right-2 top-2 inline-flex h-6 w-6 items-center justify-center rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
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
      <!-- The dropdown lives INSIDE the `relative` wrapper — `absolute
           top-full` anchors to the nearest positioned ancestor, and a
           sibling of the wrapper would attach to an arbitrary ancestor
           up the layout (the panel then renders near the page footer).
           The surface/border/shadow come from the shared
           `admin-autocomplete-panel` utility. -->
      <div
        v-if="isOpen"
        :id="listboxId"
        class="admin-autocomplete-panel absolute top-full left-0 right-0 mt-1 rounded-lg z-dropdown overflow-hidden"
        role="listbox"
        :data-testid="`${props.test_id}-dropdown`"
      >
        <template v-if="filteredProjects.length > 0">
          <div
            v-for="(project, index) in filteredProjects"
            :id="`${listboxId}-option-${index}`"
            :key="project.id"
            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-primary-50 dark:hover:bg-primary-950 transition-colors cursor-pointer"
            :class="{
              'bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300':
                index === highlighted,
            }"
            role="option"
            :aria-selected="index === highlighted"
            :data-testid="`${props.test_id}-option-${index}`"
            @mousedown.prevent
            @mousemove="highlighted = index"
            @click="selectProject(project)"
          >
            <span class="font-medium">{{ project.title }}</span>
            <span class="font-mono text-xs text-slate-400 dark:text-slate-500">
              /{{ project.slug }}
            </span>
          </div>
        </template>
        <p
          v-else
          class="px-3 py-3 text-sm text-slate-400 dark:text-slate-500"
          :data-testid="`${props.test_id}-no-match`"
        >
          {{ t('admin.experience.no_projects_match') }}
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
  </div>
</template>
