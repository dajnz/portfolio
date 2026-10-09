import { router } from '@inertiajs/vue3';

/**
 * Hover-based prefetch fires only after the pointer lingers this long.
 */
export const HOVER_PREFETCH_DELAY_MS = 100;

export interface UsePrefetchApi {
    /** Visits `url` with `preserveState`; cancels any in-flight prefetch first. */
    prefetch: (url: string) => AbortController;
    /** Hover-based prefetch: fires after the pointer lingers, cancels on leave. */
    onHover: (el: HTMLElement, url: string, delayMs?: number) => () => void;
    /** Visibility-based prefetch: fires when `el` enters the viewport. */
    whenVisible: (el: HTMLElement, url: string, options?: { once?: boolean }) => () => void;
}

/**
 * Inertia page prefetch: wraps `router.visit` with debounce (hover
 * linger) and AbortController cancellation. Prefetches fetch the Inertia
 * page JSON, never `<link rel="prefetch">` HTML.
 */
export function usePrefetch(): UsePrefetchApi {
    let inFlight: AbortController | null = null;

    function cancel(): void {
        if (inFlight) {
            inFlight.abort();
            inFlight = null;
        }
    }

    function prefetch(url: string): AbortController {
        cancel();

        const controller = new AbortController();
        inFlight = controller;

        // Inertia 3 cancels visits via the visit's cancel token
        // (`onCancelToken`) rather than a `signal` option; the
        // AbortController bridges the two.
        router.visit(url, {
            preserveState: true,
            onCancelToken: (cancelToken) => {
                controller.signal.addEventListener('abort', () => cancelToken.cancel(), {
                    once: true,
                });
            },
        });

        return controller;
    }

    function onHover(
        el: HTMLElement,
        url: string,
        delayMs: number = HOVER_PREFETCH_DELAY_MS,
    ): () => void {
        let timer: ReturnType<typeof setTimeout> | null = null;
        let hovered = false;

        const enter = (): void => {
            hovered = true;
            if (timer !== null) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => {
                if (hovered) {
                    prefetch(url);
                }
            }, delayMs);
        };

        const leave = (): void => {
            hovered = false;
            if (timer !== null) {
                clearTimeout(timer);
                timer = null;
            }

            cancel();
        };

        el.addEventListener('mouseenter', enter);
        el.addEventListener('mouseleave', leave);

        return () => {
            hovered = false;
            if (timer !== null) {
                clearTimeout(timer);
            }

            el.removeEventListener('mouseenter', enter);
            el.removeEventListener('mouseleave', leave);
            cancel();
        };
    }

    function whenVisible(
        el: HTMLElement,
        url: string,
        options: { once?: boolean } = {},
    ): () => void {
        if (typeof IntersectionObserver === 'undefined') {
            return () => undefined;
        }

        const observer = new IntersectionObserver((entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }

            prefetch(url);
            if (options.once !== false) {
                observer.disconnect();
            }
        });

        observer.observe(el);

        return () => observer.disconnect();
    }

    return { prefetch, onHover, whenVisible };
}
