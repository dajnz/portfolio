<!-- AnalyticsRichValue — renders a block/table value: plain strings/numbers verbatim, `RichText` segment arrays as bold numbers + dull unit labels ("1 min. 05 sec." — the numbers carry the parent's emphasis, the labels are smaller and greyer). Plain interpolated text — never markup. -->
<script setup lang="ts">
import type { RichText } from '../Composables/useAnalytics';

const props = withDefaults(
    defineProps<{
        parts: number | string | RichText;
        test_id?: string;
    }>(),
    {
        test_id: 'analytics-rich-value',
    },
);
</script>

<template>
  <template v-if="typeof props.parts !== 'object'">
    {{ props.parts }}
  </template>
  <template v-else>
    <template
      v-for="(part, index) in props.parts"
      :key="index"
    >
      {{ index > 0 ? ' ' : '' }}<strong
        v-if="part.bold"
        class="font-semibold"
      >{{ part.text }}</strong><span
        v-else
        class="text-xs font-medium text-slate-400 dark:text-slate-500"
      >{{ part.text }}</span>
    </template>
  </template>
</template>
