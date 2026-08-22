<!-- AnalyticsChartTooltip — the multi-series hover tooltip (AnalyticsChart sub-component, user request 2026-08-22): a small SVG box near the point showing the series name + the point's value. Positioned above the point when the plot area allows, below otherwise, clamped to the viewBox sides. User data renders as text nodes only (the textContent rule). -->
<script setup lang="ts">
import { computed } from 'vue';

import {
    type HoveredMarker,
    PADDING_X,
    TOP_AREA,
    VIEWBOX_WIDTH,
} from '../Composables/useAnalyticsChart';

const TOOLTIP_HEIGHT = 32;
const TOOLTIP_CHAR_WIDTH = 6;
const TOOLTIP_OFFSET = 8;

const props = defineProps<{
    marker: HoveredMarker | null;
    test_id?: string;
}>();

// The tooltip box near the point — above it when the plot area has
// room, below otherwise — clamped to the viewBox sides.
const geometry = computed(() => {
    const marker = props.marker;
    if (marker === null) {
        return null;
    }

    const width =
        Math.max(marker.name.length, String(marker.value).length) * TOOLTIP_CHAR_WIDTH + 16;
    const x = Math.max(
        PADDING_X,
        Math.min(marker.x - width / 2, VIEWBOX_WIDTH - PADDING_X - width),
    );
    const above = marker.y - TOOLTIP_HEIGHT - TOOLTIP_OFFSET >= TOP_AREA;
    const y = above ? marker.y - TOOLTIP_HEIGHT - TOOLTIP_OFFSET : marker.y + TOOLTIP_OFFSET + 4;

    return { x, y, width };
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
      :height="TOOLTIP_HEIGHT"
      rx="4"
      class="fill-white stroke-slate-200 dark:fill-slate-800 dark:stroke-slate-600"
    />
    <text
      :x="geometry.width / 2"
      :y="15"
      text-anchor="middle"
      class="fill-slate-400 text-[10px] font-mono"
    >
      {{ props.marker?.name }}
    </text>
    <text
      :x="geometry.width / 2"
      :y="27"
      text-anchor="middle"
      class="fill-slate-600 text-[11px] font-semibold font-mono dark:fill-slate-200"
    >
      {{ props.marker?.value }}
    </text>
  </g>
</template>
