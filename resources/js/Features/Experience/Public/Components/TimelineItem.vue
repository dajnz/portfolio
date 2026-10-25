<script setup lang="ts">
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import { computed } from 'vue';
import type { TimelineItemView } from '../Composables/useExperienceTimelinePage';
import { useExperienceIconsReady } from '../iconRegistration';

const props = withDefaults(
    defineProps<{
        item: TimelineItemView;
        tag_visible_count?: number;
        side?: 'left' | 'right';
        test_id?: string;
    }>(),
    {
        tag_visible_count: 3,
        side: 'left',
        test_id: 'timeline',
    },
);

const emit = defineEmits<{
    'show-details': [itemId: number];
    'show-tags': [itemId: number];
}>();

const { t } = useI18n();
const iconsReady = useExperienceIconsReady();

const visibleTags = computed(() => props.item.tech_tags.slice(0, props.tag_visible_count));
const overflowCount = computed(() => props.item.tech_tags.length - visibleTags.value.length);

// The card is clickable as a whole (opens the detail modal) — except on
// its own interactive children (project link, +N / Details buttons).
function onCardClick(event: MouseEvent): void {
    const target = event.target as HTMLElement;
    if (target.closest('a, button') !== null) {
        return;
    }

    emit('show-details', props.item.id);
}
</script>

<template>
  <li
    class="timeline-item"
    :class="[`timeline-item--${props.side}`, { 'timeline-item--current': props.item.is_current }]"
    :data-testid="`${props.test_id}-item-${props.item.id}`"
  >
    <div
      class="card timeline-item__card"
      :data-testid="`${props.test_id}-item-${props.item.id}-card`"
      @click="onCardClick"
    >
      <span
        class="timeline-item__period"
        :data-testid="`${props.test_id}-item-${props.item.id}-period`"
      >
        {{ props.item.period }}
      </span>
      <h2
        class="timeline-item__position"
        :data-testid="`${props.test_id}-item-${props.item.id}-position`"
      >
        {{ props.item.position }}
      </h2>
      <p
        v-if="props.item.company_name !== null"
        class="timeline-item__company"
        :data-testid="`${props.test_id}-item-${props.item.id}-company`"
      >
        {{ props.item.company_name }}<template v-if="props.item.company_country">
          · {{ props.item.company_country }}
        </template>
      </p>
      <p
        v-if="props.item.project_name"
        class="timeline-item__project"
      >
        <a
          v-if="props.item.project_slug"
          class="link"
          :href="`/projects/${props.item.project_slug}`"
          :data-testid="`${props.test_id}-item-${props.item.id}-project`"
        >
          {{ props.item.project_name }}
        </a>
        <a
          v-else-if="props.item.project_url"
          class="link"
          :href="props.item.project_url"
          target="_blank"
          rel="noopener"
          :data-testid="`${props.test_id}-item-${props.item.id}-project`"
        >
          {{ props.item.project_name }}
        </a>
        <span
          v-else
          class="timeline-item__project--plain"
          :data-testid="`${props.test_id}-item-${props.item.id}-project`"
        >
          {{ props.item.project_name }}
        </span>
      </p>
      <!-- eslint-disable vue/no-v-html -->
      <div
        v-if="props.item.short_description !== ''"
        class="timeline-item__description"
        :data-testid="`${props.test_id}-item-${props.item.id}-description`"
        v-html="props.item.short_description"
      />
      <!-- eslint-enable vue/no-v-html -->
      <div
        v-if="iconsReady && props.item.tech_tags.length > 0"
        class="timeline-item__tags"
        :data-testid="`${props.test_id}-item-${props.item.id}-tags`"
      >
        <TechSkillTag
          v-for="tag in visibleTags"
          :key="tag.id"
          :skill="tag"
          :test_id="`${props.test_id}-item-${props.item.id}-tag-${tag.id}`"
        />
        <PublicBaseButton
          v-if="overflowCount > 0"
          variant="ghost"
          :aria-label="t('experience.show_all_tags', { count: overflowCount })"
          :test_id="`${props.test_id}-item-${props.item.id}-tags-overflow`"
          @click="emit('show-tags', props.item.id)"
        >
          +{{ overflowCount }}
        </PublicBaseButton>
      </div>
      <div class="timeline-item__details">
        <PublicBaseButton
          variant="ghost"
          icon="heroicons:arrow-right"
          icon_position="right"
          :test_id="`${props.test_id}-item-${props.item.id}-details`"
          @click="emit('show-details', props.item.id)"
        >
          {{ t('experience.details') }}
        </PublicBaseButton>
      </div>
    </div>
  </li>
