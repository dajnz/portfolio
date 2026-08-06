import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

/**
 * localStorage key for the admin sidebar's collapsible group state
 * (architecture.md:192 — pure UX preference, no server involvement).
 */
export const SIDEBAR_STORAGE_KEY = 'admin_sidebar_collapsed_groups';

function readStoredGroups(): string[] {
	if (typeof window === 'undefined') {
		return [];
	}

	try {
		const raw = window.localStorage.getItem(SIDEBAR_STORAGE_KEY);
		if (!raw) {
			return [];
		}

		const parsed: unknown = JSON.parse(raw);

		return Array.isArray(parsed) ? parsed.filter((item) => typeof item === 'string') : [];
	} catch {
		// Corrupt or unreadable storage — start collapsed-free.
		return [];
	}
}

/**
 * Admin sidebar collapsible group state
 * (architecture.md:192, admin-section-components.md:340). Persisted in
 * localStorage; SSR renders no state.
 */
export const useSidebarStore = defineStore('sidebar', () => {
	const collapsedGroups = ref<string[]>(readStoredGroups());

	const collapsed = computed(() => new Set(collapsedGroups.value));

	function persist(): void {
		if (typeof window === 'undefined') {
			return;
		}

		window.localStorage.setItem(SIDEBAR_STORAGE_KEY, JSON.stringify(collapsedGroups.value));
	}

	function toggleGroup(groupId: string): void {
		if (collapsed.value.has(groupId)) {
			collapsedGroups.value = collapsedGroups.value.filter((id) => id !== groupId);
		} else {
			collapsedGroups.value.push(groupId);
		}

		persist();
	}

	function collapseGroup(groupId: string): void {
		if (!collapsed.value.has(groupId)) {
			collapsedGroups.value.push(groupId);
			persist();
		}
	}

	function expandGroup(groupId: string): void {
		if (collapsed.value.has(groupId)) {
			collapsedGroups.value = collapsedGroups.value.filter((id) => id !== groupId);
			persist();
		}
	}

	function isCollapsed(groupId: string): boolean {
		return collapsed.value.has(groupId);
	}

	return { collapsedGroups, toggleGroup, collapseGroup, expandGroup, isCollapsed };
});
