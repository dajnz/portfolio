import { describe, expect, it, vi } from 'vitest';

const { onMock } = vi.hoisted(() => ({
    onMock: vi.fn(() => vi.fn()),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { on: onMock },
}));

import { useFocusManagement } from '@shared-cross/Composables/useFocusManagement';

function finishHandler(): (() => void) | undefined {
    const call = onMock.mock.calls.find(([type]) => type === 'finish');

    return call?.[1] as (() => void) | undefined;
}

describe('useFocusManagement', () => {
    it('listens for Inertia finish events', () => {
        useFocusManagement();

        expect(onMock).toHaveBeenCalledWith('finish', expect.any(Function));
    });

    it('moves focus to the page h1 with tabindex -1 and preventScroll', () => {
        document.body.innerHTML = '<h1>Page title</h1>';

        useFocusManagement();
        finishHandler()?.();

        const heading = document.querySelector('h1');

        expect(heading?.getAttribute('tabindex')).toBe('-1');
        expect(document.activeElement).toBe(heading);
    });

    it('does nothing when the page has no h1', () => {
        document.body.innerHTML = '<p>no heading</p>';

        useFocusManagement();
        expect(() => finishHandler()?.()).not.toThrow();
    });

    it('returns a cleanup that unsubscribes the listener', () => {
        const unsubscribeMock = vi.fn();
        onMock.mockReturnValueOnce(unsubscribeMock);

        const { cleanup } = useFocusManagement();
        cleanup();

        expect(unsubscribeMock).toHaveBeenCalledOnce();
    });
});
