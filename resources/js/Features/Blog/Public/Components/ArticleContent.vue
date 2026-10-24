<!-- ArticleContent — the article's reading column: title, "Published: {date} · {n} min read" meta, the interactive article/skill tag chips (all linked to the matching blog filter), the pipeline-rendered body (v-html) and the stats row (views + the feature-flagged ArticleReactions — rendered only when `article.reactions` is non-null).

The body is the server-rendered safe-mode Markdown HTML: code blocks arrive as `<pre data-code-block data-language data-raw>` (highlight.php tokens — no-JS functional) and callouts as `<aside data-callout-type>`. After mounting, the progressive-enhancement pass mounts real CodeBlock/CalloutBlock components in place of those markers — the copy buttons and the per-type callout icons; the pass re-runs whenever `content_html` changes (article navigation) after removing the previous mounts.

The scroll tracker fires the `article_read` analytics event once at 75%+ scroll depth. -->
<script setup lang="ts">
import { formatArticleDate } from '@blog-shared/Composables/useArticleCards';
import { Icon } from '@iconify/vue';
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useAboutTimeZone } from '@shared-cross/Composables/useAboutTimeZone';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReadingProgress } from '@shared-cross/Composables/useReadingProgress';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import {
    computed,
    createVNode,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    render,
    watch,
} from 'vue';

import type { ArticleDetailView } from '../Composables/useBlogArticlePage';
import ArticleReactions from './ArticleReactions.vue';
import CalloutBlock from './CalloutBlock.vue';
import CodeBlock from './CodeBlock.vue';

type CalloutType = 'warning' | 'tip' | 'important' | 'tradeoff';

const CALLOUT_TYPES: readonly CalloutType[] = ['warning', 'tip', 'important', 'tradeoff'];

const props = withDefaults(
    defineProps<{
        article?: ArticleDetailView | null;
        is_loading?: boolean;
        /** The id of the body container — the TableOfContents'
         *  IntersectionObserver target scope (the page passes its
         *  `#article-body` selector). */
        body_id?: string;
        test_id?: string;
    }>(),
    {
        article: null,
        is_loading: false,
        body_id: '',
        test_id: 'article-content',
    },
);

const { t, locale } = useI18n();
const aboutTimeZone = useAboutTimeZone();

// The article's tags as one interactive chip list — article tags
// (`?article_tags=`) and skill tags (`?skill_tags=`) render identically
// (the shared TechSkillTag chip); only the filter destination differs.
interface ArticleChip {
    id: number;
    name: string;
    icon: string;
    type: 'tag' | 'skill';
}

