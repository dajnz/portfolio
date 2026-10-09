<!-- TableOfContents (public-section-components.md:1312-1326, mockup blog-article.html) — the sticky sidebar listing the article's h2/h3 headings. Desktop: a sticky glass panel beside the article column; mobile (below `--bp-lg`): a collapsible floating button in the bottom-right corner expanding into a panel with the same list. The heading currently in view gets `.is-active` (IntersectionObserver over the article body's heading elements — matched via `target_selector`, the page's responsibility to keep in sync with the extracted `headings`); clicking a link emits `navigate` with the heading id — the parent scrolls (smooth, `scroll-margin-top` on the headings). Empty headings → the component renders nothing. -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import type { ArticleHeading } from '../Composables/useBlogArticlePage';

const props = withDefaults(
    defineProps<{
        headings?: ArticleHeading[];
        /** The CSS selector of the article body's heading elements the
         *  IntersectionObserver watches (e.g. `#article-body h2[id],
         *  #article-body h3[id]`). Empty disables active tracking. */
        target_selector?: string;
        test_id?: string;
    }>(),
    {
        headings: () => [],
        target_selector: '',
        test_id: 'blog-toc',
    },
);

const emit = defineEmits<{ navigate: [id: string] }>();

const { t } = useI18n();

const activeId = ref('');
const isMobileOpen = ref(false);
// The mobile floating control is teleported to <body> so `position:
// fixed` escapes the `container-type: inline-size` containing block of
// the page's centered <main> (a11y 13.6 #19). Client-gated: Vue SSR does
// not inline Teleport-to-<body> content, so rendering it only after mount
// avoids a hydration mismatch.
const mounted = ref(false);
const mobileEl = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

function observe(): void {
    if (typeof IntersectionObserver === 'undefined' || props.target_selector === '') {
        return;
    }

    const elements = [...document.querySelectorAll(props.target_selector)];

    if (elements.length === 0) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeId.value = entry.target.getAttribute('id') ?? activeId.value;
                }
            }
        },
        // The active band: below the sticky header region and above the
        // bottom quarter — the heading crossing into it takes the
        // highlight.
        { rootMargin: '-20% 0px -65% 0px' },
    );

    for (const element of elements) {
        observer.observe(element);
    }
}

function cleanup(): void {
    observer?.disconnect();
    observer = null;
}

function navigate(id: string): void {
    emit('navigate', id);
    isMobileOpen.value = false;
}

// Escape closes the mobile panel and returns focus to its toggle
// (accessibility.md keyboard navigation).
function closeMobile(): void {
    if (!isMobileOpen.value) {
        return;
    }

    isMobileOpen.value = false;
    mobileEl.value?.querySelector<HTMLElement>('button')?.focus();
}

onMounted(() => {
    mounted.value = true;
    observe();
});
onBeforeUnmount(cleanup);
</script>

