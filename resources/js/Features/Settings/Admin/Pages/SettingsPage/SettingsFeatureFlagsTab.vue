<!-- SettingsFeatureFlagsTab — the Settings page Feature Flags tab: one FeatureFlagToggle per flag (separate PATCH endpoint, no Save). -->
<script setup lang="ts">
import FeatureFlagToggle, { type FeatureFlagData } from '@settings-admin/FeatureFlagToggle.vue';

const props = withDefaults(
    defineProps<{
        rows: FeatureFlagData[];
        hints: Record<string, string>;
        test_id?: string;
    }>(),
    {
        test_id: 'settings-feature-flags-tab',
    },
);

const emit = defineEmits<{ toggle: [payload: { key: string; is_enabled: boolean }] }>();
</script>

<template>
  <div
    class="space-y-2 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="`${props.test_id}-form-flags`"
  >
    <FeatureFlagToggle
      v-for="row in props.rows"
      :key="row.key"
      :flag="row"
      :hint="props.hints[row.key]"
      :test_id="`${props.test_id}-flag-${row.key}`"
      @toggle="(payload) => emit('toggle', payload)"
    />
  </div>
</template>