</template>

<style scoped>
.timeline-item {
    position: relative;
    margin-bottom: var(--space-xl);

    /* Desktop: card column + central rail column + opposite period column.
       Mobile: single column — the rail moves to the left edge and the
       period stays inside the card. */
    --timeline-rail: 48px;
    --timeline-gap: var(--space-xl);
}

.timeline-item:last-child {
    margin-bottom: 0;
}

/* The dot — centered on the rail (desktop: 50%; mobile: the left-rail
   offset — rail center 7px, the +7px cancels the centering shift) and
   vertically centered on the item (the item's middle, not its top). */
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
    border: 3px solid var(--color-accent);
}

.timeline-item--current::before {
    background: var(--color-accent);
    box-shadow: 0 0 0 4px var(--color-accent-muted);
}

.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--card-radius);
    padding: var(--card-padding);
    box-shadow: var(--card-shadow);
    border-top: 4px solid var(--color-accent-3);
    cursor: pointer;
}

.timeline-item__period {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: var(--font-size-sm);
    color: var(--color-accent);
    font-weight: var(--font-semibold);
    margin-bottom: var(--space-xs);
}

.timeline-item__position {
    font-size: var(--font-size-lg);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-xs);
}

/* The company + country line below the position (the card heading carries
   the position only, the company pair sits under it in the muted style,
   matching the modal headings). Its bottom margin is the
   heading↔description gap (space-sm = 2 × space-xs). */
.timeline-item__company {
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    color: var(--color-text-tertiary);
    margin: 0 0 var(--space-sm);
}

.timeline-item__project {
    display: block;
    margin-bottom: var(--space-sm);
    font-size: var(--font-size-sm);
}

.timeline-item__project--plain {
    color: var(--color-text-secondary);
    text-decoration: none;
    font-weight: var(--font-medium);
    cursor: default;
}

.timeline-item__description {
    font-size: var(--font-size-base);
    line-height: var(--line-height-normal);
    color: var(--color-text-secondary);
    margin: 0 0 var(--space-md);
    overflow-wrap: anywhere;
}

.timeline-item__tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-xs);
}

.timeline-item__details {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin-top: var(--space-sm);
}

/* Desktop: the two-column alternation — the card in the outer column, the
   period moved out of the card into the opposite column, hugging the rail
   and vertically centered on the item (aligned with the item's middle and
   its dot, not the card's first row). */
@media (width >= 768px) {
    .timeline-item {
        display: grid;
        grid-template-columns: 1fr var(--timeline-rail) 1fr;
        column-gap: var(--timeline-gap);
    }

    .timeline-item::before {
        left: 50%;
    }

    .timeline-item--left .timeline-item__card {
        grid-column: 1;
    }

    .timeline-item--right .timeline-item__card {
        grid-column: 3;
    }

    .timeline-item--left .timeline-item__period {
        left: calc(50% + var(--timeline-rail) / 2 + var(--timeline-gap) / 2);
        right: auto;
        text-align: left;
    }

    .timeline-item--right .timeline-item__period {
        right: calc(50% + var(--timeline-rail) / 2 + var(--timeline-gap) / 2);
        left: auto;
        text-align: right;
    }

    .timeline-item__period {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        margin-bottom: 0;
    }
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

<style>
.timeline-item__description > :first-child {
    margin-top: 0;
}

.timeline-item__description > :last-child {
    margin-bottom: 0;
}

.timeline-item__description p {
    margin: 0 0 var(--space-xs);
}

.timeline-item__description ul,
.timeline-item__description ol {
    margin: 0 0 var(--space-xs);
    padding-left: var(--space-lg);
}

.timeline-item__description ul {
    list-style: disc;
}

.timeline-item__description ol {
    list-style: decimal;
}

.timeline-item__description li {
    margin: 0.25rem 0;
}

.timeline-item__description a {
    color: var(--color-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
    font-weight: var(--font-medium);
}

.timeline-item__description a:hover {
    color: var(--color-accent-hover);
}

.timeline-item__description strong {
    color: var(--color-text-primary);
    font-weight: var(--font-semibold);
}

.timeline-item__description code {
    font-family: var(--font-mono);
    font-size: 0.875em;
}
</style>
