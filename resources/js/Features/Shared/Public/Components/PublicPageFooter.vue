<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { Comment, Fragment, Text, useSlots, type VNode } from 'vue';

import PublicCenteredContainer from './PublicCenteredContainer.vue';

const props = withDefaults(
    defineProps<{
        copyright_year?: string;
        copyright_name?: string;
        test_id?: string;
    }>(),
    {
        copyright_year: undefined,
        copyright_name: undefined,
        test_id: 'public-footer',
    },
);

const { t } = useI18n();
const slots = useSlots();

// The layout always forwards its `footer` slot (empty when the page
// provides nothing), so presence is checked by rendering the slot and
// descending through the outlet's placeholder fragments/comments.
function hasExtras(): boolean {
    const vnodes = slots.default?.() ?? [];

    return vnodes.some((vnode) => !isEmptyVNode(vnode));
}

function isEmptyVNode(vnode: VNode): boolean {
    if (vnode.type === Comment) {
        return true;
    }

    if (vnode.type === Text) {
        return ((vnode.children as string | undefined) ?? '').trim() === '';
    }

    if (vnode.type === Fragment) {
        const children = vnode.children as VNode[] | undefined;

        return !Array.isArray(children) || children.every(isEmptyVNode);
    }

    return false;
}

function copyrightText(): string {
    const year = props.copyright_year ?? String(new Date().getFullYear());
    const name = props.copyright_name ?? '';
    const ownerYear = name !== '' ? `${year} ${name}` : year;

    return t('landing.footer_copyright', { owner_year: ownerYear });
}
</script>

<template>
  <footer
    class="footer"
    :class="{ 'footer--has-extras': hasExtras() }"
    :data-testid="props.test_id"
  >
    <PublicCenteredContainer
      size="content"
      class="footer__inner"
    >
      <p class="footer__copyright">
        {{ copyrightText() }}
      </p>
      <div
        v-if="hasExtras()"
        class="footer__extras"
      >
        <slot />
      </div>
    </PublicCenteredContainer>
  </footer>
</template>

<style scoped>
.footer {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-lg) var(--space-xl);
    border-top: 1px solid var(--color-border);
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
    background-color: var(--color-bg-footer);
}

/*
 * When the extras row is present the block padding shrinks (12px per
 * side vs the 24px of the copyright-only state) so the footer keeps the
 * same overall height: the ~24px given back are consumed by the extra
 * row (xs font) plus the sm gap between the rows.
 */
.footer--has-extras {
    padding-block: calc(var(--space-md) - var(--space-xs));
}

.footer__inner {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: var(--space-sm);
}

.footer__copyright {
    text-align: center;
    margin: 0;
}

.footer__extras {
    font-size: var(--font-size-xs);
    text-align: center;
}
</style>

<style>
/*
 * The footer-slot links come from page scopes (each blog page passes its
 * own RSS link), so scoped styles cannot reach them — stylelint rejects
 * `:slotted`/`:deep`, hence this plain block (the projects pages
 * precedent).
 */
.footer__link {
    color: var(--color-text-tertiary);
    text-decoration: underline;
    text-underline-offset: 3px;
}

.footer__link:hover {
    color: var(--color-accent);
}
</style>
