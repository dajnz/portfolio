import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { visitMock } = vi.hoisted(() => ({
	visitMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
	router: { visit: visitMock },
}));

import { HOVER_PREFETCH_DELAY_MS, usePrefetch } from '@shared-cross/Composables/usePrefetch';

class IntersectionObserverMock {
	static instances: IntersectionObserverMock[] = [];

	callback: IntersectionObserverCallback;

	observe = vi.fn();

	disconnect = vi.fn(() => {
		this.disconnected = true;
	});

	disconnected = false;

	constructor(callback: IntersectionObserverCallback) {
		this.callback = callback;
		IntersectionObserverMock.instances.push(this);
	}

	trigger(isIntersecting: boolean): void {
		if (this.disconnected) {
			return;
		}

		this.callback([{ isIntersecting } as IntersectionObserverEntry], this as unknown as IntersectionObserver);
	}
}

function visitOptions(index = 0): { preserveState?: boolean; onCancelToken?: (token: { cancel: () => void }) => void } {
	return visitMock.mock.calls[index][1] ?? {};
}

describe('usePrefetch', () => {
	beforeEach(() => {
		vi.useFakeTimers();
		visitMock.mockClear();
		IntersectionObserverMock.instances = [];
	});

	afterEach(() => {
		vi.useRealTimers();
		vi.unstubAllGlobals();
	});

	it('prefetches with preserveState, bridging AbortController to the visit cancel token', () => {
		const { prefetch } = usePrefetch();

		const controller = prefetch('/blog/some-article');
		const cancelToken = { cancel: vi.fn() };
		const options = visitOptions();

		expect(visitMock).toHaveBeenCalledOnce();
		expect(options.preserveState).toBe(true);
		expect(options.onCancelToken).toEqual(expect.any(Function));

		options.onCancelToken?.(cancelToken);
		controller.abort();

		expect(controller.signal.aborted).toBe(true);
		expect(cancelToken.cancel).toHaveBeenCalledOnce();
	});

	it('cancels the previous in-flight prefetch before starting a new one', () => {
		const { prefetch } = usePrefetch();

		const first = prefetch('/blog/a');
		prefetch('/blog/b');

		expect(first.signal.aborted).toBe(true);
	});

	it('hover prefetch fires only after the linger delay', () => {
		const { onHover } = usePrefetch();
		const el = document.createElement('a');

		onHover(el, '/blog/a');

		el.dispatchEvent(new MouseEvent('mouseenter'));
		vi.advanceTimersByTime(HOVER_PREFETCH_DELAY_MS - 1);
		expect(visitMock).not.toHaveBeenCalled();

		vi.advanceTimersByTime(1);
		expect(visitMock).toHaveBeenCalledExactlyOnceWith('/blog/a', expect.objectContaining({ preserveState: true }));
	});

	it('cancels the hover timer when the pointer leaves before the delay', () => {
		const { onHover } = usePrefetch();
		const el = document.createElement('a');

		onHover(el, '/blog/a');

		el.dispatchEvent(new MouseEvent('mouseenter'));
		el.dispatchEvent(new MouseEvent('mouseleave'));
		vi.advanceTimersByTime(HOVER_PREFETCH_DELAY_MS * 2);

		expect(visitMock).not.toHaveBeenCalled();
	});

	it('aborts an in-flight hover prefetch when the pointer leaves', () => {
		const { onHover } = usePrefetch();
		const el = document.createElement('a');

		onHover(el, '/blog/a');

		el.dispatchEvent(new MouseEvent('mouseenter'));
		vi.advanceTimersByTime(HOVER_PREFETCH_DELAY_MS);

		const cancelToken = { cancel: vi.fn() };
		visitOptions().onCancelToken?.(cancelToken);

		el.dispatchEvent(new MouseEvent('mouseleave'));
		expect(cancelToken.cancel).toHaveBeenCalledOnce();
	});

	it('onHover cleanup removes listeners and cancels any pending prefetch', () => {
		const { onHover } = usePrefetch();
		const el = document.createElement('a');

		const cleanup = onHover(el, '/blog/a');
		el.dispatchEvent(new MouseEvent('mouseenter'));
		cleanup();
		vi.advanceTimersByTime(HOVER_PREFETCH_DELAY_MS * 2);

		expect(visitMock).not.toHaveBeenCalled();
	});

	it('whenVisible prefetches once when the element enters the viewport', () => {
		vi.stubGlobal('IntersectionObserver', IntersectionObserverMock);

		const { whenVisible } = usePrefetch();
		const el = document.createElement('div');

		whenVisible(el, '/blog/a');

		expect(IntersectionObserverMock.instances).toHaveLength(1);
		IntersectionObserverMock.instances[0].trigger(true);
		IntersectionObserverMock.instances[0].trigger(true);

		expect(visitMock).toHaveBeenCalledTimes(1);
		expect(IntersectionObserverMock.instances[0].disconnect).toHaveBeenCalledOnce();
	});

	it('whenVisible keeps observing with once: false', () => {
		vi.stubGlobal('IntersectionObserver', IntersectionObserverMock);

		const { whenVisible } = usePrefetch();
		const el = document.createElement('div');

		whenVisible(el, '/blog/a', { once: false });

		IntersectionObserverMock.instances[0].trigger(true);
		IntersectionObserverMock.instances[0].trigger(true);

		expect(visitMock).toHaveBeenCalledTimes(2);
		expect(IntersectionObserverMock.instances[0].disconnect).not.toHaveBeenCalled();
	});

	it('whenVisible is a no-op without IntersectionObserver support', () => {
		vi.stubGlobal('IntersectionObserver', undefined);

		const { whenVisible } = usePrefetch();
		const el = document.createElement('div');

		expect(() => whenVisible(el, '/blog/a')).not.toThrow();
	});
});
