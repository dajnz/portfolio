import { getCurrentScope, onScopeDispose, type Ref, ref } from 'vue';

/**
 * `prefers-reduced-motion` accessor (accessibility.md:180-195): reads the
 * system preference at startup and tracks subsequent changes. Components
 * driving JS animations (toast slide-in, modal fade-in, sidebar expand)
 * render the final state immediately when this is true.
 */
export function useReducedMotion(): { prefersReduced: Ref<boolean> } {
    const prefersReduced = ref(false);

    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return { prefersReduced };
    }

    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    prefersReduced.value = query.matches;

    const onChange = (event: MediaQueryListEvent): void => {
        prefersReduced.value = event.matches;
    };

    query.addEventListener('change', onChange);

    if (getCurrentScope()) {
        onScopeDispose(() => query.removeEventListener('change', onChange));
    }

    return { prefersReduced };
}
