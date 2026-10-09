<!-- ProjectCard (public-section-components.md:703-732, mockup projects.html) — the project preview card shared by the landing's featured section (compact) and the projects index (default). The card is a container with a stretched Inertia `<Link>` overlay to `/projects/{slug}` (a DOM sibling of the card's own filter-chip and source links — nested anchors would be invalid HTML); the image area renders ProjectCardCarousel when screenshots exist (its `single` state covers the one-shot case), the static preview image otherwise, and a neat placeholder (icon + label) when neither is present. The tech tags are capped at `tags_limit` (3 on the grid cards, 5 on the list cards — user rework 2026-08-16) with a "+N" chip carrying the remaining names as its accessible name; the chips link to `/projects?tags={id}` (the projects index tag filter). The Sources/Live Demo buttons are anchors opened in a new tab (analytics-tracked); with the overlay link they are DOM siblings, not children, of the card link — no nested-anchor hijack to neutralize. Analytics: `cta_click_github` / `cta_click_demo` (analytics.md:34-37). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import { computed } from 'vue';

import type { ProjectCardView } from '../Composables/useProjectsIndexPage';
import { useProjectsIconsReady } from '../iconRegistration';
import ProjectCardCarousel from './ProjectCardCarousel.vue';

const props = withDefaults(
    defineProps<{
        project?: ProjectCardView | null;
        variant?: 'default' | 'compact';
        is_loading?: boolean;
        tags_linked?: boolean;
        tags_limit?: number;
        test_id?: string;
    }>(),
    {
        project: null,
        variant: 'default',
        is_loading: false,
        tags_linked: true,
        tags_limit: 3,
        test_id: 'project-card',
    },
);

const { t } = useI18n();
const analytics = useAnalytics();
// The Sources GitHub glyph comes from the lazily-registered simple-icons
// subset — rendering before registration would trigger the Iconify
// runtime's remote fallback (blocked by CSP, ADR-0023).
const projectsIconsReady = useProjectsIconsReady();

const visibleTags = computed(() => props.project?.tech_tags.slice(0, props.tags_limit) ?? []);
const overflowCount = computed(() =>
    Math.max(0, (props.project?.tech_tags.length ?? 0) - props.tags_limit),
);
const overflowNames = computed(
    () =>
        props.project?.tech_tags
            .slice(props.tags_limit)
            .map((tag) => tag.name)
            .join(', ') ?? '',
);

function onSourcesClick(event: MouseEvent): void {
    event.stopPropagation();
    analytics.track('cta_click_github');
}

function onDemoClick(event: MouseEvent): void {
    event.stopPropagation();
    analytics.track('cta_click_demo');
}

function tagHref(tag: ProjectCardView['tech_tags'][number]): string {
    return `/projects?tags=${tag.id}`;
}
</script>

