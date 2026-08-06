<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';

import PublicBaseButton from './PublicBaseButton.vue';
import PublicBaseSkeleton from './PublicBaseSkeleton.vue';

const props = withDefaults(
    defineProps<{
        has_more: boolean;
        is_loading?: boolean;
        label?: string;
        test_id?: string;
    }>(),
    {
        is_loading: false,
        label: undefined,
        test_id: 'infinite-scroll-loader',
    },
);

const emit = defineEmits<{ 'load-more': [] }>();

const { t } = useI18n();
</script>

<template>
  <div
    v-if="props.has_more"
    class="load-more"
    :data-testid="props.test_id"
  >
    <div
      v-if="props.is_loading"
      class="load-more__skeleton"
      aria-hidden="true"
    >
      <PublicBaseSkeleton
        v-for="row in 3"
        :key="row"
        shape="text"
        :width="row % 2 === 0 ? '80%' : '60%'"
      />
      <PublicBaseSkeleton
        shape="pill"
        width="30%"
        height="1.5em"
      />
    </div>
    <PublicBaseButton
      v-else
      variant="secondary"
      :test_id="`${props.test_id}-button`"
      @click="emit('load-more')"
    >
      {{ props.label ?? t('common.load_more') }}
    </PublicBaseButton>
  </div>
</template>

<style scoped>
.load-more {
    text-align: center;
    padding: var(--space-lg) 0 var(--space-2xl);
}

.load-more__skeleton {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-sm);
    max-width: 320px;
    margin: 0 auto;
}
</style>
