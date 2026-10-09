/**
 * The achievement row of the admin experience form — the client-side
 * editor unit shared by AchievementItem, AchievementList and
 * ExperienceFormPage. The `uid` is a client-side key for the SortableList
 * (the backend achievements carry no stable id) and is stripped from the
 * submit payload; the description is the per-locale map edited under the
 * active LocaleTabs locale (the backend stores only the locales with a
 * description).
 */
export interface AchievementRow extends Record<string, unknown> {
    uid: string;
    description: Record<string, string>;
    project_link: string | null;
}
