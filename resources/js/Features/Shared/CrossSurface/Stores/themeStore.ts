import { defineStore } from 'pinia';
import { ref } from 'vue';

export type Theme = 'light' | 'dark';

/**
 * Global theme state (architecture.md:187-193, i18n-and-theming.md:171-198).
 * The store is hydrated from the server-shared `theme` prop on every Inertia
 * visit — never from localStorage — and only holds the current value; the
 * `<html>` class and the `theme` cookie are managed by `useTheme`.
 */
export const useThemeStore = defineStore('theme', () => {
	const theme = ref<Theme>('light');

	function hydrate(value: unknown): void {
		if (value === 'light' || value === 'dark') {
			theme.value = value;
		}
	}

	function setTheme(value: Theme): void {
		theme.value = value;
	}

	function toggle(): Theme {
		theme.value = theme.value === 'dark' ? 'light' : 'dark';

		return theme.value;
	}

	return { theme, hydrate, setTheme, toggle };
});
