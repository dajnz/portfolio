<!-- RelatedPosts — the related articles section at the bottom of the article page, spanning the layout's full width above the footer. Renders ArticleCards in the `compact` variant (no images); hidden entirely when there are no related articles (the `empty` state); the loading state shows 2-3 card skeletons. -->
<script setup lang="ts">
import type { ArticleCardView } from '@blog-shared/Composables/useArticleCards';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import { computed } from 'vue';

import ArticleCard from './ArticleCard.vue';

const props = withDefaults(
    defineProps<{
        articles?: ArticleCardView[];
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        articles: () => [],
        is_loading: false,
        test_id: 'related-posts',
    },
);

const { t } = useI18n();

// At most three cards render; the section is hidden when empty.
const visibleArticles = computed(() => props.articles.slice(0, 3));
</script>

<template>
  <section
    v-if="visibleArticles.length > 0 || props.is_loading"
    class="related-posts"
    :data-testid="props.test_id"
  >
    <h2 class="section-title">
      {{ t('blog.related_posts') }}
    </h2>
    <div
      v-if="props.is_loading"
      class="related-posts__grid"
      :data-testid="`${props.test_id}-loading`"
    >
      <div
        v-for="index in 3"
        :key="index"
        class="related-posts__skeleton"
      >
        <PublicBaseSkeleton
          shape="text"
          width="70%"
        />
        <PublicBaseSkeleton
          shape="text"
          :repeat="2"
        />
      </div>
    </div>
    <div
      v-else
      class="related-posts__grid"
    >
      <ArticleCard
        v-for="article in visibleArticles"
        :key="article.id"
        :article="article"
        variant="compact"
        :test_id="`${props.test_id}-card-${article.id}`"
      />
    </div>
  </section>
</template>

<style scoped>
.related-posts {
    grid-column: 1 / -1;
    margin-top: var(--space-2xl);
    padding-top: var(--space-xl);
    border-top: 1px solid var(--color-border);
}

.related-posts__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-xl);
    margin-top: var(--space-lg);
}

.related-posts__skeleton {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}
</style>
