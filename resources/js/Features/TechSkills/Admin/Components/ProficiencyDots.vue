<!-- ProficiencyDots (TechSkills, 5.2) — the 1-5 proficiency indicator.
     Purely presentational (plain divs, no buttons): the dots are too
     small for reliable clicking (and unusable on touch), so proficiency
     is edited via the inline dropdown instead. Filled dots share the
     CURRENT level's color (1 redish → 5 violettish — 5.2 rework); the
     container carries the current level's translated name as its ONLY
     title/accessible label (no per-dot titles). -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number;
        max?: number;
        test_id?: string;
    }>(),
    {
        max: 5,
        test_id: 'proficiency-dots',
    },
);

const { t } = useI18n();

const LEVEL_COLORS = [
    'bg-danger-500',
    'bg-warning-500',
    'bg-success-500',
    'bg-primary-600',
    'bg-violet-500',
];

// The current level's color — every filled dot shares it (the user's
// color-coding intent: 1 redish ... 5 violettish by the CURRENT level).
const filledColor = computed(() => LEVEL_COLORS[props.value - 1] ?? 'bg-primary-600');

const currentLevelName = computed(() => t(`skills.proficiency.${props.value}`));
</script>

<template>
  <div
    role="img"
    :aria-label="currentLevelName"
    :title="currentLevelName"
    class="flex items-center gap-1"
    :data-testid="props.test_id"
  >
    <span
      v-for="n in props.max"
      :key="n"
      class="h-2 w-2 rounded-full"
      :class="
        n <= props.value
          ? filledColor
          : 'bg-slate-200 dark:bg-slate-700'
      "
      :data-testid="`${props.test_id}-${n}`"
    />
  </div>
</template>
