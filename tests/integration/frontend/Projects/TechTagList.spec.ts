import TechTagList from '@projects-admin/TechTagList.vue';
import { ensureDeviconIcons } from '@shared-cross/Composables/useDeviconIcons';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useDeviconIcons', async (importOriginal) => {
    const actual =
        await importOriginal<typeof import('@shared-cross/Composables/useDeviconIcons')>();

    return { ...actual, ensureDeviconIcons: vi.fn(actual.ensureDeviconIcons) };
});

describe('TechTagList', () => {
    it('registers the devicon subset offline and renders the glyphs', async () => {
        const wrapper = mount(TechTagList, {
            props: {
                tags: [
                    { id: 1, name: 'Laravel', icon: 'devicon:laravel' },
                    { id: 2, name: 'PostgreSQL', icon: 'devicon:postgresql' },
                ],
                test_id: 'tech-tag-list',
            },
        });

        expect(ensureDeviconIcons).toHaveBeenCalled();

        await vi.waitFor(() => {
            expect(wrapper.find('[data-testid="tech-tag-list-chip-1"] svg path').exists()).toBe(true);
            expect(wrapper.find('[data-testid="tech-tag-list-chip-2"] svg path').exists()).toBe(true);
        });
    });
});
