<!-- SkillGroupCard (admin-section-components.md:851-865, skills.html:211-278;
     5.2 rework) — one skill group card. The header (drag handle, icon,
     name, move up/down keyboard alternatives, delete) and the footer
     ("Add Skill") carry a distinct background so the contained skills
     list reads as nested; the header highlights on hover and a click on
     it starts editing (no dedicated edit button). Editing (3×3 grid:
     icon picker / locale-tabbed name / Save+Cancel — the translation
     tabs sit BELOW the name input, empty grid cells keep the row
     heights aligned): Enter saves, Escape or a click anywhere outside
     the header discards. The skills list + new-skill row are injected
     through the default slot. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import type { SkillRow } from '@techskills-admin/SkillItem.vue';
import { computed, ref } from 'vue';

export interface LocaleOption {
    code: string;
    native_name: string;
}

export interface SkillGroupData extends Record<string, unknown> {
    id: number;
    name: string;
    names: Record<string, string>;
    icon: string | null;
    sort_order: number;
    skills: SkillRow[];
}

const props = withDefaults(
    defineProps<{
        group: SkillGroupData;
        index: number;
        editing?: boolean;
        draft_names?: Record<string, string> | null;
        draft_icon?: string | null;
        error_names?: Record<string, string>;
        is_saving?: boolean;
        can_move_up?: boolean;
        can_move_down?: boolean;
        test_id?: string;
    }>(),
    {
        editing: false,
        draft_names: null,
        draft_icon: null,
        error_names: () => ({}),
        is_saving: false,
        can_move_up: false,
        can_move_down: false,
        test_id: 'skill-group-card',
    },
);

const emit = defineEmits<{
    'update-group': [{ id: number; field: string; value: unknown }];
    'edit-group': [id: number];
    'save-group': [id: number];
    'cancel-group': [id: number];
    'delete-group': [id: number];
    'move-up': [id: number];
    'move-down': [id: number];
    'add-skill': [groupId: number];
}>();

const { t } = useI18n();
const page = usePage<{ supported_locales?: string[] }>();

const editingLocale = ref('en');

// The group's per-locale editing tabs only offer the ACTIVE locales
// (the shared `supported_locales` prop — disabled locales are not
// editable, 5.2 rework).
const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

// Locales whose required name translation is empty — the tab gets the
// warning marker before the save round-trip.
const warnedLocales = computed(() =>
    SUPPORTED_LOCALES.filter((code) => (props.draft_names?.[code] ?? '').trim() === ''),
);

function onHeaderClick(): void {
    if (!props.editing) {
        emit('edit-group', props.group.id);
    }
}

function draftName(locale: string): string {
    return props.draft_names?.[locale] ?? '';
}

function setName(locale: string, value: string): void {
    emit('update-group', {
        id: props.group.id,
        field: 'name',
        value: { ...props.draft_names, [locale]: value },
    });
}

function nameError(): string | null {
    return props.error_names[`name.${editingLocale.value}`] ?? null;
}
</script>

