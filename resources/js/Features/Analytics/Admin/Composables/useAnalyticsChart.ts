import { type ComputedRef, computed } from 'vue';

export interface ChartPoint {
    label: string;
    value: number;
}

export interface ChartSeries {
    name: string;
    points: Array<ChartPoint>;
}

export interface PointGeometry {
    x: number;
    y: number;
    barHeight: number;
    point: ChartPoint;
    index: number;
}

export interface LinePointGeometry {
    x: number;
    y: number;
}

/** A multi-series point marker with its data — the hover tooltip source. */
export interface SeriesMarker extends LinePointGeometry {
    value: number;
    label: string;
}

export interface HoveredMarker {
    x: number;
    y: number;
    value: number;
    name: string;
}

// Plot geometry: viewBox 640×260, 20 px side padding, value labels in
// the top 40 px, x-labels in the bottom 30 px.
export const VIEWBOX_WIDTH = 640;
export const VIEWBOX_HEIGHT = 260;
export const PADDING_X = 20;
export const TOP_AREA = 40;
export const BOTTOM_AREA = 30;
export const PLOT_HEIGHT = VIEWBOX_HEIGHT - TOP_AREA - BOTTOM_AREA;

// Six distinct hues — the CTA multi-chart legend (primary + semantic
// accents). Indexed by the series position.
const SERIES_COLORS = ['#5C87EF', '#22c55e', '#f59e0b', '#ef4444', '#8FB0F7', '#14b8a6'];

export function seriesColor(index: number): string {
    return SERIES_COLORS[index % SERIES_COLORS.length];
}

/**
 * The AnalyticsChart geometry: the pure coordinate math for the
 * single-series plot (bars/line) and the multi-series plot (the union
 * x-axis, per-series polylines and point markers). Numeric coordinates
 * only — the labels/values render through Vue interpolation into text
 * nodes (the textContent rule).
 */
export function useAnalyticsChart(
    data: ComputedRef<Array<ChartPoint>>,
    series: ComputedRef<Array<ChartSeries>>,
): {
    barWidth: ComputedRef<number>;
    geometries: ComputedRef<Array<PointGeometry>>;
    linePoints: ComputedRef<string>;
    unionLabels: ComputedRef<Array<string>>;
    multiLineGeometries: ComputedRef<Array<Array<LinePointGeometry | null>>>;
    multiLinePoints: ComputedRef<Array<string>>;
    multiLinePointMarkers: ComputedRef<Array<Array<SeriesMarker>>>;
    multiXPosition: (index: number) => number;
} {
    const maxValue = computed(() => {
        if (series.value.length > 0) {
            return Math.max(
                1,
                ...series.value.flatMap((entry) => entry.points.map((point) => point.value)),
            );
        }

        return Math.max(1, ...data.value.map((point) => point.value));
    });

    const barWidth = computed(() => {
        const count = Math.max(1, data.value.length);
        const slot = VIEWBOX_WIDTH - PADDING_X * 2;

        return Math.min(30, slot / count - 8);
    });

    const geometries = computed<Array<PointGeometry>>(() => {
        const points = data.value;
        const count = Math.max(1, points.length);
        const slot = (VIEWBOX_WIDTH - PADDING_X * 2) / count;

        return points.map((point, index) => {
            const barHeight = (point.value / maxValue.value) * PLOT_HEIGHT;

            return {
                x: PADDING_X + slot * index + slot / 2,
                y: TOP_AREA + PLOT_HEIGHT - barHeight,
                barHeight,
                point,
                index,
            };
        });
    });

    const linePoints = computed(() =>
        geometries.value.map((geometry) => `${geometry.x},${geometry.y}`).join(' '),
    );

    // The union of every series' point labels, in first-seen order. The
    // per-target CTA series are cumulative and start on different days,
    // so plotting each series by its own index would time-distort them
    // — every series plots against the same union slots instead.
    const unionLabels = computed<Array<string>>(() => {
        const labels: Array<string> = [];
        const seen = new Set<string>();

        for (const entry of series.value) {
            for (const point of entry.points) {
                if (!seen.has(point.label)) {
                    seen.add(point.label);
                    labels.push(point.label);
                }
            }
        }

        return labels;
    });

    // Each series maps its points onto the union slots; a series
    // without a point for a slot yields null — the polyline skips the
    // gap but the slot stays (the x-axis keeps the full union).
    const multiLineGeometries = computed<Array<Array<LinePointGeometry | null>>>(() => {
        const count = Math.max(1, unionLabels.value.length);
        const slot = (VIEWBOX_WIDTH - PADDING_X * 2) / count;

        return series.value.map((entry) => {
            const pointsByLabel = new Map(entry.points.map((point) => [point.label, point]));

            return unionLabels.value.map((label, index) => {
                const point = pointsByLabel.get(label);
                if (point === undefined) {
                    return null;
                }

                return {
                    x: PADDING_X + slot * index + slot / 2,
                    y: TOP_AREA + PLOT_HEIGHT - (point.value / maxValue.value) * PLOT_HEIGHT,
                };
            });
        });
    });

    const multiLinePoints = computed(() =>
        multiLineGeometries.value.map((line) =>
            line
                .filter((point): point is LinePointGeometry => point !== null)
                .map((point) => `${point.x},${point.y}`)
                .join(' '),
        ),
    );

    // The point markers per series — the non-null geometries with their
    // data. A series with a single point would render an invisible
    // polyline; the marker keeps it visible and carries the value/name
    // for the hover tooltip.
    const multiLinePointMarkers = computed<Array<Array<SeriesMarker>>>(() =>
        series.value.map((entry, seriesIndex) => {
            const pointsByLabel = new Map(entry.points.map((point) => [point.label, point]));

            return multiLineGeometries.value[seriesIndex].flatMap((geometry, slotIndex) => {
                const point = pointsByLabel.get(unionLabels.value[slotIndex]);
                if (geometry === null || point === undefined) {
                    return [];
                }

                return [{ ...geometry, value: point.value, label: point.label }];
            });
        }),
    );

    const multiSlot = computed(() => {
        const count = Math.max(1, unionLabels.value.length);

        return (VIEWBOX_WIDTH - PADDING_X * 2) / count;
    });

    function multiXPosition(index: number): number {
        return PADDING_X + multiSlot.value * index + multiSlot.value / 2;
    }

    return {
        barWidth,
        geometries,
        linePoints,
        unionLabels,
        multiLineGeometries,
        multiLinePoints,
        multiLinePointMarkers,
        multiXPosition,
    };
}
