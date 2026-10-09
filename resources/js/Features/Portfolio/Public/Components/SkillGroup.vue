<script setup lang="ts">
import { Icon } from '@iconify/vue';

import type { SkillGroupView } from '../Composables/useLandingPage';
import SkillItem from './SkillItem.vue';

const props = withDefaults(
    defineProps<{
        group: SkillGroupView;
        test_id?: string;
    }>(),
    {
        test_id: 'landing-skill-group',
    },
);
</script>

<template>
  <div
    class="card skill-group"
    :data-testid="`${props.test_id}-${props.group.id}`"
  >
    <h3 class="skill-group__name">
      <Icon
        v-if="props.group.icon"
        class="skill-group__icon"
        :icon="props.group.icon"
        aria-hidden="true"
      />
      {{ props.group.name }}
    </h3>
    <ul class="skill-group__list">
      <li
        v-for="skill in props.group.skills"
        :key="skill.id"
      >
        <SkillItem :skill="skill" />
      </li>
    </ul>
  </div>
</template>

<style scoped>
.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    padding: var(--card-padding);
    box-shadow: var(--card-shadow);
    transition: var(--card-transition);
    border-top: 4px solid var(--color-accent-3);
}

.skill-group {
    min-width: 0;
}

.skill-group__name {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    font-size: var(--font-size-xl);
    font-weight: var(--font-semibold);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-lg);
}

.skill-group__icon {
    width: 1.1em;
    height: 1.1em;
    flex-shrink: 0;
}

.skill-group__list {
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-sm);
    margin: 0;
    padding: 0;
}
</style>
