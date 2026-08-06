import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { reloadMock } = vi.hoisted(() => ({
	reloadMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
	router: { reload: reloadMock },
}));

import { useI18n } from '@shared-cross/Composables/useI18n';
import { useLocaleStore } from '@shared-cross/Stores/localeStore';

describe('useI18n', () => {
	beforeEach(() => {
		setActivePinia(createPinia());
		reloadMock.mockClear();
	});

	it('resolves dot-path keys from the en catalog', () => {
		const { t } = useI18n();

		expect(t('nav.about_me')).toBe('About Me');
		expect(t('common.save')).toBe('Save');
	});

	it('falls back to the key itself when the translation is missing', () => {
		const { t } = useI18n();

		expect(t('missing.deep.key')).toBe('missing.deep.key');
		expect(t('nav.nonexistent')).toBe('nav.nonexistent');
	});

	it('resolves ICU plural rules for en', () => {
		const { t } = useI18n();

		expect(t('blog.min_read', { minutes: 1 })).toBe('1 min read');
		expect(t('blog.min_read', { minutes: 3 })).toBe('3 min read');
	});

	it('resolves ICU plural rules for ru (one/few/many)', () => {
		const { t } = useI18n();
		const store = useLocaleStore();

		store.setLocale('ru');

		expect(t('blog.min_read', { minutes: 1 })).toBe('1 минута чтения');
		expect(t('blog.min_read', { minutes: 2 })).toBe('2 минуты чтения');
		expect(t('blog.min_read', { minutes: 5 })).toBe('5 минут чтения');
		expect(t('blog.min_read', { minutes: 21 })).toBe('21 минута чтения');
	});

	it('resolves ICU simple placeholders', () => {
		const { t } = useI18n();

		expect(t('admin.profile.recovery_codes_remaining', { count: 5 })).toBe('Recovery codes: 5 remaining');
		expect(t('common.go_to_slide', { number: 2 })).toBe('Go to slide 2');
	});

	it('resolves Laravel-style :param placeholders', () => {
		const { t } = useI18n();

		expect(t('admin.feedback.from', { email: 'alice@example.com' })).toBe(
			'Feedback from alice@example.com',
		);
		expect(t('blog.published_on', { date: '2026-01-01' })).toBe('Published: 2026-01-01');
	});

	it('leaves unknown :param placeholders untouched', () => {
		const { t } = useI18n();

		expect(t('blog.published_on', {})).toBe('Published: :date');
	});

	it('switches the locale, writes the cookie and reloads the page', () => {
		const { setLocale } = useI18n();
		const store = useLocaleStore();

		setLocale('ru');

		expect(store.locale).toBe('ru');
		expect(document.cookie).toContain('locale=ru');
		expect(reloadMock).toHaveBeenCalledOnce();
	});

	it('ignores unsupported locales without reloading', () => {
		const { setLocale } = useI18n();
		const store = useLocaleStore();

		setLocale('fr');

		expect(store.locale).toBe('en');
		expect(reloadMock).not.toHaveBeenCalled();
	});

	it('exposes the locale as a reactive computed', () => {
		const { locale } = useI18n();
		const store = useLocaleStore();

		store.setLocale('ru');

		expect(locale.value).toBe('ru');
	});
});