<template>
  <section
    class="rounded-lg border border-slate-200 bg-white shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="`${props.test_id}-group-${props.group.id}`"
  >
    <header
      class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 transition-colors dark:border-slate-700"
      :class="
        props.editing
          ? 'bg-slate-100/80 dark:bg-slate-800/60'
          : 'cursor-pointer bg-slate-100/70 hover:bg-slate-200/70 dark:bg-slate-800/50 dark:hover:bg-slate-800/70'
      "
      :data-testid="`${props.test_id}-group-${props.group.id}-header`"
      @click="onHeaderClick"
    >
      <span
        class="group-card-handle flex shrink-0 cursor-grab select-none justify-center text-sm text-slate-300 dark:text-slate-600"
        aria-hidden="true"
        @click.stop
      >⠿</span>

      <template v-if="props.editing">
        <div
          class="grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-4"
        >
          <!-- Column 1: icon selector (label + control + empty cell —
               grid rows keep all three columns aligned). -->
          <div class="flex flex-col gap-1.5">
            <label class="h-5 text-xs font-semibold text-slate-500 dark:text-slate-400">
              {{ t('admin.skills.icon') }}
            </label>
            <AdminIconPicker
              :model-value="props.draft_icon ?? props.group.icon"
              icon_set="ui"
              :placeholder="t('admin.icon_picker.select_placeholder')"
              :test_id="`${props.test_id}-group-${props.group.id}-icon`"
              @update:model-value="
                (value) =>
                  emit('update-group', {
                    id: props.group.id,
                    field: 'icon',
                    value,
                  })
              "
            />
            <div class="h-9" />
          </div>

          <!-- Column 2: per-locale name (label + input + tabs below). -->
          <div class="flex flex-col gap-1.5">
            <label class="h-5 text-xs font-semibold text-slate-500 dark:text-slate-400">
              {{ t('admin.skills.group_name') }}
            </label>
            <AdminBaseInput
              :model-value="draftName(editingLocale)"
              :error="nameError()"
              :disabled="props.is_saving"
              :maxlength="100"
              :test_id="`${props.test_id}-group-${props.group.id}-name-${editingLocale}`"
              @update:model-value="setName(editingLocale, $event)"
              @keydown.enter="emit('save-group', props.group.id)"
              @keydown.escape="emit('cancel-group', props.group.id)"
            />
            <LocaleTabs
              v-model="editingLocale"
              :locales="locales"
              :warned_locales="warnedLocales"
              :test_id="`${props.test_id}-group-${props.group.id}-tabs`"
            />
          </div>

          <!-- Column 3: Save/Cancel (icon-only — the user-facing look is
               the same width; empty label cell keeps the rows aligned). -->
          <div class="flex flex-col gap-1.5">
            <div class="h-5" />
            <div class="flex items-center gap-2">
              <AdminBaseButton
                type="button"
                variant="icon-only"
                icon="heroicons:check"
                :aria-label="t('common.save')"
                :disabled="props.is_saving"
                :test_id="`${props.test_id}-group-${props.group.id}-save`"
                @click.stop="emit('save-group', props.group.id)"
              />
              <AdminBaseButton
                type="button"
                variant="icon-only"
                icon="heroicons:x-mark"
                :aria-label="t('common.cancel')"
                :disabled="props.is_saving"
                :test_id="`${props.test_id}-group-${props.group.id}-cancel`"
                @click.stop="emit('cancel-group', props.group.id)"
              />
            </div>
            <div class="h-9" />
          </div>
        </div>
      </template>

      <template v-else>
        <span
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-400"
        >
          <Icon
            class="h-[1.1em] w-[1.1em]"
            :icon="props.group.icon ?? 'heroicons:bolt'"
            aria-hidden="true"
          />
        </span>
        <h2
          class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100"
        >
          {{ props.group.name }}
        </h2>
        <AdminBaseButton
          variant="icon-only"
          icon="heroicons:chevron-up"
          :disabled="!props.can_move_up"
          :aria-disabled="!props.can_move_up"
          :aria-label="t('admin.skills.move_up', { name: props.group.name })"
          :test_id="`${props.test_id}-group-${props.group.id}-up`"
          @click.stop="emit('move-up', props.group.id)"
        />
        <AdminBaseButton
          variant="icon-only"
          icon="heroicons:chevron-down"
          :disabled="!props.can_move_down"
          :aria-disabled="!props.can_move_down"
          :aria-label="t('admin.skills.move_down', { name: props.group.name })"
          :test_id="`${props.test_id}-group-${props.group.id}-down`"
          @click.stop="emit('move-down', props.group.id)"
        />
        <AdminBaseButton
          variant="icon-outline-danger"
          icon="heroicons:trash"
          :aria-label="t('admin.skills.delete_group')"
          :test_id="`${props.test_id}-group-${props.group.id}-delete`"
          @click.stop="emit('delete-group', props.group.id)"
        />
        <span class="sr-only">{{ props.index + 1 }}</span>
      </template>
    </header>

    <!-- The page injects the new-skill editor row + the skills
         SortableList here. -->
    <slot />

    <footer
      class="border-t border-slate-100 bg-slate-100/70 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50"
    >
      <AdminBaseButton
        variant="secondary"
        size="sm"
        icon="heroicons:plus"
        icon_position="left"
        :test_id="`${props.test_id}-group-${props.group.id}-add-skill`"
        @click="emit('add-skill', props.group.id)"
      >
        {{ t('admin.skills.add_skill') }}
      </AdminBaseButton>
    </footer>
  </section>
</template>
