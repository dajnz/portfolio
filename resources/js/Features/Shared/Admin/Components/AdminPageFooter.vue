<!-- AdminPageFooter (admin-section-components.md:312-322) — bottom bar, content always block-centred. -->
<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';

import AdminCenteredContainer from './AdminCenteredContainer.vue';

const props = withDefaults(
    defineProps<{
        copyright_year?: string;
        copyright_name?: string;
        test_id?: string;
    }>(),
    {
        copyright_year: undefined,
        copyright_name: undefined,
        test_id: 'admin-footer',
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
    class="border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark"
    :data-testid="props.test_id"
  >
    <AdminCenteredContainer size="xl">
      <div class="flex items-center justify-center gap-2 h-14">
        <p class="text-sm text-slate-400 dark:text-slate-500">
          {{ copyrightText() }}
        </p>
        <slot />
      </div>
    </AdminCenteredContainer>
  </footer>
</template>
