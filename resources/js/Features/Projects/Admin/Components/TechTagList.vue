<script setup lang="ts">
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import { computed } from 'vue';

interface TechTag {
    id: number;
    name: string;
    icon: string | null;
}

const props = withDefaults(
    defineProps<{
        tags: TechTag[];
        max_visible?: number;
        test_id?: string;
    }>(),
    {
        max_visible: 3,
        test_id: 'tech-tag-list',
    },
);

const visibleTags = computed(() => props.tags.slice(0, Math.max(1, props.max_visible)));
const hiddenCount = computed(() => Math.max(0, props.tags.length - visibleTags.value.length));
</script>

<template>
  <div
    class="flex max-w-full flex-wrap items-center gap-1"
    :data-testid="props.test_id"
  >
    <AdminBaseBadge
      v-for="tag in visibleTags"
      :key="tag.id"
      variant="primary"
      size="sm"
      :icon="tag.icon ?? undefined"
      :test_id="`${props.test_id}-chip-${tag.id}`"
    >
      {{ tag.name }}
    </AdminBaseBadge>
    <AdminBaseBadge
      v-if="hiddenCount > 0"
      variant="default"
      size="sm"
      :test_id="`${props.test_id}-ellipsis`"
    >
      +{{ hiddenCount }}
    </AdminBaseBadge>
  </div>
</template>
