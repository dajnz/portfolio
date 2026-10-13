<!-- AnalyticsChartTooltip — the point hover tooltip: a small SVG box near the point showing the point's context (the series name for the multi-series chart, the date for the single-series charts) and value, plus the date when it is not already the context. Positioned above the point when the plot area allows, below otherwise, clamped to the viewBox sides. User data renders as text nodes only (the textContent rule). -->
<script setup lang="ts">
import { computed } from 'vue';

import { type HoveredMarker, PADDING_X, TOP_AREA } from '../Composables/useAnalyticsChart';

const TOOLTIP_CHAR_WIDTH = 6;
const TOOLTIP_OFFSET = 8;
const LINE_HEIGHT = 12;
const TOOLTIP_PADDING = 16;

const props = defineProps<{
    marker: HoveredMarker | null;
    viewbox_width: number;
    test_id?: string;
}>();

const geometry = computed(() => {
    const marker = props.marker;
    if (marker === null) {
        return null;
    }

    const nameLength = marker.name.length;
    const labelLength = marker.label?.length ?? 0;
    const valueLength = String(marker.value).length;
    const lines = marker.label === undefined ? 2 : 3;

    const width =
        Math.max(nameLength, labelLength, valueLength) * TOOLTIP_CHAR_WIDTH + TOOLTIP_PADDING;
    const height = lines * LINE_HEIGHT + TOOLTIP_PADDING;
    const x = Math.max(
        PADDING_X,
        Math.min(marker.x - width / 2, props.viewbox_width - PADDING_X - width),
    );
    const above = marker.y - height - TOOLTIP_OFFSET >= TOP_AREA;
    const y = above ? marker.y - height - TOOLTIP_OFFSET : marker.y + TOOLTIP_OFFSET + 4;

    return { x, y, width, height };
});
</script>

<template>
  <g
    v-if="geometry !== null"
    :transform="`translate(${geometry.x}, ${geometry.y})`"
    pointer-events="none"
    :data-testid="`${props.test_id}-tooltip`"
  >
    <rect
      :width="geometry.width"
      :height="geometry.height"
      rx="4"
      class="fill-white stroke-slate-200 dark:fill-slate-800 dark:stroke-slate-600"
    />
    <text
      :x="geometry.width / 2"
      :y="19"
      text-anchor="middle"
      class="fill-slate-400 text-[10px] font-mono"
    >
      {{ props.marker?.name }}
    </text>
    <text
      v-if="props.marker?.label !== undefined"
      :x="geometry.width / 2"
      :y="31"
      text-anchor="middle"
      class="fill-slate-400 text-[10px] font-mono"
    >
      {{ props.marker?.label }}
    </text>
    <text
      :x="geometry.width / 2"
      :y="props.marker?.label === undefined ? 31 : 43"
      text-anchor="middle"
      class="fill-slate-600 text-[11px] font-semibold font-mono dark:fill-slate-200"
    >
      {{ props.marker?.value }}
    </text>
  </g>
</template>