<template>
  <aside
    class="table-of-contents"
    data-state="desktop"
    :aria-label="t('blog.table_of_contents')"
    :data-testid="props.test_id"
  >
    <h2 class="table-of-contents__title">
      {{ t('blog.table_of_contents') }}
    </h2>
    <!-- User rework 2026-08-16: the sidebar is NEVER hidden — a
         headingless article shows the muted empty message instead of
         the list. -->
    <p
      v-if="props.headings.length === 0"
      class="table-of-contents__empty"
      :data-testid="`${props.test_id}-empty`"
    >
      {{ t('blog.toc_empty') }}
    </p>
    <ul
      v-else
      class="table-of-contents__list"
    >
      <li
        v-for="heading in props.headings"
        :key="heading.id"
      >
        <button
          class="table-of-contents__link"
          :class="{
            'is-active': activeId === heading.id,
            'table-of-contents__link--sub': heading.level === 3,
          }"
          type="button"
          :aria-current="activeId === heading.id ? 'true' : undefined"
          :data-testid="`${props.test_id}-link-${heading.id}`"
          @click="navigate(heading.id)"
        >
          {{ heading.text }}
        </button>
      </li>
    </ul>
  </aside>

  <!-- The mobile variant: a floating button expanding into a panel
       with the same heading list — positioned LEFT of the GoTop button
       (user rework 2026-08-16). Teleported to <body> so `position:
       fixed` is viewport-relative (a11y 13.6 #19). -->
  <Teleport
    v-if="mounted"
    to="body"
  >
    <div
      ref="mobileEl"
      class="toc-mobile"
      :data-testid="`${props.test_id}-mobile`"
      @keydown.escape="closeMobile"
    >
      <PublicBaseButton
        variant="icon-only"
        icon="heroicons:list-bullet"
        class="toc-mobile__toggle"
        :aria-label="t('blog.table_of_contents')"
        :aria-expanded="isMobileOpen ? 'true' : 'false'"
        :aria-controls="`${props.test_id}-mobile-panel`"
        :test_id="`${props.test_id}-mobile-toggle`"
        @click="isMobileOpen = !isMobileOpen"
      />
      <div
        v-if="isMobileOpen"
        :id="`${props.test_id}-mobile-panel`"
        class="toc-mobile__panel"
        data-state="mobile"
      >
        <h2 class="table-of-contents__title">
          {{ t('blog.table_of_contents') }}
        </h2>
        <p
          v-if="props.headings.length === 0"
          class="table-of-contents__empty"
          :data-testid="`${props.test_id}-mobile-empty`"
        >
          {{ t('blog.toc_empty') }}
        </p>
        <ul
          v-else
          class="table-of-contents__list"
        >
          <li
            v-for="heading in props.headings"
            :key="`mobile-${heading.id}`"
          >
            <button
              class="table-of-contents__link"
              :class="{
                'is-active': activeId === heading.id,
                'table-of-contents__link--sub': heading.level === 3,
              }"
              type="button"
              :data-testid="`${props.test_id}-mobile-link-${heading.id}`"
              @click="navigate(heading.id)"
            >
              {{ heading.text }}
            </button>
          </li>
        </ul>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.table-of-contents {
    position: sticky;
    top: calc(var(--header-height) + var(--space-xl));
    padding: var(--space-lg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
}

.table-of-contents__title {
    font-size: var(--font-size-xs);
    font-weight: var(--font-semibold);
    text-transform: uppercase;
    letter-spacing: var(--letter-spacing-wide);
    color: var(--color-text-secondary);
    margin-bottom: var(--space-md);
}

.table-of-contents__list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
    margin: 0;
    padding: 0;
}

.table-of-contents__link {
    display: block;
    width: 100%;
    text-align: left;
    padding: var(--space-xs) var(--space-sm);
    border: none;
    border-radius: var(--radius-md);
    background: transparent;
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.table-of-contents__link:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.table-of-contents__link--sub {
    padding-left: var(--space-lg);
}

/* The headingless-article state (user rework 2026-08-16): the muted
   caption replaces the list — the sidebar itself is never hidden. */
.table-of-contents__empty {
    margin: 0;
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

/* The active heading (IntersectionObserver). */
.table-of-contents__link.is-active {
    background: var(--color-accent-muted);
    color: var(--color-accent);
    font-weight: var(--font-semibold);
}

/* The mobile variant: a floating button + panel in the bottom-right
   corner; the desktop sidebar is hidden below `--bp-lg` (1024px) and
   this block is hidden above it (the mockup's swap). */
.toc-mobile {
    display: none;
}

@media (width <= 1024px) {
    .table-of-contents {
        display: none;
    }

    .toc-mobile {
        display: block;
        position: fixed;

        /* User rework 2026-08-16: left-aligned with the same 1rem
           corner gap as the (repositioned) GoTop button, same 40px
           height, slightly narrower than GoTop. */
        left: 1rem;
        bottom: 1rem;
        z-index: var(--z-sticky);
    }

    /* The icon-only base button is 44px tall — matched to GoTop's
       40px with a slimmer 36px width; the icon carries no margin in
       the icon-only variant. */
    .toc-mobile__toggle {
        height: 40px;
        width: 36px;
        padding: 0;
        border-radius: var(--radius-md);
        background: var(--color-accent);
        color: #fff;
    }

    .toc-mobile__panel {
        position: absolute;
        left: 0;
        bottom: calc(100% + var(--space-sm));
        width: 280px;
        max-height: 60vh;
        overflow-y: auto;
        padding: var(--space-lg);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        box-shadow: var(--shadow-lg);
    }
}
</style>
