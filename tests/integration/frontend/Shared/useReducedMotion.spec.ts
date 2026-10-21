import { afterEach, describe, expect, it, vi } from 'vitest';

import { useReducedMotion } from '@shared-cross/Composables/useReducedMotion';

function stubMatchMedia(matches: boolean, listeners: Array<(event: { matches: boolean }) => void>): void {
    vi.stubGlobal('matchMedia', () => ({
        matches,
        media: '(prefers-reduced-motion: reduce)',
        addEventListener: (_type: string, listener: (event: { matches: boolean }) => void) => {
            listeners.push(listener);
        },
        removeEventListener: vi.fn(),
    }));
}

describe('useReducedMotion', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('is false when the system does not request reduced motion', () => {
        stubMatchMedia(false, []);

        const { prefersReduced } = useReducedMotion();

        expect(prefersReduced.value).toBe(false);
    });

    it('is true when the system requests reduced motion', () => {
        stubMatchMedia(true, []);

        const { prefersReduced } = useReducedMotion();

        expect(prefersReduced.value).toBe(true);
    });

    it('tracks preference changes after startup', () => {
        const listeners: Array<(event: { matches: boolean }) => void> = [];
        stubMatchMedia(false, listeners);

        const { prefersReduced } = useReducedMotion();
        expect(prefersReduced.value).toBe(false);

        listeners[0]({ matches: true });
        expect(prefersReduced.value).toBe(true);

        listeners[0]({ matches: false });
        expect(prefersReduced.value).toBe(false);
    });

    it('degrades to false when matchMedia is unavailable (SSR)', () => {
        vi.stubGlobal('matchMedia', undefined);

        const { prefersReduced } = useReducedMotion();

        expect(prefersReduced.value).toBe(false);
    });
});
