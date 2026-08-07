<!-- QuickLinks (admin-section-components.md:700-705) — dashboard action links rendered as AdminBaseButton anchors. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';

export interface QuickLinkData {
    label: string;
    url: string;
    icon: string;
    variant: 'primary' | 'secondary' | 'ghost' | 'icon-only' | 'outline' | 'danger';
}

const props = withDefaults(
    defineProps<{
        links: Array<QuickLinkData>;
        test_id?: string;
    }>(),
    {
        test_id: 'quick-links',
    },
);
</script>

<template>
  <div
    class="flex flex-wrap gap-3"
    :data-testid="props.test_id"
  >
    <AdminBaseButton
      v-for="(link, index) in props.links"
      :key="`${link.label}-${index}`"
      tag="a"
      :href="link.url"
      :icon="link.icon"
      icon_position="left"
      :variant="link.variant"
      :test_id="`${props.test_id}-link-${index}`"
    >
      {{ link.label }}
    </AdminBaseButton>
  </div>
</template>
