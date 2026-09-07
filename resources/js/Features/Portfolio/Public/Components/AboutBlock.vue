<!-- AboutBlock (public-section-components.md:929-942) — the landing hero: photo, name/title/short pitch, availability badge, location/timezone/work-type meta and the CTA row. `about` is null only when the server sent no about row — the contract renders the skeleton then (circle + text lines). The GitHub CTA fires cta_click_github and the CV download fires cta_click_cv (analytics.md:34-37). -->
<script setup lang="ts">
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicBaseBadge from '@shared-public/PublicBaseBadge.vue';
import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicCenteredContainer from '@shared-public/PublicCenteredContainer.vue';
import type { AboutBlockData, CtaLink } from '../Composables/useLandingPage';
import { usePublicIconsReady } from '../iconRegistration';

const props = withDefaults(
    defineProps<{
        about?: AboutBlockData | null;
        cta_links?: CtaLink[];
        test_id?: string;
    }>(),
    {
        about: null,
        cta_links: () => [],
        test_id: 'landing-about-block',
    },
);

const { t } = useI18n();
const analytics = useAnalytics();
// The CTA icons (e.g. the simple-icons GitHub mark) must not render
// before the landing's lazy pack registration resolved — the Iconify
// runtime would otherwise fall back to its remote API (blocked by CSP
// `connect-src 'self'`, B78 self-hosted stance).
const publicIconsReady = usePublicIconsReady();

function ctaKey(url: string): string {
    const normalized = url.toLowerCase();
    if (normalized.includes('github')) {
        return 'github';
    }

    if (normalized.endsWith('/cv')) {
        return 'cv';
    }

    return normalized.replace(/^\//, '').replace(/[^a-z0-9]+/g, '-') || 'link';
}

function handleCta(link: CtaLink): void {
    if (ctaKey(link.url) === 'github') {
        analytics.track('cta_click_github');
    } else if (ctaKey(link.url) === 'cv') {
        analytics.track('cta_click_cv');
    } else if (ctaKey(link.url) === 'projects') {
        analytics.track('cta_click_projects');
    }
}
</script>

<template>
  <section
    class="about-block gradient-hero"
    :data-testid="props.test_id"
  >
    <div
      class="gradient-hero__yellow-blue"
      aria-hidden="true"
    />
    <div
      class="gradient-hero__red"
      aria-hidden="true"
    />
    <PublicCenteredContainer size="content">
      <div
        v-if="props.about === null"
        class="about-block__skeleton"
        :data-testid="`${props.test_id}-skeleton`"
      >
        <PublicBaseSkeleton
          shape="circle"
          width="120px"
          height="120px"
        />
        <div class="about-block__skeleton-text">
          <PublicBaseSkeleton
            shape="text"
            :repeat="3"
          />
        </div>
      </div>

      <div
        v-else
        class="about-block__grid"
      >
        <div class="about-block__photo-col">
          <img
            v-if="props.about.photo"
            class="about-block__photo"
            :src="props.about.photo"
            :alt="props.about.photo_alt"
            width="480"
            height="480"
            fetchpriority="high"
            :data-testid="`${props.test_id}-photo`"
          >
          <div
            v-else
            class="about-block__photo about-block__photo--placeholder"
            aria-hidden="true"
            :data-testid="`${props.test_id}-photo-placeholder`"
          />

          <div class="about-block__meta-col">
            <PublicBaseBadge
              :variant="props.about.availability_status.variant"
              icon="heroicons:check"
              :test_id="`${props.test_id}-availability`"
            >
              {{ props.about.availability_status.label }}
            </PublicBaseBadge>
            <span
              v-if="props.about.work_type"
              class="about-block__meta-item"
              :data-testid="`${props.test_id}-work-type`"
            >
              {{ props.about.work_type }}<template v-if="props.about.relocation_readiness"> &middot; {{ t('landing.open_to_relocation') }}</template>
            </span>
            <span
              v-if="props.about.show_location && props.about.location"
              class="about-block__meta-item"
              :data-testid="`${props.test_id}-location`"
            >
              {{ props.about.location }} &middot; {{ props.about.timezone }}
            </span>
          </div>
        </div>

        <div class="about-block__content">
          <h1 class="about-block__name">
            {{ props.about.name }}
          </h1>
          <p class="about-block__title">
            {{ props.about.title }}
          </p>
          <p class="about-block__pitch">
            {{ props.about.short_pitch }}
          </p>

          <div class="about-block__ctas">
            <PublicBaseButton
              v-for="link in props.cta_links"
              :key="link.url"
              tag="a"
              :href="link.url"
              :variant="link.variant"
              :icon="publicIconsReady ? link.icon : undefined"
              :icon_position="link.icon_position"
              :test_id="`${props.test_id}-cta-${ctaKey(link.url)}`"
              @click="handleCta(link)"
            >
              {{ link.label }}
            </PublicBaseButton>
          </div>
        </div>
      </div>
    </PublicCenteredContainer>
  </section>
</template>

<style scoped>
.about-block {
    position: relative;
    overflow: hidden;
    padding: var(--space-3xl) 0;
}

/* The ambient gradient blobs (z-index 0/1) must stay behind the content. */
.about-block__grid,
.about-block__skeleton {
    position: relative;
    z-index: 2;
}

.about-block__grid {
    display: grid;

    /* Content left, photo column right — the photo is FIRST in the DOM
       so the single-column (mobile) order starts with the image. */
    grid-template-columns: 1fr 240px;
    gap: var(--space-2xl);
    align-items: center;
}

.about-block__photo-col {
    grid-column: 2;
    grid-row: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-lg);
}

.about-block__content {
    grid-column: 1;
    grid-row: 1;
}

.about-block__photo {
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-xl);
    object-fit: cover;
    box-shadow: var(--shadow-lg);
    border: 3px solid var(--glass-border);
}

.about-block__photo--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-bg-tertiary);
}

/* Availability → work type → location, stacked one below another under
   the photo (user rework 2026-08-14). */
.about-block__meta-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-sm);
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    text-align: center;
}

.about-block__meta-item {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
}

.about-block__name {
    font-family: var(--font-display);
    font-size: 3.5rem;
    font-weight: var(--font-semibold);
    line-height: 1.1;
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin-bottom: var(--space-xs);
}

.about-block__title {
    font-size: var(--font-size-xl);
    font-weight: var(--font-bold);
    color: var(--color-accent-2);
    margin-bottom: var(--space-md);
}

.about-block__pitch {
    font-size: var(--font-size-lg);
    line-height: var(--line-height-relaxed);
    color: var(--color-text-secondary);
    max-width: 56ch;
    margin-bottom: var(--space-lg);
}

.about-block__ctas {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-md);
}

.about-block__skeleton {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: var(--space-2xl);
    align-items: center;
}

@media (width <= 768px) {
    .about-block__grid,
    .about-block__skeleton {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .about-block__photo-col,
    .about-block__content {
        grid-column: 1;
        grid-row: auto;
    }

    .about-block__photo {
        max-width: 200px;
        margin: 0 auto;
    }

    .about-block__ctas {
        justify-content: center;
    }

    .about-block__skeleton {
        justify-items: center;
    }

    .about-block__skeleton-text {
        width: 100%;
    }
}
</style>
