<!-- ArticleCard (public-section-components.md:800-816, mockup blog.html + blog-article.html) — the blog article preview card shared by the blog index (default variant) and the article page's related section (compact variant — no image). Root is an Inertia `<Link>` to `/blog/{slug}`; the lead image renders with the resolved per-locale alt (placeholder icon when absent — the `empty_image` state), the search results' `short_description` may carry the FTS `<mark>` snippet (B16 — server-sanitized, rendered via v-html), the meta row shows the localized date + reading time, the article tags render as accent badges and the skill tags as linked TechSkillTag chips (`/blog?skill_tags={id}`), capped at 3 with a "+N" overflow chip (the B264 projects convention, mockup parity). Hover prefetches the article page data after the 100 ms linger (architecture.md:1264-1271 — `router.prefetch` is a genuine cache-warming fetch, no navigation; the B262 hazard does not apply). -->
<script setup lang="ts">
import { type ArticleCardView, formatArticleDate } from '@blog-shared/Composables/useArticleCards';
import { Icon } from '@iconify/vue';
import { Link, router } from '@inertiajs/vue3';
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseBadge from '@shared-public/PublicBaseBadge.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import { computed, ref } from 'vue';

const HOVER_PREFETCH_DELAY_MS = 100;

const props = withDefaults(
    defineProps<{
        article?: ArticleCardView | null;
        variant?: 'default' | 'compact';
        is_loading?: boolean;
        /** The max number of rendered tag chips (article tags + skill
         *  tags combined); the excess collapses into a "+N" chip
         *  (3 grid / 5 list — user rework 2026-08-16). */
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

// The combined chip list — article tags first, then the skill tags
// (the mockup's order); the cap applies to the combined total.
const allTags = computed(() => [
    ...(props.article?.tags ?? []),
    ...(props.article?.skill_tags ?? []),
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

const metaDate = computed(() =>
    formatArticleDate(props.article?.published_at ?? null, locale.value),
);

// Hover-based prefetch (architecture.md:1264-1271): the timer arms on
// mouseenter and fires `router.prefetch` after the linger — a genuine
// cache-warming page-data fetch with no navigation. An in-flight
// prefetch cannot be cancelled (Inertia has no public abort for it —
// the B75 `onCancelToken` bridge belongs to visits); leaving early
// only cancels the pending timer.
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
  <Link
    v-if="props.is_loading"
    class="card article-card article-card--skeleton"
    href="#"
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
  </Link>
  <Link
    v-else-if="props.article !== null"
    class="card card--hoverable article-card"
    :class="`article-card--${props.variant}`"
    :href="`/blog/${props.article.slug}`"
    :data-testid="props.test_id"
    @mouseenter="onEnter"
    @mouseleave="onLeave"
  >
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
        class="article-card__image article-card__image--placeholder"
        aria-hidden="true"
        :data-testid="`${props.test_id}-placeholder`"
      >
        <!-- The large, faint photo icon on the tinted surface — the
             projects card's placeholder look (B266). -->
        <Icon
          class="article-card__placeholder-icon"
          icon="heroicons:photo"
          aria-hidden="true"
        />
      </div>
    </template>
    <div class="card__content article-card__content">
      <h3 class="card__title article-card__title">
        {{ props.article.title }}
      </h3>
      <!-- The FTS snippet may carry `<mark>` tags (B16) — the server
           sanitizes everything else (ArticleCardDto::sanitizeSnippet),
           so only the highlight survives; eslint accepts the v-html by
           design (the ProjectDetailPage precedent). -->
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
        v-if="visibleTags.length > 0 || props.article.skill_tags.length > 0"
        class="card__tags article-card__tags"
      >
        <template v-for="tag in visibleTags">
          <PublicBaseBadge
            v-if="'slug' in tag"
            :key="tag.id"
            variant="primary"
            :test_id="`${props.test_id}-tag-${tag.id}`"
          >
            {{ tag.name }}
          </PublicBaseBadge>
          <TechSkillTag
            v-else
            :key="tag.id"
            :skill="tag"
            linked
            :test_id="`${props.test_id}-skill-${tag.id}`"
          />
        </template>
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
  </Link>
</template>

<style scoped>
.article-card {
    display: block;
    color: inherit;
    text-decoration: none;
}

/* The 16/9 lead-image slot (the mockup's `.article-card__image`). */
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

/* The no-image placeholder: the tinted surface + dashed frame + faint
   photo icon of the projects card (B264/B266 look). */
.article-card__image--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        linear-gradient(135deg, var(--color-bg-tertiary) 25%, transparent 25%) 0 0 / 16px 16px,
        var(--color-bg-secondary);
    border: 1px dashed var(--color-border-strong);
}

.article-card__placeholder-icon {
    width: 5.25em;
    height: 5.25em;
    color: var(--color-text-tertiary);
    opacity: 0.5;
}

/* The "+N" overflow chip mirrors the TechSkillTag look. */
.article-card__tag-overflow {
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
