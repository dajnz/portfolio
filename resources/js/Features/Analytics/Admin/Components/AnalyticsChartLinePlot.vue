<!-- AnalyticsChartLinePlot — the single-series line marks of AnalyticsChart: the polyline + a dot and the value/x labels per point. Labels render as text nodes (the textContent rule). -->
<script setup lang="ts">
import { type PointGeometry, VIEWBOX_HEIGHT } from '../Composables/useAnalyticsChart';

defineProps<{
    line_points: string;
    geometries: Array<PointGeometry>;
}>();
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
    <text
      :x="geometry.x"
      :y="geometry.y - 8"
      text-anchor="middle"
      class="fill-slate-500 text-[10px] font-mono"
    >
      {{ geometry.point.value }}
    </text>
    <text
      :x="geometry.x"
      :y="VIEWBOX_HEIGHT - 10"
      text-anchor="middle"
      class="fill-slate-400 text-[10px] font-mono"
    >
      {{ geometry.point.label }}
    </text>
  </g>
</template>
