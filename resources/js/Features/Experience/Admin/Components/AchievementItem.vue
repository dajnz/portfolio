<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import TextEditor, {
    EXPERIENCE_ACHIEVEMENT_EDITOR_BUTTONS,
} from '@shared-admin/TextEditor/TextEditor.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { AchievementRow } from '../Types/achievement';

const props = withDefaults(
    defineProps<{
        modelValue: AchievementRow;
        locale: string;
        index?: number;
        can_move_up?: boolean;
        can_move_down?: boolean;
        error?: string | null;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        index: 0,
        can_move_up: false,
        can_move_down: false,
        error: null,
        disabled: false,
        test_id: 'achievement-item',
    },
);

const emit = defineEmits<{
    'update:description': [value: string];
    remove: [];
    'move-up': [];
    'move-down': [];
}>();

const { t } = useI18n();

function onDescriptionInput(value: string): void {
    emit('update:description', value);
}
</script>

<template>
  <div
    class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
    :class="{ 'border-danger-300 dark:border-danger-700': props.error !== null }"
    :data-component="'achievement-item'"
    :data-testid="props.test_id"
  >
    <div class="mb-3 flex items-center gap-3">
      <span
        class="achievement-handle hidden cursor-grab select-none text-sm text-slate-300 sm:inline dark:text-slate-600"
        aria-hidden="true"
        :data-testid="`${props.test_id}-handle`"
      >⠿</span>
      <div class="ml-auto flex w-full items-center justify-between gap-1 sm:w-auto sm:justify-end">
        <div class="flex items-center gap-1">
          <AdminBaseButton
            type="button"
            variant="icon-only"
            icon="heroicons:chevron-up"
            :aria-label="t('common.move_item_up', { item_name: t('admin.experience.achievements') })"
            :disabled="props.disabled || props.can_move_up === false"
            :test_id="`${props.test_id}-move-up`"
            @click="emit('move-up')"
          />
          <AdminBaseButton
            type="button"
            variant="icon-only"
            icon="heroicons:chevron-down"
            :aria-label="t('common.move_item_down', { item_name: t('admin.experience.achievements') })"
            :disabled="props.disabled || props.can_move_down === false"
            :test_id="`${props.test_id}-move-down`"
            @click="emit('move-down')"
          />
        </div>
        <AdminBaseButton
          type="button"
          variant="icon-only"
          icon="heroicons:x-mark"
          :aria-label="t('common.remove')"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-remove`"
          @click="emit('remove')"
        />
      </div>
    </div>
    <TextEditor
      :model-value="props.modelValue.description[props.locale] ?? ''"
      :label="t('admin.experience.description')"
      :error="props.error"
      :disabled="props.disabled"
      :buttons="EXPERIENCE_ACHIEVEMENT_EDITOR_BUTTONS"
      min_height="60px"
      :test_id="`${props.test_id}-description-${props.locale}`"
      @update:model-value="onDescriptionInput"
    />
  </div>
</template>
