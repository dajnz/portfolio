import { expect, test } from '@playwright/test';

import { unique } from '../fixtures/admin';

/**
 * The skills admin surface (testing.md:494). The tests are serial and
 * share the created group: the first creates a group with a skill and
 * verifies the landing page, the second moves the group up, the last
 * deletes it — the public project (which runs after the admin project)
 * still sees the seeded groups.
 */
test.describe.configure({ mode: 'serial' });

const token = unique('e2e-skills');
const groupEn = `${token} Group`;
const groupRu = `Группа ${token}`;

let newGroupId: number | null = null;

test.describe('skills management', () => {
    test('creates a group with a skill and shows it on the landing page', async ({ page }) => {
        await page.goto('/admin/skills');
        await page.getByTestId('skills-list-page-add-group').click();

        await page.getByTestId('skills-list-page-new-group-name-en').fill(groupEn);
        await page.getByTestId('skills-list-page-new-group-tabs-tab-ru').click();
        await page.getByTestId('skills-list-page-new-group-name-ru').fill(groupRu);
        await page.getByTestId('skills-list-page-new-group-save').click();

        // Wait for the refresh, then read the list order: the saved
        // group gets its own id and lands at the end.
        await expect.poll(async () => (await groupIdsInOrder(page)).length).toBeGreaterThan(3);

        const groupIds = await groupIdsInOrder(page);
        newGroupId = groupIds[groupIds.length - 1];

        expect(newGroupId).not.toBeNull();
        await expect(page.getByTestId(`skills-list-page-group-${newGroupId}`)).toContainText(groupEn);

        // Add a skill into the new group.
        await page.getByTestId(`skills-list-page-group-${newGroupId}-add-skill`).click();
        await page.getByTestId(`skills-list-page-new-skill-${newGroupId}-name`).fill(token);
        await page.getByTestId(`skills-list-page-new-skill-${newGroupId}-proficiency`).click();
        await page.getByTestId(`skills-list-page-new-skill-${newGroupId}-proficiency-option-3`).click();
        await page.getByTestId(`skills-list-page-new-skill-${newGroupId}-save`).click();

        await expect(page.getByTestId(`skills-list-page-group-${newGroupId}`)).toContainText(token);

        // The landing page renders the new group and its skill.
        await page.goto('/');
        const skillsBlock = page.getByTestId('landing-skills-block');

        await expect(skillsBlock).toContainText(groupEn);
        await expect(skillsBlock).toContainText(token);
    });

    test('moves the new group up in the list', async ({ page }) => {
        expect(newGroupId).not.toBeNull();

        await page.goto('/admin/skills');
        const before = await groupIdsInOrder(page);

        await page.getByTestId(`skills-list-page-group-${newGroupId}-up`).click();
        await expect
            .poll(async () => groupIdsInOrder(page))
            .toEqual([...before.slice(0, before.length - 2), newGroupId, before[before.length - 2]]);
    });

    test('deletes the created group', async ({ page }) => {
        expect(newGroupId).not.toBeNull();

        await page.goto('/admin/skills');
        await page.getByTestId(`skills-list-page-group-${newGroupId}-delete`).click();
        await page.getByTestId('skills-list-page-delete-dialog-confirm').click();

        await expect(page.locator(`[data-testid="skills-list-page-group-${newGroupId}"]`)).toHaveCount(0);
    });
});

/** The group ids in their DOM order (the group testids are unique roots). */
async function groupIdsInOrder(page: import('@playwright/test').Page): Promise<number[]> {
    await expect(page.getByTestId('skills-list-page-groups-sortable')).toBeVisible();

    return page.evaluate(() =>
        Array.from(document.querySelectorAll<HTMLElement>('[data-testid^="skills-list-page-group-"]'))
            .map((el) => /^skills-list-page-group-(\d+)$/.exec(el.getAttribute('data-testid') ?? ''))
            .filter((match): match is RegExpExecArray => match !== null)
            .map((match) => Number(match[1])),
    );
}
