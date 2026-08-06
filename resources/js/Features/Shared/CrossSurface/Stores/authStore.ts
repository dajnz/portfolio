import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

/**
 * The authenticated admin's shared props shape (B3, auth.yaml contract):
 * `{ id, name, email, login, github_linked, totp_enabled, is_oauth_primary }`.
 */
export interface AdminUser {
	id?: number;
	name?: string;
	login?: string;
	email?: string;
	github_linked?: boolean;
	totp_enabled?: boolean;
	is_oauth_primary?: boolean;
}

/**
 * Global admin session state (architecture.md:187-193, authentication.md:11).
 * Hydrated from the server-shared `auth` prop (`auth.user` is null on
 * public pages); sessions are server-managed, so logout/login arrive as
 * full page loads and always re-hydrate this store from the new props.
 */
export const useAuthStore = defineStore('auth', () => {
	const user = ref<AdminUser | null>(null);

	const isAuthenticated = computed(() => user.value !== null);

	function hydrate(authProps: unknown): void {
		const candidate = (authProps as { user?: AdminUser | null } | null | undefined)?.user;
		user.value = candidate ?? null;
	}

	function setUser(next: AdminUser | null): void {
		user.value = next;
	}

	return { user, isAuthenticated, hydrate, setUser };
});
