/**
 * The resume layout shape as served by `ResumeEditPageProps.layout`
 * (docs/openapi/inertia/resume.yaml#ResumeLayout) — the template id plus
 * the five fixed sections' visibility/order config. The order range is
 * 0–4 (the request validation, content-management.md:369).
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

export interface ResumeSectionConfig {
    visible: boolean;
    order: number;
}

export type ResumeSectionsMap = Record<ResumeSectionKey, ResumeSectionConfig>;

export interface ResumeLayout {
    template: ResumeTemplate;
    sections: ResumeSectionsMap;
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
};