const tagChips = computed<ArticleChip[]>(() => [
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

function tagHref(tag: ArticleChip): string {
    return tag.type === 'skill' ? `/blog?skill_tags=${tag.id}` : `/blog?article_tags=${tag.id}`;
}

const bodyEl = ref<HTMLElement | null>(null);
const mountedHosts: { host: HTMLElement; dispose: () => void }[] = [];

function metaDate(): string {
    return formatArticleDate(props.article?.published_at ?? null, locale.value, aboutTimeZone);
}

function userReaction(): 'like' | 'dislike' | null {
    const value = props.article?.reactions?.user_reaction;

    return value === 'like' || value === 'dislike' ? value : null;
}

function reactionLikes(): number {
    return props.article?.reactions?.likes ?? 0;
}

function reactionDislikes(): number {
    return props.article?.reactions?.dislikes ?? 0;
}

function cleanupMounts(): void {
    for (const { host, dispose } of mountedHosts.splice(0)) {
        dispose();
        host.remove();
    }
}

/**
 * The progressive-enhancement pass: mounts CodeBlock over each
 * `pre[data-code-block]` (the language + raw-code attributes become the
 * component props; the inner `<code>` HTML becomes `code_html`) and
 * CalloutBlock over each `aside[data-callout-type]` (title text +
 * content HTML extracted from the static markup). The static no-JS
 * output is fully styled — the mounts only add the interactivity (copy
 * buttons, callout icons).
 */
function enhance(): void {
    const body = bodyEl.value;
    if (body === null || typeof document === 'undefined') {
        return;
    }

    cleanupMounts();

    let codeIndex = 0;
    for (const pre of body.querySelectorAll('pre[data-code-block]')) {
        const host = document.createElement('div');
        pre.replaceWith(host);

        const vnode = createVNode(CodeBlock, {
            code_html: pre.innerHTML,
            language: pre.getAttribute('data-language') ?? '',
            raw_code: pre.getAttribute('data-raw') ?? '',
            test_id: `${props.test_id}-code-${codeIndex}`,
        });
        render(vnode, host);
        mountedHosts.push({
            host,
            dispose: () => {
                render(null, host);
            },
        });
        codeIndex += 1;
    }

    let calloutIndex = 0;
    for (const aside of body.querySelectorAll('aside[data-callout-type]')) {
        const type = aside.getAttribute('data-callout-type');
        if (type === null || !CALLOUT_TYPES.includes(type as CalloutType)) {
            continue;
        }

        const host = document.createElement('div');
        aside.replaceWith(host);

        const vnode = createVNode(CalloutBlock, {
            type,
            title: aside.querySelector('.callout__title')?.textContent ?? '',
            content_html: aside.querySelector('.callout__content')?.innerHTML ?? '',
            test_id: `${props.test_id}-callout-${calloutIndex}`,
        });
        render(vnode, host);
        mountedHosts.push({
            host,
            dispose: () => {
                render(null, host);
            },
        });
        calloutIndex += 1;
    }
}

// The reading-progress tracker follows the article id across Inertia
// navigations (each article fires the `article_read` event at most
// once).
let readingProgress: { cleanup: () => void } | null = null;

watch(
    () => props.article?.id,
    (id) => {
        readingProgress?.cleanup();
        readingProgress = id === undefined || id === 0 ? null : useReadingProgress(id);
    },
    { immediate: true },
);

// The enhancer runs after the body's v-html is in the DOM and again
// on content changes (the previous mounts are removed first).
onMounted(() => {
    void nextTick(enhance);
});

watch(
    () => props.article?.content_html,
    () => {
        void nextTick(enhance);
    },
);

onBeforeUnmount(cleanupMounts);
</script>

<template>
  <article
    class="article-content"
    :data-testid="props.test_id"
  >
    <template v-if="props.is_loading">
      <PublicBaseSkeleton
        shape="text"
        width="60%"
        height="32px"
      />
      <PublicBaseSkeleton
        shape="text"
        width="30%"
      />
      <PublicBaseSkeleton
        shape="text"
        :repeat="6"
      />
    </template>
    <template v-else-if="props.article !== null">
      <h1
        class="article-content__title"
        :data-testid="`${props.test_id}-title`"
      >
        {{ props.article.title }}
      </h1>

      <div
        class="article-content__meta"
        :data-testid="`${props.test_id}-meta`"
      >
        <span v-if="metaDate() !== ''">
          {{ t('blog.published_on', { date: metaDate() }) }}
        </span>
        <span v-if="props.article.reading_time_minutes > 0">
          {{ t('blog.min_read', { minutes: props.article.reading_time_minutes }) }}
        </span>
      </div>

      <div
        v-if="tagChips.length > 0"
        class="article-content__tags"
        :data-testid="`${props.test_id}-tags`"
      >
        <TechSkillTag
          v-for="tag in tagChips"
          :key="`${tag.type}-${tag.id}`"
          :skill="tag"
          linked
          :href="tagHref(tag)"
          :test_id="`${props.test_id}-${tag.type}-${tag.id}`"
        />
      </div>

      <!-- The pipeline-rendered body: safe-mode CommonMark + callouts +
           server-highlighted code; the enhancer mounts the
           CodeBlock/CalloutBlock components over the markers after
           mount. -->
      <!-- eslint-disable vue/no-v-html -->
      <div
        :id="props.body_id || undefined"
        ref="bodyEl"
        class="article-content__body"
        :data-testid="`${props.test_id}-body`"
        v-html="props.article.content_html"
      />
      <!-- eslint-enable vue/no-v-html -->

      <div
        class="article-content__stats"
        :data-testid="`${props.test_id}-stats`"
      >
        <span class="article-content__views">
          <Icon
            icon="heroicons:eye"
            aria-hidden="true"
          />
          {{ t('blog.views', { count: props.article.views_count }) }}
        </span>
        <ArticleReactions
          v-if="props.article.reactions !== null"
          :article_id="props.article.id"
          :user_reaction="userReaction()"
          :likes="reactionLikes()"
          :dislikes="reactionDislikes()"
          :test_id="`${props.test_id}-reactions`"
        />
      </div>
    </template>
  </article>
</template>

<style scoped>
/* The reading column: a card-like panel on the page background. The
   layout (two-column grid with the TOC) is the page's. */
.article-content {
    max-width: calc(var(--wide-max-width) - var(--sidebar-width) - var(--space-xl));
    min-width: 0;
    margin-top: var(--space-xl);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-xl) var(--space-xl) var(--space-2xl);
    box-shadow: var(--card-shadow);
}

.article-content__title {
    font-family: var(--font-display);
    font-size: var(--font-size-4xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    line-height: var(--line-height-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-md);
}

.article-content__meta {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: var(--space-md);
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
    margin-bottom: var(--space-md);
}

.article-content__tags {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-xs);
    margin-bottom: var(--space-xl);
}

/* The rendered body — the typography base: the root box is scoped (the
   root element carries the scope attribute); the INNER element styles
   live in the plain block below (v-html content never receives the
   scope attribute). */
.article-content__body {
    font-size: var(--font-size-base);
    line-height: var(--line-height-relaxed);
    color: var(--color-text-secondary);

    /* Long unbroken strings (long words, URLs) must wrap inside the
       reading column: `anywhere` (not `break-word`) also caps the
       content's min-content width, so the panel never grows past the
       grid column. Code blocks (`pre`) reset it below to keep their
       horizontal scroll. */
    overflow-wrap: anywhere;
}

.article-content__body > * + * {
    margin-top: var(--space-lg);
}

/* The stats row: views + reactions, above the panel's bottom edge. */
.article-content__stats {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-xl);
    margin-top: var(--space-2xl);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--color-border);
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.article-content__views {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
}
</style>

