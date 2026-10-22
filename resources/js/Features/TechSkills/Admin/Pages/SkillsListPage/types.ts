export interface SkillDraft {
    name: string;
    icon: string | null;
    proficiency: number;
}

export interface NewSkillDraft extends SkillDraft {
    includeInResume: boolean;
}

export interface SkillEditorTarget {
    groupId: number;
    skillId: number | 'new';
}

export interface ProficiencyOption {
    value: string;
    label: string;
}
