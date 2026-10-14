<script setup lang="ts">
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
</script>

<template>
  <footer
    class="border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark"
    :data-testid="props.test_id"
  >
    <AdminCenteredContainer size="xl">
      <div class="flex items-center justify-center gap-2 h-14">
        <p class="text-sm text-slate-500 dark:text-slate-400">
          {{ copyrightText }}
        </p>
        <slot />
      </div>
    </AdminCenteredContainer>
  </footer>
</template>
