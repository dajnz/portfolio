import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { trackMock } = vi.hoisted(() => ({
    trackMock: vi.fn(),
}));

vi.mock('@shared-cross/Composables/useAnalytics', () => ({
    useAnalytics: () => ({ track: trackMock, cleanup: vi.fn() }),
}));

import { ARTICLE_READ_THRESHOLD, useReadingProgress, type ReadingProgress } from '@shared-cross/Composables/useReadingProgress';

function stubScrollMetrics(scrollY: number, scrollHeight: number, innerHeight: number): void {
    Object.defineProperty(window, 'scrollY', { value: scrollY, configurable: true });
    Object.defineProperty(document.documentElement, 'scrollHeight', { value: scrollHeight, configurable: true });
    Object.defineProperty(window, 'innerHeight', { value: innerHeight, configurable: true });
}

function dispatchScroll(): void {
    window.dispatchEvent(new Event('scroll'));
}

describe('useReadingProgress', () => {
    let cleanups: Array<() => void> = [];

    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        for (const cleanup of cleanups) {
            cleanup();
        }
        cleanups = [];
        trackMock.mockClear();
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    function track(articleId: number, threshold?: number): ReadingProgress {
        const tracker = useReadingProgress(articleId, threshold);
        cleanups.push(tracker.cleanup);

        return tracker;
    }

    it('computes the current scroll progress', () => {
        stubScrollMetrics(500, 2000, 1000);

        const { progress } = track(42);

        // maxScroll = 1000; 500 / 1000 = 0.5
        expect(progress.value).toBe(0.5);
    });

    it('fires article_read once when crossing the 76% threshold', () => {
        stubScrollMetrics(700, 2000, 1000);

        track(42);

        expect(trackMock).not.toHaveBeenCalled();

        stubScrollMetrics(760, 2000, 1000);
        dispatchScroll();
        vi.advanceTimersByTime(16);

        expect(trackMock).toHaveBeenCalledExactlyOnceWith('article_read', {
            article_id: 42,
            percentage_completed: 76,
        });

        stubScrollMetrics(2000, 2000, 1000);
        dispatchScroll();
        vi.advanceTimersByTime(16);

        expect(trackMock).toHaveBeenCalledTimes(1);
    });

    it('fires with the current percentage, not a fixed value', () => {
        stubScrollMetrics(900, 2000, 1000);

        track(7);

        expect(trackMock).toHaveBeenCalledWith('article_read', {
            article_id: 7,
            percentage_completed: 90,
        });
    });

    it('honors a custom threshold', () => {
        stubScrollMetrics(500, 2000, 1000);

        track(1, 0.5);

        expect(trackMock).toHaveBeenCalledWith('article_read', {
            article_id: 1,
            percentage_completed: 50,
        });
    });

    it('clamps progress to 0..1', () => {
        stubScrollMetrics(-100, 2000, 1000);

        const { progress } = track(9);
        expect(progress.value).toBe(0);

        stubScrollMetrics(5000, 2000, 1000);
        dispatchScroll();
        vi.advanceTimersByTime(16);

        expect(progress.value).toBe(1);
    });

    it('coalesces burst scroll events into a single measurement per frame', () => {
        stubScrollMetrics(100, 2000, 1000);

        const { progress } = track(42);

        stubScrollMetrics(400, 2000, 1000);
        dispatchScroll();
        dispatchScroll();
        dispatchScroll();
        dispatchScroll();

        // Only the first dispatch scheduled a frame — the burst is
        // coalesced and progress reflects the final state after one tick.
        expect(progress.value).toBe(0.1);
        vi.advanceTimersByTime(16);
        expect(progress.value).toBe(0.4);
    });

    it('cleanup removes the scroll and resize listeners', () => {
        stubScrollMetrics(760, 2000, 1000);

        const tracker = track(42);
        trackMock.mockClear();

        tracker.cleanup();

        dispatchScroll();
        window.dispatchEvent(new Event('resize'));
        vi.advanceTimersByTime(16);

        expect(trackMock).not.toHaveBeenCalled();
    });

    it('exports the documented threshold constant', () => {
        expect(ARTICLE_READ_THRESHOLD).toBe(0.76);
    });
});
