import { usePage } from '@inertiajs/vue3';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useAppVersion } from '@shared-cross/Composables/useAppVersion';
import { useLocaleStore } from '@shared-cross/Stores/localeStore';

vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(() => ({ props: {} })),
}));

function setVersion(version: string | undefined): void {
    vi.mocked(usePage).mockReturnValue({
        props: { version },
    } as unknown as ReturnType<typeof usePage>);
}

describe('useAppVersion', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        setVersion('v1.4.0');
    });

    it('formats the shared version into the localized label', () => {
        expect(useAppVersion().value).toBe('Version: v1.4.0');
    });

    it('localizes the label', () => {
        const store = useLocaleStore();
        store.setLocale('ru');

        expect(useAppVersion().value).toBe('Версия: v1.4.0');
    });

    it('returns an empty string when no version is shared', () => {
        setVersion('');
        expect(useAppVersion().value).toBe('');

        setVersion(undefined);
        expect(useAppVersion().value).toBe('');
    });
});
