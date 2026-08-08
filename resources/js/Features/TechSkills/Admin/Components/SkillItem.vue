<!-- SkillItem (admin-section-components.md:867-884, skills.html:242-266;
     5.2 reworks) — one skill row inside a group's SortableList. The whole
     row is the drag surface (no handle-mode guard — the row drags from
     anywhere except the `skill-row-interactive` controls) and a click
     switches it to edit mode. Viewing: icon, name, display-only
     color-coded proficiency dots (single container title with the current
     level name — proficiency is edited via the dropdown, 5.2 rework),
     include-in-resume checkbox, keyboard move-up/down alternatives
     (accessibility.md:35-44), an icon-outline-danger delete aligned to
     the right edge. Editing (4 columns: icon picker, name input,
     localized proficiency dropdown, checkbox + icon-only Save/Cancel):
     Enter saves, Escape cancels, clicking outside the row discards. Gaps
     (5.2 rework): a small base gap (10 px) between the knob/icon/name/
     dropdown and the Save/Cancel pair; double-ish gaps (24 px) around the
     Resume checkbox to visually separate the input group / the checkbox /
     the action buttons. All mutations bubble as `update` with
     `{ id, field, value }`; the page persists them. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import ProficiencyDots from '@techskills-admin/ProficiencyDots.vue';
import { computed } from 'vue';

export interface SkillRow extends Record<string, unknown> {
    id: number;
    name: string;
    icon: string | null;
    proficiency: number;
    sort_order: number;
    include_in_resume: boolean;
}

const props = withDefaults(
    defineProps<{
        skill: SkillRow;
        index: number;
        editing?: boolean;
        can_move_up?: boolean;
        can_move_down?: boolean;
        draft_name?: string | null;
        draft_icon?: string | null;
        draft_proficiency?: number | null;
        error_name?: string | null;
        is_saving?: boolean;
        test_id?: string;
    }>(),
    {
        editing: false,
        can_move_up: false,
        can_move_down: false,
        draft_name: null,
        draft_icon: null,
        draft_proficiency: null,
        error_name: null,
        is_saving: false,
        test_id: 'skill-item',
    },
);

const emit = defineEmits<{
    update: [{ id: number; field: string; value: unknown }];
    delete: [id: number];
    edit: [id: number];
    save: [id: number];
    cancel: [id: number];
    'move-up': [id: number];
    'move-down': [id: number];
}>();

const { t } = useI18n();

// The localized proficiency-level options for the editing dropdown
// (`skills.proficiency.{1-5}` — the domain's `toProficiencyLabelKey()`
// keys).
const proficiencyOptions = computed(() =>
    Array.from({ length: 5 }, (_, index) => ({
        value: String(index + 1),
        label: t(`skills.proficiency.${index + 1}`),
    })),
);

function onRowClick(): void {
    if (!props.editing) {
        emit('edit', props.skill.id);
    }
}
</script>

