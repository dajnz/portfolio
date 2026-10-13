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
    class="flex items-start gap-2.5 px-4 py-2.5 transition-colors sm:items-center"
    :class="
      props.editing
        ? 'bg-slate-50 dark:bg-slate-800/40'
        : 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/30'
    "
    :data-testid="`${props.test_id}-skill-${props.skill.id}`"
    @click="onRowClick"
  >
    <span
      class="skill-row-handle hidden shrink-0 cursor-grab select-none items-center justify-center text-sm text-slate-300 sm:flex sm:w-14 dark:text-slate-600"
      aria-hidden="true"
      @click.stop
    >⠿</span>

    <div class="flex min-w-0 flex-1 flex-col gap-2.5 sm:flex-row sm:items-center">
      <template v-if="props.editing">
        <span
          class="flex shrink-0 items-center justify-start"
          :class="{ 'skill-row-interactive': props.editing }"
        >
          <span class="w-full sm:w-28">
            <AdminIconPicker
              :model-value="props.draft_icon ?? props.skill.icon"
              icon_set="brands"
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
        </span>

        <span
          class="min-w-0 flex-1 sm:min-w-64"
          :class="{ 'skill-row-interactive': props.editing }"
        >
          <AdminBaseInput
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
        </span>

        <span
          class="flex shrink-0 items-center"
          :class="{ 'skill-row-interactive': props.editing }"
        >
          <span class="w-full sm:w-28">
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
        </span>

        <!-- The Resume checkbox and the Save/Cancel buttons share the
             bottom row (24 px separators on desktop). -->
        <div class="flex items-center justify-between gap-2.5">
          <span class="mx-0 flex shrink-0 items-center sm:mx-6">
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
          <span class="mx-0 flex shrink-0 items-center gap-2.5 sm:mx-6">
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
        </div>
      </template>

      <template v-else>
        <!-- Row 1: icon + skill name (ellipsized when too long) +
             proficiency level. -->
        <div class="flex items-center gap-2.5 sm:flex-1">
          <span class="flex shrink-0 items-center justify-start">
            <Icon
              class="h-[1.1em] w-[1.1em] text-slate-400 dark:text-slate-500"
              :icon="props.skill.icon ?? 'heroicons:code-bracket'"
              aria-hidden="true"
            />
          </span>
          <span class="min-w-0 flex-1 sm:min-w-64">
            <span class="block truncate text-sm font-medium text-slate-700 dark:text-slate-200">
              {{ props.skill.name }}
            </span>
          </span>
          <span class="flex shrink-0 items-center">
            <ProficiencyDots
              :value="props.skill.proficiency"
              :test_id="`${props.test_id}-skill-${props.skill.id}-dots`"
            />
          </span>
        </div>

        <!-- Row 2: Resume checkbox, move up/down and Delete in order
             (the delete aligns with the group's delete at the right
             edge). -->
        <div class="flex items-center justify-between gap-2.5">
          <span class="mx-0 flex shrink-0 items-center sm:mx-6">
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
          <span class="mx-0 flex shrink-0 items-center gap-2.5 sm:mx-6">
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
        </div>
      </template>
    </div>
    <span class="sr-only">{{ props.index + 1 }}</span>
  </div>
</template>
