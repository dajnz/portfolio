import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { SIDEBAR_STORAGE_KEY, useSidebarStore } from '@shared-cross/Stores/sidebarStore';

describe('sidebarStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia());
		window.localStorage.clear();
	});

	it('starts with no collapsed groups', () => {
		const store = useSidebarStore();

		expect(store.collapsedGroups).toEqual([]);
		expect(store.isCollapsed('content')).toBe(false);
	});

	it('toggles a group and persists it to localStorage', () => {
		const store = useSidebarStore();

		store.toggleGroup('content');

		expect(store.isCollapsed('content')).toBe(true);
		expect(JSON.parse(window.localStorage.getItem(SIDEBAR_STORAGE_KEY) ?? '[]')).toEqual(['content']);

		store.toggleGroup('content');

		expect(store.isCollapsed('content')).toBe(false);
		expect(window.localStorage.getItem(SIDEBAR_STORAGE_KEY)).toBe('[]');
	});

	it('collapseGroup and expandGroup set the state explicitly', () => {
		const store = useSidebarStore();

		store.collapseGroup('system');
		expect(store.isCollapsed('system')).toBe(true);

		store.expandGroup('system');
		expect(store.isCollapsed('system')).toBe(false);

		store.expandGroup('system');
		expect(store.isCollapsed('system')).toBe(false);
	});

	it('restores collapsed groups from localStorage', () => {
		window.localStorage.setItem(SIDEBAR_STORAGE_KEY, JSON.stringify(['insights', 'system']));

		const store = useSidebarStore();

		expect(store.isCollapsed('insights')).toBe(true);
		expect(store.isCollapsed('system')).toBe(true);
		expect(store.isCollapsed('content')).toBe(false);
	});

	it('tolerates corrupt localStorage content', () => {
		window.localStorage.setItem(SIDEBAR_STORAGE_KEY, '{not json');

		const store = useSidebarStore();

		expect(store.collapsedGroups).toEqual([]);
	});

	it('ignores non-string entries from stored JSON', () => {
		window.localStorage.setItem(SIDEBAR_STORAGE_KEY, JSON.stringify(['content', 42, null]));

		const store = useSidebarStore();

		expect(store.collapsedGroups).toEqual(['content']);
	});
});
