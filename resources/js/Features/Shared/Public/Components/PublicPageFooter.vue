<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';

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

function copyrightText(): string {
    return t('landing.footer_copyright', {
        year: props.copyright_year ?? String(new Date().getFullYear()),
        name: props.copyright_name ?? '',
    });
}
</script>

<template>
  <footer
    class="footer"
    :data-testid="props.test_id"
  >
    <PublicCenteredContainer
      size="content"
      class="footer__inner"
    >
      <p class="footer__copyright">
        {{ copyrightText() }}
      </p>
      <slot />
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
</style>
