import AdminTechSkillPicker from '@shared-admin/AdminTechSkillPicker.vue';
import { ensureDeviconIcons } from '@shared-cross/Composables/useDeviconIcons';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@shared-cross/Composables/useDeviconIcons', async (importOriginal) => {
    const actual =
        await importOriginal<typeof import('@shared-cross/Composables/useDeviconIcons')>();

    return { ...actual, ensureDeviconIcons: vi.fn(actual.ensureDeviconIcons) };
});

describe('AdminTechSkillPicker icon registration', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('registers the devicon subset offline and renders the pill icons', async () => {
        const wrapper = mount(AdminTechSkillPicker, {
            props: {
                modelValue: [{ id: 1, name: 'Laravel', icon: 'devicon:laravel' }],
                test_id: 'tech-skill-picker',
            },
        });

        expect(ensureDeviconIcons).toHaveBeenCalled();

        await vi.waitFor(() => {
            expect(
                wrapper.find('[data-testid="tech-skill-picker-pill-0"] svg path').exists(),
            ).toBe(true);
        });
    });
});
