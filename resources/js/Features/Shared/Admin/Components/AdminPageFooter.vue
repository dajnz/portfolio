<script setup lang="ts">
import { useAppVersion } from '@shared-cross/Composables/useAppVersion';
import { useCopyrightNotice } from '@shared-cross/Composables/useCopyrightNotice';

import AdminCenteredContainer from './AdminCenteredContainer.vue';

const props = withDefaults(
    defineProps<{
        test_id?: string;
    }>(),
    {
        test_id: 'admin-footer',
    },
);

const copyrightText = useCopyrightNotice();
const versionText = useAppVersion();
</script>

<template>
  <footer
    class="border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark"
    :data-testid="props.test_id"
  >
    <div class="relative flex flex-col justify-center gap-1 py-3 lg:h-14 lg:py-0">
      <span
        v-if="versionText !== ''"
        class="text-center text-sm text-slate-500 dark:text-slate-400 lg:absolute lg:top-1/2 lg:left-0 lg:-translate-y-1/2 lg:pl-8 lg:text-left"
        data-testid="admin-footer-version"
      >
        {{ versionText }}
      </span>
      <AdminCenteredContainer size="xl">
        <div class="flex items-center justify-center gap-2">
          <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ copyrightText }}
          </p>
          <slot />
        </div>
      </AdminCenteredContainer>
    </div>
  </footer>
</template>
