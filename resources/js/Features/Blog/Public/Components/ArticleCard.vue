<!-- ArticleCard — the blog article preview card shared by the blog index (default variant) and the article page's related section (compact variant — no image). The card is a container with a stretched Inertia `<Link>` overlay to `/blog/{slug}` (a DOM sibling of the card's own filter-chip links — nested anchors would be invalid HTML); the lead image renders with the resolved per-locale alt (placeholder icon when absent — the `empty_image` state), the search results' `short_description` may carry the FTS `<mark>` snippet (server-sanitized, rendered via v-html), the meta row shows the localized date + reading time, and the article tags + skill tags render as the same linked chip (article tags → `/blog?article_tags={id}`, skill tags → `/blog?skill_tags={id}`), capped at 3 with a "+N" overflow chip. Hover prefetches the article page data after the 100 ms linger (`router.prefetch` is a genuine cache-warming fetch, no navigation). -->
<script setup lang="ts">
import { type ArticleCardView, formatArticleDate } from '@blog-shared/Composables/useArticleCards';
import { Link, router } from '@inertiajs/vue3';
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicImagePlaceholder from '@shared-public/PublicImagePlaceholder.vue';
import { computed, ref } from 'vue';

const HOVER_PREFETCH_DELAY_MS = 100;

const props = withDefaults(
    defineProps<{
        article?: ArticleCardView | null;
        variant?: 'default' | 'compact';
        is_loading?: boolean;
        /** The max number of rendered tag chips (article tags + skill
         *  tags combined); the excess collapses into a "+N" chip
         *  (3 grid / 5 list). */
        tags_limit?: number;
        test_id?: string;
    }>(),
    {
        article: null,
        variant: 'default',
        is_loading: false,
        tags_limit: 3,
        test_id: 'article-card',
    },
);

const { t, locale } = useI18n();

// The combined chip list — article tags first, then the skill tags; the
// cap applies to the combined total. Both tag types render as the same
// interactive chip (only the filter destination differs: `?article_tags=`
// vs `?skill_tags=`).
interface ArticleCardChip {
    id: number;
    name: string;
    icon: string;
    type: 'tag' | 'skill';
}

const allTags = computed<ArticleCardChip[]>(() => [
    ...(props.article?.tags ?? []).map((tag) => ({
        id: tag.id,
        name: tag.name,
        icon: '',
        type: 'tag' as const,
    })),
    ...(props.article?.skill_tags ?? []).map((tag) => ({
        id: tag.id,
        name: tag.name,
        icon: tag.icon,
        type: 'skill' as const,
    })),
]);
const visibleTags = computed(() => allTags.value.slice(0, props.tags_limit));
const overflowCount = computed(() => Math.max(0, allTags.value.length - props.tags_limit));
const overflowNames = computed(
    () =>
        allTags.value
            .slice(props.tags_limit)
            .map((tag) => tag.name)
            .join(', ') ?? '',
);

function tagHref(tag: ArticleCardChip): string {
    return tag.type === 'skill' ? `/blog?skill_tags=${tag.id}` : `/blog?article_tags=${tag.id}`;
}

const metaDate = computed(() =>
    formatArticleDate(props.article?.published_at ?? null, locale.value),
);

// Hover-based prefetch: the timer arms on mouseenter and fires
// `router.prefetch` after the linger — a genuine cache-warming
// page-data fetch with no navigation. An in-flight prefetch cannot be
// cancelled (Inertia has no public abort for it — the `onCancelToken`
// bridge belongs to visits); leaving early only cancels the pending
// timer.
const prefetchTimer = ref<ReturnType<typeof setTimeout> | null>(null);

function onEnter(): void {
    if (props.article === null || props.article === undefined) {
        return;
    }

    if (prefetchTimer.value !== null) {
        clearTimeout(prefetchTimer.value);
    }

    prefetchTimer.value = setTimeout(() => {
        prefetchTimer.value = null;
        router.prefetch(`/blog/${props.article?.slug}`);
    }, HOVER_PREFETCH_DELAY_MS);
}

function onLeave(): void {
    if (prefetchTimer.value !== null) {
        clearTimeout(prefetchTimer.value);
        prefetchTimer.value = null;
    }
}
</script>

