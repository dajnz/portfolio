<!-- FeatureFlagToggle (admin-section-components.md:1295-1322, settings.html) — a feature-flag row: flag name + optional hint + enabled/disabled AdminBaseToggle (the small primary toggle from the style-guide, mockup wins per B79 — not the large maintenance AdminBaseSwitch). Emits `toggle` with `{key, is_enabled}` so the page can PATCH /admin/settings/feature-flags. -->
<script setup lang="ts">
import AdminBaseToggle from '@shared-admin/AdminBaseToggle.vue';

export interface FeatureFlagData {
    key: string;
    name: string;
    is_enabled: boolean;
}

const props = withDefaults(
    defineProps<{
        flag: FeatureFlagData;
        hint?: string;
        disabled?: boolean;
        test_id?: string;
    }>(),
    {
        hint: undefined,
        disabled: false,
        test_id: 'feature-flag-toggle',
    },
);

const emit = defineEmits<{ toggle: [payload: { key: string; is_enabled: boolean }] }>();

function onToggle(value: boolean): void {
    emit('toggle', { key: props.flag.key, is_enabled: value });
}
</script>

<template>
  <div
    class="flex items-center justify-between gap-4 py-3"
    :data-testid="props.test_id"
  >
    <div class="min-w-0">
      <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">
        {{ props.flag.name }}
      </span>
      <span
        v-if="props.hint"
        class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400"
      >
        {{ props.hint }}
      </span>
    </div>
    <AdminBaseToggle
      :model-value="props.flag.is_enabled"
      :aria_label="props.flag.name"
      :disabled="props.disabled"
      :test_id="`${props.test_id}-toggle`"
      @update:model-value="onToggle"
    />
  </div>
</template>