<template>
  <div
    v-if="props.is_loading"
    class="card project-card project-card--skeleton"
    :data-testid="props.test_id"
  >
    <PublicBaseSkeleton
      shape="rectangle"
      width="100%"
      height="180px"
    />
    <PublicBaseSkeleton
      shape="text"
      :repeat="2"
    />
    <PublicBaseSkeleton
      shape="pill"
      :repeat="3"
    />
  </div>
  <div
    v-else-if="props.project !== null"
    class="card card--hoverable project-card"
    :class="`project-card--${props.variant}`"
    :data-testid="props.test_id"
  >
    <!-- The stretched overlay link keeps the whole card clickable while
         staying a DOM sibling of the card's own links (filter chips and
         the source buttons) — nested anchors would be invalid HTML. -->
    <Link
      class="project-card__link"
      :href="`/projects/${props.project.slug}`"
      :aria-label="props.project.title"
    />
    <ProjectCardCarousel
      v-if="props.project.screenshots !== null && props.project.screenshots.length > 0"
      :screenshots="props.project.screenshots"
      :test_id="`${props.test_id}-carousel`"
    />
    <div
      v-else-if="props.project.preview_image"
      class="card__image project-card__image"
    >
      <img
        :src="props.project.preview_image"
        :alt="props.project.preview_image_alt"
        width="800"
        height="450"
        loading="lazy"
      >
    </div>
    <div
      v-else
      class="card__image project-card__image project-card__image--placeholder"
      aria-hidden="true"
      :data-testid="`${props.test_id}-placeholder`"
    >
      <!-- The large, faint photo icon on the tinted surface — no text
           label (user rework 2026-08-16: "PREVIEW" dropped, the icon
           3× bigger and less prominent). -->
      <Icon
        class="project-card__placeholder-icon"
        icon="heroicons:photo"
        aria-hidden="true"
      />
    </div>
    <div class="card__content project-card__content">
      <h2 class="card__title project-card__title">
        {{ props.project.title }}
      </h2>
      <p class="card__description project-card__description">
        {{ props.project.brief_description }}
      </p>
      <div
        v-if="props.project.tech_tags.length > 0"
        class="card__tags project-card__tags"
      >
        <TechSkillTag
          v-for="tag in visibleTags"
          :key="tag.id"
          :skill="tag"
          :linked="props.tags_linked"
          :href="tagHref(tag)"
        />
        <span
          v-if="overflowCount > 0"
          class="project-card__tag-overflow"
          :aria-label="t('projects.more_tags', { count: overflowCount, names: overflowNames })"
          :title="overflowNames"
          :data-testid="`${props.test_id}-tags-overflow`"
        >
          +{{ overflowCount }}
        </span>
      </div>
      <div
        v-if="props.project.github_url || props.project.live_demo_url"
        class="card__actions project-card__actions"
      >
        <PublicBaseButton
          v-if="props.project.github_url"
          tag="a"
          :href="props.project.github_url"
          target="_blank"
          rel="noopener"
          variant="outline"
          :icon="projectsIconsReady ? 'simple-icons:github' : undefined"
          :test_id="`${props.test_id}-github`"
          @click.stop="onSourcesClick"
        >
          {{ t('projects.sources') }}
        </PublicBaseButton>
        <PublicBaseButton
          v-if="props.project.live_demo_url"
          tag="a"
          :href="props.project.live_demo_url"
          target="_blank"
          rel="noopener"
          variant="outline"
          icon="heroicons:arrow-top-right-on-square"
          :test_id="`${props.test_id}-demo`"
          @click.stop="onDemoClick"
        >
          {{ t('projects.live_demo') }}
        </PublicBaseButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* The `.card`/`.card__*` look lives in the global stylesheet
   (resources/css/app.css — the mockup's pages.css pattern); this
   component only styles its own modifiers. `.card__image` gives the
   wrapper the 16/9 slot; the inner image fills it. */
.project-card {
    display: block;
    position: relative;
    color: inherit;
    text-decoration: none;
}

.project-card__image {
    overflow: hidden;
}

.project-card__image img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* The no-image placeholder: a tinted surface with a dashed inner frame
   and a large, faint photo icon — 3× the previous size (5.25em) with a
   reduced opacity so it reads as a subtle watermark, no text label
   (user rework 2026-08-16). */
.project-card__image--placeholder {
    flex-direction: column;
    background:
        linear-gradient(135deg, var(--color-bg-tertiary) 25%, transparent 25%) 0 0 / 16px 16px,
        var(--color-bg-secondary);
    border: 1px dashed var(--color-border-strong);
}

.project-card__placeholder-icon {
    width: 5.25em;
    height: 5.25em;
    color: var(--color-text-tertiary);
    opacity: 0.5;
}

/* The "+N" overflow chip mirrors the TechSkillTag look (the shared
   component's scoped styles cannot reach this element — its own scope
   attribute belongs to this component). */
.project-card__tag-overflow {
    display: inline-flex;
    align-items: center;
    padding: 0.2em 0.65em;
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    color: var(--color-text-secondary);
    background: var(--color-bg-secondary);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-full);
    white-space: nowrap;
}

/* The stretched whole-card link overlays the container; the card's own
   links (filter chips, source buttons) and the carousel controls sit
   above it on their own layers. The carousel layer is click-through
   (pointer-events: none) so the card navigation still works over the
   slides — only its buttons receive the pointer. */
.project-card__link {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.project-card__tags,
.project-card__actions {
    position: relative;
    z-index: 2;
}

.project-card-carousel {
    position: relative;
    z-index: 2;
    pointer-events: none;
}

.project-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}
</style>

<!-- The compact action buttons of the card: the shared `.btn` sizing
     (44px height, semibold, solid secondary fill) reads "heavy" and
     wraps inside the ~264px content column of a grid card — the
     ProjectCard scope cannot reach the button's internals, so a plain
     block overrides them (the projects-grid--list precedent). The
     buttons become light outline (contour) chips that always fit on
     one row and stay left-aligned with the card content (user rework
     2026-08-16). The specificity beats the scoped `.btn[data-v-…]`
     rules regardless of stylesheet order. -->
<style>
/* The carousel lives inside the card: the scoped overlay rules make
   the carousel layer click-through; its internal buttons must stay
   clickable — reached here because the scoped styles cannot target the
   carousel component's internals. */
.project-card .project-card-carousel button {
    pointer-events: auto;
}

.project-card .card__actions {
    flex-wrap: nowrap;
    justify-content: flex-start;
}

.project-card .card__actions .btn {
    height: auto;
    padding: 0.3em 0.8em;
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
}
</style>
