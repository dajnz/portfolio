<!-- SettingsTabs — the Settings page tab bar (General / Mail / Feature Flags / Analytics); emits the selected tab id. -->
<script setup lang="ts">
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

import type { SettingsTab, TabId } from './types';

const props = withDefaults(
    defineProps<{
        tabs: SettingsTab[];
        active_tab: TabId;
        test_id?: string;
    }>(),
    {
        test_id: 'settings-tabs',
    },
);

const emit = defineEmits<{ select: [tab: TabId] }>();

const { t } = useI18n();
</script>

<template>
  <div
    class="thin-scrollbar mb-6 flex overflow-x-auto border-b border-slate-200 dark:border-slate-700"
    :data-testid="`${props.test_id}-tabs`"
  >
    <AdminBaseButton
      v-for="tab in props.tabs"
      :key="tab.id"
      :variant="'ghost'"
      :type="'button'"
      :class="
        props.active_tab === tab.id
          ? '-mb-px shrink-0 whitespace-nowrap rounded-none border-b-2 border-primary-600 text-primary-600 dark:text-primary-400'
          : 'shrink-0 whitespace-nowrap rounded-none'
      "
      :test_id="`${props.test_id}-tab-${tab.id}`"
      @click="emit('select', tab.id)"
    >
      {{ t(tab.label_key) }}
    </AdminBaseButton>
  </div>
</template>
