<script setup lang="ts">
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseModal from '@shared-public/PublicBaseModal.vue';
import { computed } from 'vue';
import type { TimelineItemView } from '../Composables/useExperienceTimelinePage';
import { useExperienceIconsReady } from '../iconRegistration';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        item?: TimelineItemView | null;
        test_id?: string;
    }>(),
    {
        item: null,
        test_id: 'experience-detail-modal',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const { t } = useI18n();
const iconsReady = useExperienceIconsReady();

const title = computed(() => props.item?.position ?? '');
</script>

<template>
  <PublicBaseModal
    :model-value="props.modelValue"
    :title="title"
    :test_id="props.test_id"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #header>
      <div class="modal-heading">
        <h2
          :id="`${props.test_id}-title`"
          class="modal-heading__title"
        >
          {{ title }}
        </h2>
        <p
          v-if="props.item !== null && props.item.company_name !== null"
          class="modal-heading__company"
          :data-testid="`${props.test_id}-company`"
        >
          {{ props.item.company_name }}
        </p>
      </div>
    </template>

    <template v-if="props.item !== null">
      <span
        class="timeline-item__period"
        :data-testid="`${props.test_id}-period`"
      >
        {{ props.item.period }}
      </span>

      <template v-if="props.item.achievements.length > 0">
        <h4 class="modal-section-title">
          {{ t('experience.achievements') }}
        </h4>
        <ul class="achievements">
          <li
            v-for="(achievement, index) in props.item.achievements"
            :key="index"
          >
            {{ achievement.description }}
            <a
              v-if="achievement.project_link !== null"
              class="link"
              :href="achievement.project_link"
              :data-testid="`${props.test_id}-achievement-${index}-link`"
            >
              {{ t('experience.read_the_writeup') }}
            </a>
          </li>
        </ul>
      </template>

      <template v-if="iconsReady && props.item.tech_tags.length > 0">
        <h4 class="modal-section-title">
          {{ t('experience.tech_stack') }}
        </h4>
        <ul class="modal-tag-list">
          <li
            v-for="tag in props.item.tech_tags"
            :key="tag.id"
          >
            <TechSkillTag :skill="tag" />
          </li>
        </ul>
      </template>

      <template v-if="props.item.project_name !== null">
        <h4 class="modal-section-title">
          {{ t('experience.project') }}
        </h4>
        <a
          v-if="props.item.project_slug !== null"
          class="link"
          :href="`/projects/${props.item.project_slug}`"
          :data-testid="`${props.test_id}-project-link`"
        >
          {{ props.item.project_name }}
        </a>
        <span
          v-else
          :data-testid="`${props.test_id}-project-name`"
        >
          {{ props.item.project_name }}
        </span>
      </template>
    </template>
  </PublicBaseModal>
</template>

<style scoped>
.modal-heading__title {
    font-size: var(--font-size-xl);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
    margin: 0;
}

.modal-heading__company {
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
    margin: var(--space-xs) 0 0;
}

.timeline-item__period {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: var(--font-size-sm);
    color: var(--color-accent);
    font-weight: var(--font-semibold);
    margin-bottom: var(--space-xs);
}

.modal-section-title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-semibold);
    text-transform: uppercase;
    letter-spacing: var(--letter-spacing-wide);
    color: var(--color-text-tertiary);
    margin: var(--space-lg) 0 var(--space-sm);
}

.modal-section-title:first-of-type {
    margin-top: var(--space-md);
}

.achievements {
    list-style: disc;
    padding-left: var(--space-lg);
    margin: 0 0 var(--space-lg);
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.modal-tag-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-xs);
}

.link {
    color: var(--color-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
    font-weight: var(--font-medium);
    transition: color var(--transition-fast) var(--ease-default);
}

.link:hover {
    color: var(--color-accent-hover);
}
</style>
