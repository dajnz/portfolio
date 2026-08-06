import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { useLocaleStore } from '@shared-cross/Stores/localeStore';

describe('localeStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia());
	});

	it('defaults to en', () => {
		const store = useLocaleStore();

		expect(store.locale).toBe('en');
	});

	it('hydrates the locale from the server-shared prop', () => {
		const store = useLocaleStore();

		store.hydrate('ru');
		expect(store.locale).toBe('ru');
	});

	it('ignores unsupported locales on hydrate', () => {
		const store = useLocaleStore();

		store.hydrate('fr');
		store.hydrate(null);
		store.hydrate(undefined);

		expect(store.locale).toBe('en');
	});

	it('setLocale only accepts supported locales', () => {
		const store = useLocaleStore();

		store.setLocale('ru');
		expect(store.locale).toBe('ru');

		store.setLocale('de');
		expect(store.locale).toBe('ru');
	});
});