<template>
  <div
    v-if="props.is_loading"
    class="card article-card article-card--skeleton"
    :data-testid="props.test_id"
  >
    <PublicBaseSkeleton
      shape="text"
      width="70%"
    />
    <PublicBaseSkeleton
      shape="text"
      :repeat="3"
    />
    <PublicBaseSkeleton
      shape="text"
      width="40%"
    />
    <PublicBaseSkeleton
      shape="pill"
      :repeat="2"
    />
  </div>
  <div
    v-else-if="props.article !== null"
    class="card card--hoverable article-card"
    :class="`article-card--${props.variant}`"
    :data-testid="props.test_id"
  >
    <!-- The stretched overlay link keeps the whole card clickable while
         staying a DOM sibling of the card's own links (filter chips) —
         nested anchors would be invalid HTML (element-permitted-content). -->
    <Link
      class="article-card__link"
      :href="`/blog/${props.article.slug}`"
      :aria-label="props.article.title"
      @mouseenter="onEnter"
      @mouseleave="onLeave"
    />
    <template v-if="props.variant === 'default'">
      <img
        v-if="props.article.lead_image"
        class="article-card__image"
        :src="props.article.lead_image"
        :alt="props.article.lead_image_alt"
        width="800"
        height="450"
        loading="lazy"
      >
      <div
        v-else
        class="article-card__image"
      >
        <PublicImagePlaceholder :test_id="`${props.test_id}-placeholder`" />
      </div>
    </template>
    <div class="card__content article-card__content">
      <h2 class="card__title article-card__title">
        {{ props.article.title }}
      </h2>
      <!-- The FTS snippet may carry `<mark>` tags — the server sanitizes
           everything else (ArticleCardDto::sanitizeSnippet), so only the
           highlight survives; eslint accepts the v-html by design (the
           ProjectDetailPage precedent). -->
      <!-- eslint-disable vue/no-v-html -->
      <p
        class="card__description article-card__description"
        v-html="props.article.short_description"
      />
      <!-- eslint-enable vue/no-v-html -->
      <div
        v-if="metaDate !== '' || props.article.reading_time_minutes > 0"
        class="card__meta article-card__meta"
      >
        <span
          v-if="metaDate !== ''"
          class="article-card__meta-date"
        >{{ metaDate }}</span>
        <span
          v-if="props.article.reading_time_minutes > 0"
          class="article-card__meta-read"
        >
          {{ t('blog.min_read', { minutes: props.article.reading_time_minutes }) }}
        </span>
      </div>
      <div
        v-if="visibleTags.length > 0"
        class="card__tags article-card__tags"
      >
        <TechSkillTag
          v-for="tag in visibleTags"
          :key="`${tag.type}-${tag.id}`"
          :skill="tag"
          linked
          :href="tagHref(tag)"
          :test_id="`${props.test_id}-${tag.type}-${tag.id}`"
        />
        <span
          v-if="overflowCount > 0"
          class="article-card__tag-overflow"
          :aria-label="t('blog.more_tags', { count: overflowCount, names: overflowNames })"
          :title="overflowNames"
          :data-testid="`${props.test_id}-tags-overflow`"
        >
          +{{ overflowCount }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.article-card {
    display: block;
    position: relative;
    color: inherit;
    text-decoration: none;
}

/* The 16/9 lead-image slot. */
.article-card__image {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border-radius: var(--radius-md);
    margin-bottom: var(--space-md);
    background: var(--color-bg-tertiary);
    overflow: hidden;
}

/* The stretched whole-card link overlays the container; the card's own
   links (filter chips) sit above it on their own layer. */
.article-card__link {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.article-card__tags {
    position: relative;
    z-index: 2;
}

/* The "+N" overflow chip mirrors the TechSkillTag look. */
.article-card__tag-overflow {
    display: inline-flex;
    align-items: center;
    padding: 0.2em 0.65em;
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    color: var(--color-text-secondary);
    background: var(--color-bg-inactive);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-full);
    white-space: nowrap;
}

.article-card__meta {
    display: flex;
    justify-content: space-between;
    gap: var(--space-md);
    margin-top: var(--space-lg);
    font-size: var(--font-size-xs);
    color: var(--color-text-tertiary);
}

.article-card--skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}
</style>

<!-- The card meta row of the compact related card sits in the shared
     stylesheet context — `.card__meta`/`.card__tags` are global (the
     `.card` pattern in app.css); nothing extra needed here. -->
