<!-- SkillItem (public-section-components.md:969-979, content-management.md:76-77; reworked 2026-08-14) — one compact skill chip: the top row carries the icon + name, the bottom row the 1-5 proficiency circles. Filled circles share the CURRENT level's color (1 redish → 5 violettish — the same color coding as the admin ProficiencyDots); each circle has a tiny border in that color with a small gap between the border and the inner circle. The level title shows only the localized level name. Chips wrap inline within the group list. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

import type { SkillView } from '../Composables/useLandingPage';
import { usePublicIconsReady } from '../iconRegistration';

const props = withDefaults(
    defineProps<{
        skill: SkillView;
        test_id?: string;
    }>(),
    {
        test_id: 'landing-skill-item',
    },
);

const iconsReady = usePublicIconsReady();
// The current level's color — every filled circle shares it (mirrors the
// admin ProficiencyDots palette, 5.2: danger-500 → warning-500 →
// success-500 → primary-600 → violet-500).
const LEVEL_COLORS = ['#ef4444', '#f59e0b', '#22c55e', '#2447c9', '#8b5cf6'];

const dotColor = computed(() => LEVEL_COLORS[props.skill.proficiency - 1] ?? '#2447c9');

// The tooltip/accessible name carries only the level name (user rework —
// the skill name is already visible next to the indicator).
const levelLabel = computed<string | undefined>(() => props.skill.proficiency_label ?? undefined);
</script>

<template>
  <div
    class="skill-item"
    :data-testid="`${props.test_id}-${props.skill.id}`"
  >
    <div class="skill-item__top">
      <span class="skill-item__icon">
        <Icon
          v-if="iconsReady && props.skill.icon"
          class="skill-item__icon-svg"
          :icon="props.skill.icon"
          aria-hidden="true"
        />
      </span>
      <span class="skill-item__name">
        {{ props.skill.name }}
      </span>
    </div>
    <div
      class="skill-item__level"
      role="img"
      :aria-label="levelLabel"
      :title="levelLabel"
      :data-testid="`${props.test_id}-${props.skill.id}-level`"
    >
      <span
        v-for="index in 5"
        :key="index"
        class="skill-item__dot"
        :class="{ 'is-filled': index <= props.skill.proficiency }"
        :style="{ '--dot-color': dotColor }"
      />
    </div>
  </div>
</template>

<style scoped>
.skill-item {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: var(--space-sm) var(--space-md);

    /* Opaque chip background: darker than the card on light, lighter on
       dark (--color-bg-tertiary sits below/above --color-surface in both
       themes); the small shadow is theme-aware via the token. */
    background: var(--color-bg-tertiary);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
}

/* Light theme: the chip uses the user-approved neutral tint and a more
   prominent shadow (dark keeps the token values above). */
:root:not(.dark) .skill-item {
    background: oklch(95% 0 0deg);
    box-shadow: 0 1px 2px rgb(0 0 0 / 10%), 0 2px 4px rgb(0 0 0 / 10%);
}

.skill-item__top {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
}

.skill-item__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: var(--radius-sm);
    background: var(--color-accent-muted);
    color: var(--color-accent);
}

/* The icon plate stays accent-muted on dark; white on light. */
:root:not(.dark) .skill-item__icon {
    background: #fff;
}

.skill-item__icon-svg {
    width: 14px;
    height: 14px;
}

.skill-item__name {
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    color: var(--color-text-primary);
    white-space: nowrap;
}

/* Proficiency: 5 square dots, filled by .is-filled (1-5). */
.skill-item__level {
    display: flex;
    gap: 3px;
    cursor: help;
}

.skill-item__dot {
    width: 9px;
    height: 9px;
    border-radius: 3px;

    /* Non-filled: a subtle tint over the chip background — differs from
       the chip but stays quiet. */
    background: color-mix(in oklab, var(--color-text-tertiary) 22%, transparent);
}

.skill-item__dot.is-filled {
    background: var(--dot-color);
}
</style>
