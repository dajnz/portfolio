<!-- AnalyticsChartBarPlot — the single-series bar marks of AnalyticsChart: one bar + the value/x labels per point. Labels render as text nodes (the textContent rule). -->
<script setup lang="ts">
import { type PointGeometry, VIEWBOX_HEIGHT } from '../Composables/useAnalyticsChart';

defineProps<{
    geometries: Array<PointGeometry>;
    bar_width: number;
}>();
</script>

<template>
  <g
    v-for="geometry in geometries"
    :key="geometry.index"
  >
    <rect
      :x="geometry.x - bar_width / 2"
      :y="geometry.y"
      :width="bar_width"
      :height="geometry.barHeight"
      rx="3"
      fill="#B9CFFB"
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
