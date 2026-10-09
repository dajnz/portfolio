<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TechSkillTag from '@shared-cross/Components/TechSkillTag.vue';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { usePageTracking } from '@shared-cross/Composables/usePageTracking';
import { useSeoUpdater } from '@shared-cross/Composables/useSeoUpdater';
import type { components } from '@shared-cross/Types/inertia';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import { computed } from 'vue';
import EngineeringDetailItem from '../Components/EngineeringDetailItem.vue';
import ScreenshotsGallery from '../Components/ScreenshotsGallery.vue';
import { useProjectDetailPage } from '../Composables/useProjectDetailPage';
import { ensureProjectsIcons, useProjectsIconsReady } from '../iconRegistration';

type DetailProps = NonNullable<components['schemas']['ProjectDetailPageProps']['props']>;

// Per-property types (not the whole `DetailProps` alias): the SFC
// compiler's runtime prop resolution cannot expand the indexed-access
// `NonNullable<...['props']>` type.
const props = withDefaults(
    defineProps<{
        project?: DetailProps['project'];
        seo?: DetailProps['seo'];
        test_id?: string;
    }>(),
    {
        project: undefined,
        seo: undefined,
        test_id: 'project-detail-page',
    },
);

const { t } = useI18n();
const analytics = useAnalytics();

ensureProjectsIcons();
usePageTracking('project_detail', props.project?.id ?? null);
useSeoUpdater();

const { navItems, activeRoute, project } = useProjectDetailPage(props);

// The Sources GitHub glyph comes from the lazily-registered simple-icons
// subset — rendering before registration would trigger the Iconify
// runtime's remote fallback (blocked by CSP).
const projectsIconsReady = useProjectsIconsReady();

// The bottom block's two-column condition — extracted so the class
// binding stays a single line (biome lineWidth 100 vs vue/html-indent).
// The section v-ifs keep the direct prop expressions so vue-tsc can
// narrow `project.tech_writeup` inside the writeup subtree.
const hasExternalLinks = computed(
    () => Boolean(props.project?.github_url) || Boolean(props.project?.live_demo_url),
);
const hasTwoColBottom = computed(
    () =>
        props.project?.tech_writeup !== null &&
        props.project?.tech_writeup !== undefined &&
        hasExternalLinks.value,
);

function onSourcesClick(): void {
    analytics.track('cta_click_github');
}

function onDemoClick(): void {
    analytics.track('cta_click_demo');
}
</script>

