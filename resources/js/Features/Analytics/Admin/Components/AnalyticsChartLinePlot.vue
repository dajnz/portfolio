<!-- AnalyticsChartLinePlot — the single-series line marks of AnalyticsChart: the polyline with a dot per point + its x label, and a full-column hover hit area that opens the value tooltip. Labels render as text nodes (the textContent rule); dense windows thin the x labels to the `label_indices`. -->
<script setup lang="ts">
import { ref, watch } from 'vue';

import {
    type HoveredMarker,
    PLOT_HEIGHT,
    type PointGeometry,
    TOP_AREA,
    VIEWBOX_HEIGHT,
} from '../Composables/useAnalyticsChart';
import AnalyticsChartTooltip from './AnalyticsChartTooltip.vue';

const props = defineProps<{
    line_points: string;
    geometries: Array<PointGeometry>;
    slot_width: number;
    label_indices: Array<number>;
    test_id?: string;
}>();

const hovered = ref<HoveredMarker | null>(null);

function showTooltip(geometry: PointGeometry): void {
    hovered.value = {
        x: geometry.x,
        y: geometry.y,
        value: geometry.point.value,
        name: geometry.point.label,
    };
}

function hideTooltip(): void {
    hovered.value = null;
}

// A data refresh can remove the hovered point — drop the stale tooltip.
watch(
    () => props.geometries,
    () => {
        hovered.value = null;
    },
);
</script>

<template>
  <polyline
    :points="line_points"
    fill="none"
    stroke="#5C87EF"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
  />
  <g
    v-for="geometry in geometries"
    :key="geometry.index"
  >
    <circle
      :cx="geometry.x"
      :cy="geometry.y"
      r="3"
      fill="#5C87EF"
    />
    <!-- The full-column hit area keeps dense points hoverable. -->
    <rect
      :x="geometry.x - slot_width / 2"
      :y="TOP_AREA"
      :width="slot_width"
      :height="PLOT_HEIGHT"
      fill="transparent"
      :data-testid="`${props.test_id}-hit-${geometry.index}`"
      @mouseenter="showTooltip(geometry)"
      @mouseleave="hideTooltip"
    />
    <text
      v-if="label_indices.includes(geometry.index)"
      :x="geometry.x"
      :y="VIEWBOX_HEIGHT - 10"
      text-anchor="middle"
      class="fill-slate-400 text-[10px] font-mono"
    >
      {{ geometry.point.label }}
    </text>
  </g>

  <AnalyticsChartTooltip
    :marker="hovered"
    :test_id="props.test_id"
  />
</template>
