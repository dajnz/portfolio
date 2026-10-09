<script setup lang="ts">
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseModal from '@shared-public/PublicBaseModal.vue';
import type { TechTagView } from '../Composables/useExperienceTimelinePage';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        title?: string;
        company?: string | null;
        tags?: TechTagView[];
        test_id?: string;
    }>(),
    {
        title: undefined,
        company: null,
        tags: () => [],
        test_id: 'tag-popup-modal',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const { t } = useI18n();
</script>

<template>
  <PublicBaseModal
    :model-value="props.modelValue"
    :title="props.title"
    size="sm"
    :test_id="props.test_id"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #header>
      <div class="modal-heading">
        <h2
          :id="`${props.test_id}-title`"
          class="modal-heading__title"
        >
          {{ props.title }}
        </h2>
        <p
          v-if="props.company !== null"
          class="modal-heading__company"
          :data-testid="`${props.test_id}-company`"
        >
          {{ props.company }}
        </p>
      </div>
    </template>

    <p class="modal-section-title">
      {{ t('experience.all_tags') }}
    </p>
    <ul class="modal-tag-list">
      <li
        v-for="tag in props.tags"
        :key="tag.id"
      >
        <TechSkillTag :skill="tag" />
      </li>
    </ul>
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

.modal-section-title {
    font-size: var(--font-size-sm);
    font-weight: var(--font-semibold);
    text-transform: uppercase;
    letter-spacing: var(--letter-spacing-wide);
    color: var(--color-text-tertiary);
    margin: 0 0 var(--space-sm);
}

.modal-tag-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-xs);
}
</style>