<template>
  <PublicPageLayout
    :nav_items="navItems"
    :active_route="activeRoute"
    :test_id="props.test_id"
  >
    <article
      v-if="project !== null"
      class="project-detail"
      :data-testid="props.test_id"
    >
      <div class="project-detail__panel">
        <h1
          class="project-detail__title"
          :data-testid="`${props.test_id}-title`"
        >
          {{ project.title }}
        </h1>

        <ScreenshotsGallery
          :screenshots="project.screenshots"
          :test_id="`${props.test_id}-gallery`"
        />

        <!-- The bodies are the shared content pipeline's safe-mode
             CommonMark HTML — server-sanitized; the eslint
             vue/no-v-html warnings are accepted by design (the
             TOTPSetup precedent). A `div` (not a `p`): the pipeline
             emits block-level elements (headings, lists) that must not
             nest inside a paragraph. -->
        <!-- eslint-disable vue/no-v-html -->
        <section
          v-if="project.full_description"
          class="project-detail__section"
        >
          <div
            class="markdown-body"
            :data-testid="`${props.test_id}-overview`"
            v-html="project.full_description"
          />
        </section>

        <section
          v-if="project.problem"
          class="project-detail__section"
        >
          <h2 class="project-detail__section-title">
            {{ t('projects.problem') }}
          </h2>
          <div
            class="markdown-body"
            :data-testid="`${props.test_id}-problem`"
            v-html="project.problem"
          />
        </section>

        <section
          v-if="project.solution"
          class="project-detail__section"
        >
          <h2 class="project-detail__section-title">
            {{ t('projects.solution') }}
          </h2>
          <div
            class="markdown-body"
            :data-testid="`${props.test_id}-solution`"
            v-html="project.solution"
          />
        </section>
        <!-- eslint-enable vue/no-v-html -->

        <section
          v-if="project.tech_tags.length > 0"
          class="project-detail__section"
        >
          <h2 class="project-detail__section-title">
            {{ t('projects.tech_stack') }}
          </h2>
          <div
            class="project-detail__tags"
            :data-testid="`${props.test_id}-tags`"
          >
            <TechSkillTag
              v-for="tag in project.tech_tags"
              :key="tag.id"
              :skill="tag"
              linked
            />
          </div>
        </section>

        <section
          v-if="project.detail_items.length > 0"
          class="project-detail__section"
        >
          <h2 class="project-detail__section-title">
            {{ t('projects.engineering_details') }}
          </h2>
          <ul
            class="detail-grid"
            :data-testid="`${props.test_id}-details`"
          >
            <EngineeringDetailItem
              v-for="item in project.detail_items"
              :key="item.id"
              :detail="item"
              :test_id="`${props.test_id}-detail-${item.id}`"
            />
          </ul>
        </section>

        <!-- The bottom two-column block: the tech writeup card in the
             first column, the labeled Sources/Live Demo row in the
             second; each side becomes a single column when the other is
             absent and the block hides entirely when neither exists. -->
        <div
          v-if="project.tech_writeup !== null || project.github_url || project.live_demo_url"
          class="project-detail__bottom"
          :class="{ 'project-detail__bottom--two-col': hasTwoColBottom }"
          :data-testid="`${props.test_id}-bottom`"
        >
          <section
            v-if="project.tech_writeup !== null"
            class="project-detail__writeup"
            :data-testid="`${props.test_id}-writeup`"
          >
            <h2 class="project-detail__section-title">
              {{ t('projects.tech_writeup') }}
            </h2>
            <Link
              class="card card--hoverable project-detail__writeup-card"
              :href="`/blog/${project.tech_writeup.slug}`"
            >
              <div class="card__content">
                <h3 class="card__title">
                  {{ project.tech_writeup.title }}
                </h3>
                <p class="card__meta">
                  {{ t('projects.read_the_writeup') }}
                </p>
              </div>
            </Link>
          </section>

          <section
            v-if="hasExternalLinks"
            class="project-detail__links"
            :data-testid="`${props.test_id}-links`"
          >
            <h2 class="project-detail__section-title">
              {{ t('projects.links') }}
            </h2>
            <div class="project-detail__links-row">
              <PublicBaseButton
                v-if="project.github_url"
                tag="a"
                :href="project.github_url"
                target="_blank"
                rel="noopener"
                variant="secondary"
                :icon="projectsIconsReady ? 'simple-icons:github' : undefined"
                :test_id="`${props.test_id}-github`"
                @click="onSourcesClick"
              >
                {{ t('projects.sources') }}
              </PublicBaseButton>
              <PublicBaseButton
                v-if="project.live_demo_url"
                tag="a"
                :href="project.live_demo_url"
                target="_blank"
                rel="noopener"
                variant="secondary"
                icon="heroicons:arrow-top-right-on-square"
                :test_id="`${props.test_id}-demo`"
                @click="onDemoClick"
              >
                {{ t('projects.live_demo') }}
              </PublicBaseButton>
            </div>
          </section>
        </div>
      </div>
    </article>
  </PublicPageLayout>
</template>

<style scoped>
/* The content column spans `--layout-content` (1000px — the
   header/footer width), centered inside the layout's xl main container.
   The top margin sets the gap between the sticky header and the panel;
   the bottom margin keeps a proper gap between the content panel's
   bottom edge and the footer. */
.project-detail {
    max-width: var(--layout-content);
    margin: var(--space-xl) auto var(--space-2xl);
}

/* The content column is a card-like panel on the page background. */
.project-detail__panel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
}

.project-detail__title {
    font-family: var(--font-display);
    font-size: var(--font-size-4xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-lg);
}

.project-detail__section {
    margin-bottom: var(--space-2xl);
}

.project-detail__section-title {
    font-family: var(--font-display);
    font-size: var(--font-size-2xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-md);
}

/* Rendered Markdown (v-html) — server-side CommonMark pipeline output.
   The root `.markdown-body` box styles (font, margins) are scoped (the
   root element carries the scope attribute), but the INNER element
   styles (headings, lists, links, ...) must live in the plain block
   below: v-html content never receives the Vue scope attribute, so
   scoped selectors like `.markdown-body h2[data-v-…]` can never match
   them. The `*` CSS reset
   (public-tokens.css) strips list markers, margins, heading sizes and
   link styles, so every element the pipeline can emit is re-styled
   here. */
