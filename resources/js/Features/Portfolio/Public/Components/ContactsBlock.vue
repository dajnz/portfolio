<!-- ContactsBlock (public-section-components.md:994-1007, user rework 2026-08-14) — the contact links column (single column, left) next to the FeedbackForm; the pair is centered inside the content container. Each contact row carries a small copy-to-clipboard button (copies the contact url, or the value when url is null). On small screens the columns stack with contacts above the form. The block hides when no contact links exist. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

import PublicBaseButton from '@shared-public/PublicBaseButton.vue';
import PublicBaseSkeleton from '@shared-public/PublicBaseSkeleton.vue';
import PublicCenteredContainer from '@shared-public/PublicCenteredContainer.vue';
import { onBeforeUnmount, ref } from 'vue';
import type { ContactLinkView, FeedbackFormProps } from '../Composables/useLandingPage';
import { usePublicIconsReady } from '../iconRegistration';
import FeedbackForm from './FeedbackForm.vue';

const props = withDefaults(
    defineProps<{
        contact_links?: ContactLinkView[];
        feedback_form_props: FeedbackFormProps;
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        contact_links: () => [],
        is_loading: false,
        test_id: 'landing-contacts-block',
    },
);

const { t } = useI18n();
const iconsReady = usePublicIconsReady();

const skeletonLinks = [0, 1, 2];

const COPY_FEEDBACK_MS = 1500;
const copiedId = ref<number | null>(null);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

async function copyContact(link: ContactLinkView): Promise<void> {
    const text = link.url ?? link.value;

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // Clipboard unavailable (non-secure context / denied) — the copy
        // feedback still flashes; nothing else we can do here.
    }

    copiedId.value = link.id;
    if (copyTimer !== null) {
        clearTimeout(copyTimer);
    }

    copyTimer = setTimeout(() => {
        copiedId.value = null;
    }, COPY_FEEDBACK_MS);
}

onBeforeUnmount(() => {
    if (copyTimer !== null) {
        clearTimeout(copyTimer);
    }
});
</script>

<template>
  <section
    v-if="props.contact_links.length > 0 || props.is_loading"
    id="contacts"
    class="contacts-block"
    :data-testid="props.test_id"
  >
    <PublicCenteredContainer size="content">
      <h2 class="section-title">
        {{ t('landing.get_in_touch') }}
      </h2>

      <div
        v-if="props.is_loading"
        class="contacts-block__grid"
        :data-testid="`${props.test_id}-skeleton`"
      >
        <div class="contacts-block__links">
          <div
            v-for="link in skeletonLinks"
            :key="link"
            class="contacts-block__skeleton-link"
          >
            <PublicBaseSkeleton shape="pill" />
          </div>
        </div>
      </div>

      <div
        v-else
        class="contacts-block__grid"
      >
        <div class="contacts-block__links">
          <div
            v-for="link in props.contact_links"
            :key="link.id"
            class="contact-row"
            :class="{ 'contact-row--plain': !link.url }"
            :data-testid="`${props.test_id}-row-${link.id}`"
          >
            <component
              :is="link.url ? 'a' : 'span'"
              :href="link.url ?? undefined"
              class="contact-link"
              :data-testid="`${props.test_id}-link-${link.id}`"
            >
              <Icon
                v-if="iconsReady && link.icon"
                class="contact-link__icon"
                :icon="link.icon"
                aria-hidden="true"
              />
              <span class="contact-link__name">{{ link.name }}</span>
              <span
                v-if="link.value"
                class="contact-link__value"
              >
                {{ link.value }}
              </span>
            </component>
            <PublicBaseButton
              variant="icon-only"
              class="contact-row__copy"
              :icon="copiedId === link.id ? 'heroicons:check' : 'heroicons:clipboard'"
              :title="t('landing.copy_contact')"
              :aria-label="t('landing.copy_contact')"
              :test_id="`${props.test_id}-copy-${link.id}`"
              @click="copyContact(link)"
            />
          </div>
        </div>

        <div class="contacts-block__form">
          <FeedbackForm v-bind="props.feedback_form_props" />
        </div>
      </div>
    </PublicCenteredContainer>
  </section>
</template>

<style scoped>
.contacts-block {
    background: var(--color-bg-secondary);

    /* Top padding halved (user rework 2026-08-14) — the section
   heading no longer floats 4rem below the previous block. */
    padding: var(--space-xl) 0 var(--space-3xl);
}

.section-title {
    font-family: var(--font-display);
    font-size: var(--font-size-2xl);
    font-weight: var(--font-semibold);
    letter-spacing: var(--letter-spacing-tight);
    color: var(--color-text-primary);
    margin: 0 0 var(--space-md);
}

/* Two-column layout: contact links (single column) on the left, the
   feedback form on the right (public-section-ui.md:83-95). Fixed-ish
   columns + justify-content: center keep the pair centered inside the
   content container instead of hugging its left edge. */
.contacts-block__grid {
    display: grid;

    /* The contact column is ~40px narrower than the cards' natural width
       (user request 2026-08-15). */
    grid-template-columns: minmax(300px, 380px) minmax(0, 400px);
    gap: var(--space-2xl);
    align-items: start;
    justify-content: center;
}

.contacts-block__links {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

/* The card look lives on the ROW now — the copy button sits INSIDE the
   card, next to the link area (user rework 2026-08-14). */
.contact-row {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-md) var(--space-lg);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--card-shadow);
    transition: var(--card-transition);
}

.contact-row:hover {
    transform: translateY(-2px);
    box-shadow: var(--card-shadow-hover);
    border-color: var(--color-accent);
}

.contact-row--plain:hover {
    transform: none;
    box-shadow: var(--card-shadow);
    border-color: var(--color-border);
}

/* The copy button is intentionally quiet — tertiary icon, no surface —
   and gains presence on hover (user request 2026-08-15). */
.contact-row__copy {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    color: var(--color-text-tertiary);
}

.contact-row__copy:hover {
    color: var(--color-accent);
    background: var(--color-bg-secondary);
}

.contact-link {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    font-weight: var(--font-medium);
    color: var(--color-text-primary);
    text-decoration: none;
}

/* Icon <-> name gap doubled (2 x the old --space-sm), name <-> value gap
   enlarged too (user rework 2026-08-14). */
.contact-link__icon {
    width: 1.65em;
    height: 1.65em;
    flex-shrink: 0;
    color: var(--color-accent);

    /* The simple-icons glyphs fill their 24x24 viewBox edge-to-edge — at
       chip size the flush right edge reads as cut off; 0.9 gives the
       glyph a small margin on every side. */
    transform: scale(0.9);
    margin-right: 1rem;
}

.contact-link__name {
    margin-right: 0.75rem;
    white-space: nowrap;
}

.contact-link__value {
    color: var(--color-text-tertiary);
    font-weight: var(--font-normal);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.contacts-block__form {
    display: block;
}

.contacts-block__skeleton-link {
    height: 46px;
}

@media (width <= 768px) {
    .contacts-block__grid {
        grid-template-columns: 1fr;
    }
}
</style>
