<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicCenteredContainer from '@shared-public/PublicCenteredContainer.vue';
import { computed } from 'vue';
import type { SkillGroupView } from '../Composables/useLandingPage';
import SkillGroup from './SkillGroup.vue';

const props = withDefaults(
    defineProps<{
        skill_groups?: SkillGroupView[];
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        skill_groups: () => [],
        is_loading: false,
        test_id: 'landing-skills-block',
    },
);

const { t } = useI18n();

// Pairs of groups per masonry row; a trailing odd group spans the row alone.
const rows = computed<SkillGroupView[][]>(() => {
    const result: SkillGroupView[][] = [];
    for (let index = 0; index < props.skill_groups.length; index += 2) {
        result.push(props.skill_groups.slice(index, index + 2));
    }

    return result;
});

const skeletonGroups = [0, 1];
const skeletonSkills = [0, 1, 2];
</script>

<template>
  <section
    v-if="props.skill_groups.length > 0 || props.is_loading"
    class="skills-block"
    :data-testid="props.test_id"
  >
    <PublicCenteredContainer size="content">
      <h2 class="section-title">
        {{ t('landing.my_skills') }}
      </h2>

      <div
        v-if="props.is_loading"
        class="skills-block__grid"
        :data-testid="`${props.test_id}-skeleton`"
      >
        <div
          v-for="group in skeletonGroups"
          :key="group"
          class="skills-block__skeleton-group"
        >
          <div class="skills-block__skeleton-group-header">
            <PublicBaseSkeleton
              shape="rectangle"
              width="24px"
              height="24px"
            />
            <PublicBaseSkeleton
              shape="text"
              width="120px"
            />
          </div>
          <ul class="skill-group__list">
            <li
              v-for="skill in skeletonSkills"
              :key="skill"
              class="skills-block__skeleton-row"
            >
              <PublicBaseSkeleton
                shape="rectangle"
                width="22px"
                height="22px"
              />
              <PublicBaseSkeleton
                shape="text"
                width="90px"
              />
              <PublicBaseSkeleton
                shape="rectangle"
                width="57px"
                height="9px"
              />
            </li>
          </ul>
        </div>
      </div>

      <div
        v-else
        class="skills-block__grid"
      >
        <div
          v-for="(row, rowIndex) in rows"
          :key="`row-${rowIndex}`"
          class="skills-block__row"
          :class="{
            'skills-block__row--wide-first': rowIndex % 2 === 1,
            'skills-block__row--single': row.length === 1,
          }"
        >
          <SkillGroup
            v-for="group in row"
            :key="group.id"
            :group="group"
          />
        </div>
      </div>
    </PublicCenteredContainer>
  </section>
</template>

<style scoped>
.skills-block {
    background: var(--color-bg-secondary);

    /* Halved top padding — keeps the section heading from floating
   4rem below the previous block. */
    padding: var(--space-xl) 0 var(--space-3xl);
}

.section-title {
    font-family: var(--font-display);
    font-size: var(--font-size-2xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-lg);
}

.skills-block__grid {
    display: flex;
    flex-direction: column;
    gap: var(--space-xl);
}

.skills-block__row {
    display: grid;
    grid-template-columns: 3fr 4fr;
    gap: var(--space-xl);
}

.skills-block__row--wide-first {
    grid-template-columns: 4fr 3fr;
}

.skills-block__row--single {
    grid-template-columns: 1fr;
}

/* Skeleton group card — mirrors the loaded group's card look. */
.skills-block__skeleton-group {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    padding: var(--card-padding);
    box-shadow: var(--card-shadow);
    border-top: 4px solid var(--color-accent-3);
}

.skills-block__skeleton-group-header {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin-bottom: var(--space-lg);
}

/* Skeleton chip row — mirrors the loaded skill chip (2 stacked rows). */
.skills-block__skeleton-row {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: var(--space-sm) var(--space-md);
    background: var(--color-bg-tertiary);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    list-style: none;
}

/* Mirrors the loaded chip's light-theme look (SkillItem). */
:root:not(.dark) .skills-block__skeleton-row {
    background: oklch(95% 0 0deg);
    box-shadow: 0 1px 2px rgb(0 0 0 / 10%), 0 2px 4px rgb(0 0 0 / 10%);
}

.skill-group__list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
    margin: 0;
    padding: 0;
}

@media (width <= 768px) {
    .skills-block__row,
    .skills-block__row--wide-first {
        grid-template-columns: 1fr;
    }
}
</style>
