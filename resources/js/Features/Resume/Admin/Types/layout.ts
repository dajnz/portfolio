/**
 * The resume layout shape as served by `ResumeEditPageProps.layout` —
 * the template id, the five fixed sections' visibility/order config and
 * the PDF rendering options. The order range is 0–4 (the request
 * validation).
 */

export const RESUME_SECTION_KEYS = [
    'header',
    'about_me',
    'skills',
    'experience',
    'contacts',
] as const;

export type ResumeSectionKey = (typeof RESUME_SECTION_KEYS)[number];

export type ResumeTemplate = 'modern' | 'classic' | 'compact';

export type ResumeSkillsLayout = 'per_line' | 'inline';

export type ResumeContactsLayout = 'per_line' | 'two_columns';

export interface ResumeSectionConfig {
    visible: boolean;
    order: number;
}

export type ResumeSectionsMap = Record<ResumeSectionKey, ResumeSectionConfig>;

export interface ResumeLayout {
    template: ResumeTemplate;
    sections: ResumeSectionsMap;
    skills_layout: ResumeSkillsLayout;
    contacts_show_heading: boolean;
    contacts_layout: ResumeContactsLayout;
    experience_show_skills: boolean;
}

export const DEFAULT_RESUME_LAYOUT: ResumeLayout = {
    template: 'modern',
    sections: {
        header: { visible: true, order: 0 },
        about_me: { visible: true, order: 1 },
        skills: { visible: true, order: 2 },
        experience: { visible: true, order: 3 },
        contacts: { visible: true, order: 4 },
    },
    skills_layout: 'per_line',
    contacts_show_heading: true,
    contacts_layout: 'per_line',
    experience_show_skills: true,
};