<!-- The markdown element styles are intentionally global (non-scoped):
     the pipeline HTML is injected via v-html and its elements never
     carry the component's scope attribute, so scoped rules can never
     match them (stylelint forbids `:deep`; the ProjectDetailPage
     `markdown-body` precedent). The `.article-content__body` class is
     unique to this page. -->
<style>
.article-content__body h2,
.article-content__body h3,
.article-content__body h4 {
    font-family: var(--font-display);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);

    /* The heading anchor offset — the TOC/scrollIntoView target lands
       below the sticky header. */
    scroll-margin-top: calc(var(--header-height) + var(--space-md));
}

.article-content__body h2 {
    font-size: var(--font-size-2xl);
    margin-top: var(--space-2xl);
}

.article-content__body h3 {
    font-size: var(--font-size-xl);
    margin-top: var(--space-xl);
}

.article-content__body h4 {
    font-size: var(--font-size-lg);
    margin-top: var(--space-lg);
}

.article-content__body h2 + *,
.article-content__body h3 + *,
.article-content__body h4 + * {
    margin-top: var(--space-sm);
}

.article-content__body p {
    margin: 0;
}

.article-content__body ul,
.article-content__body ol {
    padding-left: var(--space-lg);
    margin: 0;
}

.article-content__body ul {
    list-style: disc;
}

.article-content__body ol {
    list-style: decimal;
}

.article-content__body li {
    margin-bottom: var(--space-xs);
}

.article-content__body li:last-child {
    margin-bottom: 0;
}

.article-content__body li > ul,
.article-content__body li > ol {
    margin-top: var(--space-xs);
}

.article-content__body a {
    color: var(--color-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color var(--transition-fast);
}

.article-content__body a:hover {
    color: var(--color-accent-hover);
}

.article-content__body blockquote {
    margin: var(--space-md) 0;
    padding: var(--space-xs) 0 var(--space-xs) var(--space-md);
    border-left: 3px solid var(--color-accent);
    color: var(--color-text-tertiary);
}

.article-content__body blockquote > * + * {
    margin-top: var(--space-xs);
}

.article-content__body strong {
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
}

.article-content__body em {
    font-style: italic;
}

.article-content__body hr {
    border: none;
    border-top: 1px solid var(--color-border);
    margin: var(--space-lg) 0;
}

.article-content__body code {
    font-family: var(--font-mono);
    font-size: 0.9em;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-sm);
    padding: 0.1em 0.4em;
}

.article-content__body pre {
    /* The block's code keeps its layout — the body's inherited
       `overflow-wrap: anywhere` is reset so long lines scroll
       horizontally (`overflow-x: auto`) instead of wrapping. */
    overflow-wrap: normal;
}

.article-content__body pre code {
    /* The block's code must not carry the INLINE code treatment
       (background/padding/radius from the rule above — its class
       specificity beats the tokens' `pre code` reset): the highlighted
       tokens sit directly on the block's surface. */
    background: none;
    padding: 0;
    border-radius: 0;
    font-size: var(--font-size-sm);
    color: inherit;
}

.article-content__body img {
    display: block;
    max-width: 100%;
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    box-shadow: var(--card-shadow);
}

.article-content__body table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
    font-size: var(--font-size-sm);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
}

.article-content__body th,
.article-content__body td {
    padding: var(--space-xs) var(--space-sm);
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

.article-content__body th:not(:last-child) {
    border-right: 1px solid var(--color-border);
}

.article-content__body td:not(:last-child) {
    border-bottom: 1px solid var(--color-border);
    border-right: 1px solid var(--color-border);
}

.article-content__body table tr:last-child td {
    border-bottom: none;
}

.article-content__body th {
    background: var(--color-bg-secondary);
    font-weight: var(--font-semibold);
    color: var(--color-text-primary);
}
</style>