<template>
  <div
    class="flex items-center gap-2.5 px-4 py-2.5 transition-colors"
    :class="
      props.editing
        ? 'bg-slate-50 dark:bg-slate-800/40'
        : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/30'
    "
    :data-testid="`${props.test_id}-skill-${props.skill.id}`"
    @click="onRowClick"
  >
    <span
      class="skill-row-handle flex w-14 shrink-0 cursor-grab select-none justify-center text-sm text-slate-300 dark:text-slate-600"
      aria-hidden="true"
      @click.stop
    >⠿</span>

    <span
      class="flex shrink-0 items-center justify-center"
      :class="{ 'skill-row-interactive': props.editing }"
    >
      <template v-if="props.editing">
        <span class="w-28">
          <AdminIconPicker
            :model-value="props.draft_icon ?? props.skill.icon"
            icon_set="devicon"
            :placeholder="t('admin.icon_picker.select_placeholder')"
            :test_id="`${props.test_id}-skill-${props.skill.id}-icon`"
            @update:model-value="
              (value) =>
                emit('update', {
                  id: props.skill.id,
                  field: 'icon',
                  value,
                })
            "
          />
        </span>
      </template>
      <Icon
        v-else
        class="h-[1.1em] w-[1.1em] text-slate-400 dark:text-slate-500"
        :icon="props.skill.icon ?? 'heroicons:code-bracket'"
        aria-hidden="true"
      />
    </span>

    <span
      class="min-w-64 flex-1"
      :class="{ 'skill-row-interactive': props.editing }"
    >
      <AdminBaseInput
        v-if="props.editing"
        :model-value="props.draft_name ?? props.skill.name"
        :error="props.error_name"
        :disabled="props.is_saving"
        :maxlength="100"
        :test_id="`${props.test_id}-skill-${props.skill.id}-name`"
        @update:model-value="
          (value) =>
            emit('update', {
              id: props.skill.id,
              field: 'name',
              value,
            })
        "
        @keydown.enter="emit('save', props.skill.id)"
        @keydown.escape="emit('cancel', props.skill.id)"
      />
      <span
        v-else
        class="block truncate text-sm font-medium text-slate-700 dark:text-slate-200"
      >
        {{ props.skill.name }}
      </span>
    </span>

    <span
      class="flex shrink-0 items-center"
      :class="{ 'skill-row-interactive': props.editing }"
    >
      <span
        v-if="props.editing"
        class="w-28"
      >
        <AdminBaseDropdown
          :model-value="String(props.draft_proficiency ?? props.skill.proficiency)"
          :options="proficiencyOptions"
          :aria-label="t('admin.skills.proficiency')"
          :test_id="`${props.test_id}-skill-${props.skill.id}-proficiency`"
          @update:model-value="
            (value) =>
              emit('update', {
                id: props.skill.id,
                field: 'proficiency',
                value: Number(value),
              })
          "
        />
      </span>
      <ProficiencyDots
        v-else
        :value="props.skill.proficiency"
        :test_id="`${props.test_id}-skill-${props.skill.id}-dots`"
      />
    </span>

    <!-- The Resume checkbox is its own control group, visually separated
         from the inputs AND from the action buttons (24 px each side). -->
    <span class="mx-6 flex shrink-0 items-center">
      <span
        class="skill-row-interactive"
        @click.stop
      >
        <AdminBaseCheckbox
          :model-value="props.skill.include_in_resume"
          :label="t('admin.skills.resume')"
          :test_id="`${props.test_id}-skill-${props.skill.id}-resume`"
          @update:model-value="
            (value) =>
              emit('update', {
                id: props.skill.id,
                field: 'include_in_resume',
                value,
              })
          "
        />
      </span>
    </span>

    <template v-if="!props.editing">
      <!-- Move up/down group. -->
      <span class="mx-6 flex shrink-0 items-center gap-2.5">
        <span
          class="skill-row-interactive"
          @click.stop
        >
          <AdminBaseButton
            variant="icon-only"
            icon="heroicons:chevron-up"
            :disabled="!props.can_move_up"
            :aria-disabled="!props.can_move_up"
            :aria-label="t('admin.skills.move_up', { name: props.skill.name })"
            :test_id="`${props.test_id}-skill-${props.skill.id}-up`"
            @click="emit('move-up', props.skill.id)"
          />
        </span>
        <span
          class="skill-row-interactive"
          @click.stop
        >
          <AdminBaseButton
            variant="icon-only"
            icon="heroicons:chevron-down"
            :disabled="!props.can_move_down"
            :aria-disabled="!props.can_move_down"
            :aria-label="t('admin.skills.move_down', { name: props.skill.name })"
            :test_id="`${props.test_id}-skill-${props.skill.id}-down`"
            @click="emit('move-down', props.skill.id)"
          />
        </span>
      </span>

      <!-- Delete — the flex-1 name pushes the trailing controls to the
           right edge, so the delete aligns with the group's delete. -->
      <span class="flex shrink-0 items-center">
        <span
          class="skill-row-interactive"
          @click.stop
        >
          <AdminBaseButton
            variant="icon-outline-danger"
            icon="heroicons:trash"
            :aria-label="`${t('common.delete')} ${props.skill.name}`"
            :test_id="`${props.test_id}-skill-${props.skill.id}-delete`"
            @click="emit('delete', props.skill.id)"
          />
        </span>
      </span>
    </template>

    <template v-else>
      <!-- Action buttons group (Save/Cancel with the base gap). -->
      <span class="mx-6 flex shrink-0 items-center gap-2.5">
        <span
          class="skill-row-interactive"
          @click.stop
        >
          <AdminBaseButton
            type="button"
            variant="icon-only"
            icon="heroicons:check"
            :aria-label="t('common.save')"
            :disabled="props.is_saving"
            :test_id="`${props.test_id}-skill-${props.skill.id}-save`"
            @click="emit('save', props.skill.id)"
          />
        </span>
        <span
          class="skill-row-interactive"
          @click.stop
        >
          <AdminBaseButton
            type="button"
            variant="icon-only"
            icon="heroicons:x-mark"
            :aria-label="t('common.cancel')"
            :disabled="props.is_saving"
            :test_id="`${props.test_id}-skill-${props.skill.id}-cancel`"
            @click="emit('cancel', props.skill.id)"
          />
        </span>
      </span>
    </template>
    <span class="sr-only">{{ props.index + 1 }}</span>
  </div>
</template>
