import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { useThemeStore } from '@shared-cross/Stores/themeStore';

describe('themeStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia());
	});

	it('defaults to light', () => {
		const store = useThemeStore();

		expect(store.theme).toBe('light');
	});

	it('hydrates the theme from the server-shared prop', () => {
		const store = useThemeStore();

		store.hydrate('dark');
		expect(store.theme).toBe('dark');

		store.hydrate('light');
		expect(store.theme).toBe('light');
	});

	it('ignores invalid hydration values', () => {
		const store = useThemeStore();

		store.hydrate('blue');
		store.hydrate(undefined);
		store.hydrate(null);

		expect(store.theme).toBe('light');
	});

	it('setTheme writes the given theme', () => {
		const store = useThemeStore();

		store.setTheme('dark');

		expect(store.theme).toBe('dark');
	});

	it('toggle flips between light and dark', () => {
		const store = useThemeStore();

		expect(store.toggle()).toBe('dark');
		expect(store.theme).toBe('dark');
		expect(store.toggle()).toBe('light');
		expect(store.theme).toBe('light');
	});
});
