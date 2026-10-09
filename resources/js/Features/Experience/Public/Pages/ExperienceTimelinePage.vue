<script setup lang="ts">
import ExperienceDetailModal from '@experience-public/ExperienceDetailModal.vue';
import TagPopupModal from '@experience-public/TagPopupModal.vue';
import Timeline from '@experience-public/Timeline.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { usePageTracking } from '@shared-cross/Composables/usePageTracking';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import InfiniteScrollLoader from '@shared-public/InfiniteScrollLoader.vue';
import PublicEmptyState from '@shared-public/PublicEmptyState.vue';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import { ref } from 'vue';
import type { TimelineItemView } from '../Composables/useExperienceTimelinePage';
import { useExperienceTimelinePage } from '../Composables/useExperienceTimelinePage';
import { ensureExperienceIcons } from '../iconRegistration';

type ExperienceProps = NonNullable<components['schemas']['ExperienceTimelinePageProps']['props']>;

// Per-property types (not the whole `ExperienceProps` alias): the SFC
// compiler's runtime prop resolution cannot expand the indexed-access
// `NonNullable<...['props']>` type.
const props = withDefaults(
    defineProps<{
        items?: ExperienceProps['items'];
        has_more?: ExperienceProps['has_more'];
        next_cursor?: ExperienceProps['next_cursor'];
        has_projects?: ExperienceProps['has_projects'];
        seo?: ExperienceProps['seo'];
        test_id?: string;
    }>(),
    {
        items: () => [],
        has_more: undefined,
        next_cursor: undefined,
        has_projects: undefined,
        seo: undefined,
        test_id: 'experience-page',
    },
);

const { t } = useI18n();

ensureExperienceIcons();
usePageTracking('experience');
useSeoUpdater();

const { navItems, activeRoute, items, isLoading, loadError, loadMore, retry } =
    useExperienceTimelinePage(props);

const tagPopupOpen = ref(false);
const detailModalOpen = ref(false);
const tagPopupItem = ref<TimelineItemView | null>(null);
const detailModalItem = ref<TimelineItemView | null>(null);

function openTagPopup(itemId: number): void {
    const item = items.value.find((candidate) => candidate.id === itemId) ?? null;
    tagPopupItem.value = item;
    tagPopupOpen.value = item !== null;
}

function openDetailModal(itemId: number): void {
    const item = items.value.find((candidate) => candidate.id === itemId) ?? null;
    detailModalItem.value = item;
    detailModalOpen.value = item !== null;
}
</script>

<template>
  <PublicPageLayout
    :nav_items="navItems"
    :active_route="activeRoute"
    :test_id="props.test_id"
  >
    <h1
      class="page-title"
      :data-testid="`${props.test_id}-title`"
    >
      {{ t('experience.work_experience') }}
    </h1>

    <PublicEmptyState
      v-if="loadError"
      mode="error"
      :message="t('experience.could_not_load')"
      :action_label="t('common.retry')"
      :test_id="`${props.test_id}-error`"
      @action="retry"
    />

    <template v-else>
      <Timeline
        :items="items"
        :test_id="`${props.test_id}-timeline`"
        @show-details="openDetailModal"
        @show-tags="openTagPopup"
      />
      <InfiniteScrollLoader
        :has_more="props.has_more ?? false"
        :is_loading="isLoading"
        :test_id="`${props.test_id}-load-more`"
        @load-more="loadMore"
      />
    </template>

    <TagPopupModal
      v-model="tagPopupOpen"
      :title="tagPopupItem?.position ?? ''"
      :company="tagPopupItem?.company_name ?? null"
      :tags="tagPopupItem?.tech_tags ?? []"
    />
    <ExperienceDetailModal
      v-model="detailModalOpen"
      :item="detailModalItem"
    />
  </PublicPageLayout>
</template>

<style scoped>
/* The title spans the same content width as the timeline below it
   (`--layout-content` — the header/footer width), centered inside the
   layout's xl main container. */
.page-title {
    font-family: var(--font-display);
    font-size: var(--font-size-4xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    max-width: var(--layout-content);
    margin: var(--space-xl) auto var(--space-lg);
}
</style>
