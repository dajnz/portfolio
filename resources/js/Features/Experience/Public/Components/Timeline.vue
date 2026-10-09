<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicEmptyState from '@shared-public/PublicEmptyState.vue';
import type { TimelineItemView } from '../Composables/useExperienceTimelinePage';
import TimelineItem from './TimelineItem.vue';

const props = withDefaults(
    defineProps<{
        items?: TimelineItemView[];
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        items: () => [],
        is_loading: false,
        test_id: 'timeline',
    },
);

// The page owns the modals — the item events bubble through Timeline
// (TimelineItem's show-details/show-tags must reach the page).
const emit = defineEmits<{
    'show-details': [itemId: number];
    'show-tags': [itemId: number];
}>();

const { t } = useI18n();

const skeletonItems = [0, 1, 2, 3];
</script>

<template>
  <template v-if="props.is_loading || props.items.length > 0">
    <div class="timeline-shell">
      <span
        class="timeline__cap"
        aria-hidden="true"
        data-testid="timeline-cap"
      >
        <Icon icon="heroicons:briefcase" />
      </span>
      <ol
        v-if="props.is_loading"
        class="timeline"
        data-state="loading"
        :data-testid="props.test_id"
      >
        <li
          v-for="skeleton in skeletonItems"
          :key="skeleton"
          class="timeline-item"
          :class="skeleton % 2 === 0 ? 'timeline-item--left' : 'timeline-item--right'"
        >
          <div class="card timeline-item__card">
            <PublicBaseSkeleton
              shape="text"
              width="120px"
            />
            <PublicBaseSkeleton
              shape="text"
              width="60%"
            />
            <PublicBaseSkeleton
              shape="text"
              :repeat="2"
            />
            <PublicBaseSkeleton
              shape="pill"
              :repeat="2"
              width="4.5em"
              height="1.5em"
            />
          </div>
        </li>
      </ol>

      <ol
        v-else
        class="timeline"
        :data-testid="props.test_id"
      >
        <TimelineItem
          v-for="(item, index) in props.items"
          :key="item.id"
          :item="item"
          :side="index % 2 === 0 ? 'left' : 'right'"
          :test_id="props.test_id"
          @show-details="emit('show-details', $event)"
          @show-tags="emit('show-tags', $event)"
        />
      </ol>
    </div>
  </template>

  <PublicEmptyState
    v-else
    mode="empty"
    :message="t('experience.no_experience')"
    :test_id="`${props.test_id}-empty`"
  />
</template>

<style scoped>
/* The shell hosts the rail cap and carries the content width: the timeline
   spans the full `--layout-content` (1000px — the header/footer width). */
.timeline-shell {
    position: relative;
    max-width: var(--layout-content);
    margin: 0 auto var(--space-2xl);
}

/* The rail (desktop variant): the bottom ~50 px fade to transparent via a
   mask gradient so the rail's end dissolves into the page background
   instead of a hard edge. Dots are unaffected (they live on
   .timeline-item::before). Mobile moves the rail to the left edge. */
.timeline {
    position: relative;
    list-style: none;
    margin: 0;
    padding: 0 0 0 var(--space-xl);
}

.timeline::before {
    content: '';
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 6px;
    width: 2px;
    background: var(--color-border);
    border-radius: var(--radius-full);
    mask-image: linear-gradient(to bottom, #000 0, #000 calc(100% - 50px), transparent 100%);
}

/* The semantic cap for the rail's top end: a briefcase badge centered on
   the rail's start — the career-timeline marker. Positioned on the shell so
   it follows the rail (left edge on mobile, center on desktop) without
   being a list item. */
.timeline__cap {
    position: absolute;
    top: 4px;
    left: 7px;
    transform: translate(-50%, -50%);
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: var(--radius-full);
    background: var(--color-accent);
    color: var(--color-text-inverse);
    font-size: 15px;
    box-shadow: var(--shadow-sm);
}

/* Desktop: the centered line with the items alternating on both sides.
   The mobile rail-offset padding is dropped so the cards span their full
   column. */
@media (width >= 768px) {
    .timeline {
        padding: 0;
    }

    .timeline::before {
        left: 50%;
        transform: translateX(-50%);
    }

    .timeline__cap {
        left: 50%;
    }
}

/* The skeleton items reuse the TimelineItem layout: the li carries the
   side alternation and the rail/dot positioning, the card hosts the
   skeleton rows. */
.timeline-item {
    position: relative;
    margin-bottom: var(--space-xl);
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    top: 50%;
    left: calc(-1 * var(--space-xl) + 7px);
    transform: translate(-50%, -50%);
    width: 14px;
    height: 14px;
    border-radius: var(--radius-full);
    background: var(--color-surface);
    border: 3px solid var(--color-border);
}

.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    padding: var(--card-padding);
    box-shadow: var(--card-shadow);
    border-top: 4px solid var(--color-accent-3);
}

@media (width >= 768px) {
    .timeline-item {
        display: grid;
        grid-template-columns: 1fr var(--timeline-rail, 48px) 1fr;
        column-gap: var(--timeline-gap, var(--space-xl));
    }

    .timeline-item::before {
        left: 50%;
    }

    .timeline-item--left .card {
        grid-column: 1;
    }

    .timeline-item--right .card {
        grid-column: 3;
    }
}
</style>
