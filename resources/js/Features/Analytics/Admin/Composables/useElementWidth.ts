import { onBeforeUnmount, onMounted, type Ref, ref } from 'vue';

/**
 * Tracks the rendered pixel width of `element`, starting from `fallback`
 * until the first measurement. Returns the fallback when the element is
 * hidden (zero width) or `ResizeObserver` is unavailable (tests, SSR), so
 * callers get a stable value without a measurement.
 */
export function useElementWidth(element: Ref<HTMLElement | null>, fallback: number): Ref<number> {
    const width = ref(fallback);
    let observer: ResizeObserver | undefined;

    function measure(): void {
        const measured = element.value?.getBoundingClientRect().width ?? 0;

        if (measured > 0) {
            width.value = measured;
        }
    }

    onMounted(() => {
        measure();

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        observer = new ResizeObserver(measure);

        if (element.value !== null) {
            observer.observe(element.value);
        }
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
    });

    return width;
}
