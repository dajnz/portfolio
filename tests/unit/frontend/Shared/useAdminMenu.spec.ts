import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';

import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';

describe('useAdminMenu', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('returns the three sidebar groups with their nav items', () => {
        const { menuGroups } = useAdminMenu();

        const groups = menuGroups();

        expect(groups.map((group) => group.id)).toEqual(['content', 'insights', 'system']);
        expect(groups[0].label).toBe('Content');
        expect(groups[1].label).toBe('Insights');
        expect(groups[2].label).toBe('System');
    });

    it('covers the expected nav routes', () => {
        const { menuGroups } = useAdminMenu();

        const routes = menuGroups().flatMap((group) => group.items.map((item) => item.route));

        expect(routes).toEqual([
            '/admin/about',
            '/admin/resume',
            '/admin/skills',
            '/admin/projects',
            '/admin/experience',
            '/admin/blog',
            '/admin/tags',
            '/admin/contacts',
            '/admin/analytics',
            '/admin/feedback',
            '/admin/settings',
            '/admin/backups',
            '/horizon',
        ]);
    });
});
