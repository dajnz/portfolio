import { createPinia, setActivePinia } from 'pinia';
import { nextTick, reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { onMock } = vi.hoisted(() => ({
	onMock: vi.fn(() => vi.fn()),
}));

vi.mock('@inertiajs/vue3', () => ({
	router: { on: onMock },
}));

import { useUnsavedChanges, type UnsavedChangesGuard } from '@shared-cross/Composables/useUnsavedChanges';

function beforeGuard(): (event: { preventDefault: () => void }) => void {
	const call = onMock.mock.calls.find(([type]) => type === 'before');

	return call?.[1] as (event: { preventDefault: () => void }) => void;
}

describe('useUnsavedChanges', () => {
	let guards: UnsavedChangesGuard[] = [];

	beforeEach(() => {
		setActivePinia(createPinia());
		onMock.mockClear();
		guards = [];
		// happy-dom implements no window.confirm — the real browser dialog
		// is replaced by a controllable stub.
		window.confirm = vi.fn(() => true);
	});

	afterEach(() => {
		for (const guard of guards) {
			guard.clear();
		}
		vi.restoreAllMocks();
	});

	function track(form: Record<string, unknown>): UnsavedChangesGuard {
		const guard = useUnsavedChanges(form, { title: 'Draft', meta: { tags: ['vue'] } });
		guards.push(guard);

		return guard;
	}

	it('starts clean when the form matches the initial values', () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		const guard = track(form);

		expect(guard.isDirty.value).toBe(false);
	});

	it('tracks changes with a deep comparison', async () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		const guard = track(form);

		form.title = 'Changed';
		await nextTick();
		expect(guard.isDirty.value).toBe(true);

		form.meta.tags.push('inertia');
		await nextTick();
		expect(guard.isDirty.value).toBe(true);

		form.title = 'Draft';
		form.meta.tags.pop();
		await nextTick();
		expect(guard.isDirty.value).toBe(false);
	});

	it('prompts through beforeunload only when dirty', async () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		track(form);

		const cleanEvent = new Event('beforeunload', { cancelable: true });
		const cleanPreventDefault = vi.spyOn(cleanEvent, 'preventDefault');
		window.dispatchEvent(cleanEvent);
		expect(cleanPreventDefault).not.toHaveBeenCalled();

		form.title = 'Changed';
		await nextTick();

		const dirtyEvent = new Event('beforeunload', { cancelable: true });
		const dirtyPreventDefault = vi.spyOn(dirtyEvent, 'preventDefault');
		window.dispatchEvent(dirtyEvent);
		expect(dirtyPreventDefault).toHaveBeenCalledOnce();
	});

	it('blocks Inertia navigation with the confirm prompt when dirty', async () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		track(form);

		const confirmMock = vi.spyOn(window, 'confirm').mockReturnValue(false);
		form.title = 'Changed';
		await nextTick();

		const event = { preventDefault: vi.fn() };
		beforeGuard()(event);

		expect(confirmMock).toHaveBeenCalledOnce();
		expect(event.preventDefault).toHaveBeenCalledOnce();
	});

	it('allows navigation when the user confirms', async () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		track(form);

		const confirmMock = vi.spyOn(window, 'confirm').mockReturnValue(true);
		form.title = 'Changed';
		await nextTick();

		const event = { preventDefault: vi.fn() };
		beforeGuard()(event);

		expect(confirmMock).toHaveBeenCalledOnce();
		expect(event.preventDefault).not.toHaveBeenCalled();
	});

	it('never prompts when the form is clean', () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		track(form);

		const confirmMock = vi.spyOn(window, 'confirm');

		beforeGuard()({ preventDefault: vi.fn() });

		expect(confirmMock).not.toHaveBeenCalled();
	});

	it('clear() removes both guards', async () => {
		const form = reactive({ title: 'Draft', meta: { tags: ['vue'] } });
		const guard = track(form);

		form.title = 'Changed';
		await nextTick();
		expect(guard.isDirty.value).toBe(true);

		guard.clear();

		const event = new Event('beforeunload', { cancelable: true });
		const preventDefault = vi.spyOn(event, 'preventDefault');
		window.dispatchEvent(event);
		expect(preventDefault).not.toHaveBeenCalled();

		const confirmMock = vi.spyOn(window, 'confirm');
		beforeGuard()({ preventDefault: vi.fn() });
		expect(confirmMock).not.toHaveBeenCalled();
		expect(guard.isDirty.value).toBe(false);
	});
});
