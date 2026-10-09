<!-- BlogArticlePage — the public article detail: the two-column layout (ArticleContent reading panel | sticky TableOfContents sidebar), the RelatedPosts section spanning the full width. The article's SEO props + JSON-LD (BlogPosting + BreadcrumbList — incl. the skill names in `keywords`) arrive from the backend and are synced via useSeoUpdater. TOC navigation smooth-scrolls to the heading's `scroll-margin-top` offset (respecting the sticky header); the TOC's IntersectionObserver watches the body's headings via the `target_selector`. The mobile variant collapses the sidebar into the floating TOC button. -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { usePageTracking } from '@shared-cross/Composables/usePageTracking';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import { nextTick } from 'vue';
import ArticleContent from '../Components/ArticleContent.vue';
import RelatedPosts from '../Components/RelatedPosts.vue';
import TableOfContents from '../Components/TableOfContents.vue';
import { useBlogArticlePage } from '../Composables/useBlogArticlePage';
import { ensureBlogIcons } from '../iconRegistration';

type ArticleProps = NonNullable<components['schemas']['BlogArticlePageProps']['props']>;

const props = withDefaults(
    defineProps<{
        article?: ArticleProps['article'];
        reactions?: ArticleProps['reactions'];
        has_projects?: ArticleProps['has_projects'];
        seo?: ArticleProps['seo'];
        test_id?: string;
    }>(),
    {
        article: undefined,
        reactions: null,
        has_projects: false,
        seo: undefined,
        test_id: 'blog-article-page',
    },
);

ensureBlogIcons();
usePageTracking('blog_article', props.article?.id ?? null);
useSeoUpdater();

const { t } = useI18n();

const { navItems, activeRoute, article, headings } = useBlogArticlePage(props);

// The body container id the TOC's IntersectionObserver watches (the
// headings carry `scroll-margin-top` — the pipeline's HeadingIdRenderer
// emits them with ids).
const TOC_TARGET_SELECTOR = '#article-body h2[id], #article-body h3[id]';

function onTocNavigate(id: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    // Wait for the TOC panel to close, then move focus to the heading so
    // the mobile/VoiceOver case does not lose focus (and scroll) when the
    // teleported panel is removed — focusing also announces the heading.
    void nextTick(() => {
        const heading = document.getElementById(id);

        if (heading === null) {
            return;
        }

        heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });
        heading.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    });
}
</script>

<template>
  <PublicPageLayout
    :nav_items="navItems"
    :active_route="activeRoute"
    :test_id="props.test_id"
  >
    <article
      v-if="article !== null"
      class="article-layout"
      :data-testid="props.test_id"
    >
      <ArticleContent
        :article="article"
        body_id="article-body"
        :test_id="`${props.test_id}-content`"
      />

      <TableOfContents
        :headings="headings"
        :target_selector="TOC_TARGET_SELECTOR"
        :test_id="`${props.test_id}-toc`"
        @navigate="onTocNavigate"
      />

      <RelatedPosts
        :articles="article.related"
        :test_id="`${props.test_id}-related`"
      />
    </article>

    <template #footer>
      <a
        class="footer__link"
        href="/rss"
        data-testid="footer-rss-link"
      >
        {{ t('blog.rss_feed') }}
      </a>
    </template>
  </PublicPageLayout>
</template>

<style scoped>
/* The two-column layout: the reading panel and the sticky TOC sidebar;
   the related section spans both columns. Below `--bp-lg` (1024px) the
   grid falls back to a single column. */
.article-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) var(--sidebar-width);
    gap: var(--space-xl);
    align-items: start;

    /* The whole page content spans `--layout-content` (1000px — the
       header/footer width). */
    max-width: var(--layout-content);
    margin: 0 auto var(--space-2xl);
}

@media (width <= 1024px) {
    .article-layout {
        grid-template-columns: 1fr;
    }
}
</style>
