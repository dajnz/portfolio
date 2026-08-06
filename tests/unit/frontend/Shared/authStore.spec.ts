import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { useAuthStore } from '@shared-cross/Stores/authStore';

describe('authStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia());
	});

	it('starts with no authenticated user on public pages', () => {
		const store = useAuthStore();

		expect(store.user).toBeNull();
		expect(store.isAuthenticated).toBe(false);
	});

	it('hydrates the user from the shared auth prop', () => {
		const store = useAuthStore();

		store.hydrate({
			user: {
				id: 1,
				name: 'Admin',
				login: 'admin',
				email: 'admin@example.com',
				github_linked: true,
				totp_enabled: true,
				is_oauth_primary: false,
			},
		});

		expect(store.user).toEqual({
			id: 1,
			name: 'Admin',
			login: 'admin',
			email: 'admin@example.com',
			github_linked: true,
			totp_enabled: true,
			is_oauth_primary: false,
		});
		expect(store.isAuthenticated).toBe(true);
	});

	it('hydrates null for public pages', () => {
		const store = useAuthStore();

		store.hydrate({ user: null });

		expect(store.user).toBeNull();
		expect(store.isAuthenticated).toBe(false);
	});

	it('tolerates a missing or malformed auth prop', () => {
		const store = useAuthStore();

		store.hydrate(undefined);
		expect(store.user).toBeNull();

		store.hydrate('garbage');
		expect(store.user).toBeNull();
	});

	it('setUser replaces the current user', () => {
		const store = useAuthStore();

		store.setUser({ id: 2, name: 'Other' });
		expect(store.user?.id).toBe(2);

		store.setUser(null);
		expect(store.user).toBeNull();
	});
});