.markdown-body {
    font-size: var(--font-size-base);
    line-height: var(--line-height-relaxed);
    color: var(--color-text-secondary);
}

.markdown-body > * + * {
    margin-top: var(--space-md);
}

.project-detail__tags {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-xs);
}

/* The bottom block: the tech writeup card and the labeled links row.
   Two columns when both sides exist, a
   single column otherwise; the block is hidden entirely when neither
   side has content (the template's v-if). */
.project-detail__bottom {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-xl);
    margin-top: var(--space-2xl);
    padding-top: var(--space-xl);
    border-top: 1px solid var(--color-border);
}

.project-detail__bottom--two-col {
    grid-template-columns: 1fr 1fr;
}

/* Small screens: the writeup card and the
   links row stack one below another, each taking the full width. */
@media (width < 768px) {
    .project-detail__bottom--two-col {
        grid-template-columns: 1fr;
    }
}

.project-detail__links-row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-md);
}

.detail-grid {
    list-style: none;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-lg);
    padding: 0;
    margin: 0;
}

.project-detail__writeup-card {
    display: block;
    color: inherit;
    text-decoration: none;
}

.card__meta {
    font-size: var(--font-size-sm);
    color: var(--color-accent);
    margin: 0;
}
</style>

<!-- The markdown element styles are intentionally global (non-scoped):
     the pipeline HTML is injected via v-html and its elements never
     carry the component's scope attribute, so scoped rules can never
     match them (stylelint forbids `:deep`; the projects-grid--list
     precedent). The class is unique to this page. -->
<style>
.markdown-body h2,
.markdown-body h3,
.markdown-body h4 {
    font-family: var(--font-display);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
}

.markdown-body h2 {
    font-size: var(--font-size-2xl);
    margin-top: var(--space-xl);
}

.markdown-body h3 {
    font-size: var(--font-size-xl);
    margin-top: var(--space-lg);
}

.markdown-body h4 {
    font-size: var(--font-size-lg);
    margin-top: var(--space-lg);
}

.markdown-body h2 + *,
.markdown-body h3 + *,
.markdown-body h4 + * {
    margin-top: var(--space-sm);
}

.markdown-body ul,
.markdown-body ol {
    padding-left: var(--space-lg);
}

.markdown-body ul {
    list-style: disc;
}

.markdown-body ol {
    list-style: decimal;
}

.markdown-body li {
    margin-bottom: var(--space-xs);
}

.markdown-body li:last-child {
    margin-bottom: 0;
}

.markdown-body li > ul,
.markdown-body li > ol {
    margin-top: var(--space-xs);
}

.markdown-body a {
    color: var(--color-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color var(--transition-fast);
}

.markdown-body a:hover {
    color: var(--color-accent-hover);
}

.markdown-body blockquote {
    margin: var(--space-md) 0;
    padding: var(--space-xs) 0 var(--space-xs) var(--space-md);
    border-left: 3px solid var(--color-accent);
    color: var(--color-text-tertiary);
}

.markdown-body blockquote > * + * {
    margin-top: var(--space-xs);
}

.markdown-body strong {
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
}

.markdown-body em {
    font-style: italic;
}

.markdown-body hr {
    border: none;
    border-top: 1px solid var(--color-border);
    margin: var(--space-lg) 0;
}

.markdown-body code {
    font-family: var(--font-mono);
    font-size: 0.9em;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-sm);
    padding: 0.1em 0.4em;
}

.markdown-body pre {
    background: var(--color-bg-secondary);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    overflow-x: auto;
}

.markdown-body pre code {
    background: none;
    padding: 0;
    font-size: var(--font-size-sm);
}

.markdown-body img {
    max-width: 100%;
    border-radius: var(--radius-md);
}

.markdown-body table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--font-size-sm);
}

.markdown-body th,
.markdown-body td {
    border: 1px solid var(--color-border);
    padding: var(--space-xs) var(--space-sm);
    text-align: left;
}

.markdown-body th {
    background: var(--color-bg-secondary);
    font-weight: var(--font-semibold);
    color: var(--color-text-primary);
}
</style>
