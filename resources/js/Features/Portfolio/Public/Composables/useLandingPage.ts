import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed } from 'vue';

export type LandingProps = NonNullable<components['schemas']['LandingPageProps']['props']>;
export type AboutData = NonNullable<LandingProps['about']>;
export type SkillGroupData = components['schemas']['SkillGroupData'];
export type FeaturedProjectData = components['schemas']['FeaturedProjectData'];
export type ContactData = components['schemas']['ContactData'];
type WireSkill = NonNullable<SkillGroupData['skills']>[number];
type WireTechTag = NonNullable<FeaturedProjectData['tech_tags']>[number];

// The landing-specific slice of the wire props — the page receives the
// full Inertia props object (shared props included); the composable only
// reads its own fields.
export type LandingDataProps = Pick<
    LandingProps,
    'about' | 'skills' | 'featured_projects' | 'has_more' | 'contacts' | 'feedback_honeypot_name'
>;

export type AvailabilityVariant = 'success' | 'warning' | 'danger';
export type CtaVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'icon-only';

export interface AvailabilityView {
    label: string;
    variant: AvailabilityVariant;
}

export interface AboutBlockData {
    name: string;
    title: string;
    short_pitch: string;
    photo: string | null;
    photo_alt: string;
    availability_status: AvailabilityView;
    location: string;
    show_location: boolean;
    timezone: string;
    relocation_readiness: boolean;
    work_type: string;
}

export interface CtaLink {
    label: string;
    url: string;
    icon?: string;
    icon_position?: 'left' | 'right';
    variant: CtaVariant;
}

export interface SkillView {
    id: number;
    name: string;
    icon: string;
    proficiency: number;
    proficiency_label: string | null;
}

export interface SkillGroupView {
    id: number;
    name: string;
    icon: string;
    skills: SkillView[];
}

export interface TechTagView {
    id: number;
    name: string;
    icon: string;
}

export interface FeaturedProjectView {
    id: number;
    title: string;
    slug: string;
    brief_description: string;
    preview_image: string | null;
    tech_tags: TechTagView[];
}

export interface ContactLinkView {
    id: number;
    icon: string;
    name: string;
    value: string;
    url: string | null;
}

export interface FeedbackFormProps {
    action_url: string;
    sender_name_label: string;
    sender_email_label: string;
    message_label: string;
    submit_label: string;
    success_title: string;
    honeypot_name: string | null;
}

export interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

export interface UseLandingPageApi {
    navItems: ComputedRef<NavItem[]>;
    aboutBlock: ComputedRef<AboutBlockData | null>;
    ctaLinks: ComputedRef<CtaLink[]>;
    skillGroups: ComputedRef<SkillGroupView[]>;
    featuredProjects: ComputedRef<FeaturedProjectView[]>;
    hasMore: ComputedRef<boolean>;
    contactLinks: ComputedRef<ContactLinkView[]>;
    feedbackProps: ComputedRef<FeedbackFormProps>;
    externalHosts: ComputedRef<string[]>;
}

const AVAILABILITY_VARIANTS: Record<string, AvailabilityVariant> = {
    available: 'success',
    limited: 'warning',
    busy: 'danger',
};

export function useLandingPage(props: LandingDataProps): UseLandingPageApi {
    const { t } = useI18n();

    // Reactive on purpose: the page component survives Inertia visits —
    // a locale captured once at setup would stay stale after a language
    // switch reload (the same bug class as LanguageSelector).
    const page = usePage<{ locale?: string }>();
    const locale = computed(() => page.props?.locale ?? 'en');

    const aboutBlock = computed<AboutBlockData | null>(() => {
        const about = props.about;
        if (about === undefined || about === null) {
            return null;
        }

        const availability = about.availability_status ?? 'available';

        return {
            name: about.name ?? '',
            title: about.title ?? '',
            short_pitch: about.short_pitch ?? '',
            photo: about.photo ? `/assets/${about.photo}` : null,
            photo_alt: about.photo_alt?.[locale.value as 'en' | 'ru'] ?? '',
            availability_status: {
                label: t(`landing.availability.${availability}`),
                variant: AVAILABILITY_VARIANTS[availability] ?? 'success',
            },
            location: about.location ?? '',
            show_location: about.show_location ?? false,
            timezone: formatTimezone(about.timezone ?? ''),
            relocation_readiness: about.relocation_readiness ?? false,
            work_type: about.work_type ? t(`landing.work_type.${about.work_type}`) : '',
        };
    });

    const githubContact = computed<ContactData | null>(
        () =>
            props.contacts?.find((contact) => contact.icon?.toLowerCase().includes('github')) ??
            null,
    );

    const ctaLinks = computed<CtaLink[]>(() => {
        const links: CtaLink[] = [];

        if (githubContact.value?.url) {
            links.push({
                label: githubContact.value.name ?? '',
                url: githubContact.value.url,
                icon: 'simple-icons:github',
                variant: 'primary',
            });
        }

        links.push({ label: t('landing.download_cv'), url: '/cv', variant: 'secondary' });
        links.push({
            label: t('landing.view_projects'),
            url: '/projects',
            icon: 'heroicons:arrow-right',
            icon_position: 'right',
            variant: 'outline',
        });

        return links;
    });

    const skillGroups = computed<SkillGroupView[]>(() =>
        (props.skills ?? []).map(
            (group: SkillGroupData): SkillGroupView => ({
                id: group.id ?? 0,
                name: group.name ?? '',
                icon: group.icon ?? '',
                skills: (group.skills ?? []).map(
                    (skill: WireSkill): SkillView => ({
                        id: skill.id ?? 0,
                        name: skill.name ?? '',
                        icon: skill.icon ?? '',
                        proficiency: skill.proficiency ?? 0,
                        proficiency_label:
                            skill.proficiency !== undefined
                                ? t(`skills.proficiency.${skill.proficiency}`)
                                : null,
                    }),
                ),
            }),
        ),
    );

    const featuredProjects = computed<FeaturedProjectView[]>(() =>
        (props.featured_projects ?? []).map(
            (project: FeaturedProjectData): FeaturedProjectView => ({
                id: project.id ?? 0,
                title: project.title ?? '',
                slug: project.slug ?? '',
                brief_description: project.brief_description ?? '',
                preview_image: project.preview_image ? `/assets/${project.preview_image}` : null,
                tech_tags: (project.tech_tags ?? []).map(
                    (tag: WireTechTag): TechTagView => ({
                        id: tag.id ?? 0,
                        name: tag.name ?? '',
                        icon: tag.icon ?? '',
                    }),
                ),
            }),
        ),
    );

    const hasMore = computed<boolean>(() => props.has_more ?? false);

    const hasPublishedProjects = computed<boolean>(
        () => (props.featured_projects?.length ?? 0) > 0 || hasMore.value,
    );

    const navItems = computed<NavItem[]>(() => [
        { label: t('nav.about_me'), url: '#about' },
        { label: t('nav.projects'), url: '/projects', condition: hasPublishedProjects.value },
        { label: t('nav.experience'), url: '/experience' },
        { label: t('nav.blog'), url: '/blog' },
        { label: t('nav.contacts'), url: '#contacts' },
    ]);

    const contactLinks = computed<ContactLinkView[]>(() =>
        (props.contacts ?? []).map(
            (contact: ContactData): ContactLinkView => ({
                id: contact.id ?? 0,
                icon: contact.icon ?? '',
                name: contact.name ?? '',
                value: contact.value ?? '',
                url: contact.url ?? null,
            }),
        ),
    );

    const feedbackProps = computed<FeedbackFormProps>(() => ({
        action_url: '/api/feedback',
        sender_name_label: t('landing.feedback_name'),
        sender_email_label: t('landing.feedback_email'),
        message_label: t('landing.feedback_message'),
        submit_label: t('landing.feedback_send'),
        success_title: t('landing.feedback_sent'),
        honeypot_name: props.feedback_honeypot_name ?? null,
    }));

    const externalHosts = computed<string[]>(() => {
        const hosts = new Set<string>();
        const urls = [
            ...ctaLinks.value.map((link) => link.url),
            ...contactLinks.value.map((link) => link.url ?? ''),
        ];

        for (const url of urls) {
            if (!url.startsWith('http')) {
                continue;
            }

            try {
                hosts.add(new URL(url).hostname);
            } catch {
                // Unparseable external URL — no dns-prefetch hint for it.
            }
        }

        return [...hosts];
    });

    return {
        navItems,
        aboutBlock,
        ctaLinks,
        skillGroups,
        featuredProjects,
        hasMore,
        contactLinks,
        feedbackProps,
        externalHosts,
    };
}

/**
 * IANA timezone → display label: the short name ("CET"/"CEST") plus a
 * compact UTC offset. Both derive from Intl so the label is
 * locale-independent; falls back to the raw value when the runtime does
 * not know the timezone.
 */
function formatTimezone(iana: string): string {
    if (iana === '') {
        return '';
    }

    try {
        const name = timeZoneName(iana, 'short') || iana;
        const offset = timeZoneName(iana, 'longOffset').replace(/^GMT/, 'UTC');
        const compact = offset.replace(/:00$/, '').replace(/([+-])0+(\d)/, '$1$2');

        return `${name} (${compact})`;
    } catch {
        return iana;
    }
}

function timeZoneName(timeZone: string, timeZoneName: 'short' | 'longOffset'): string {
    const parts = new Intl.DateTimeFormat('en', { timeZone, timeZoneName }).formatToParts();

    return parts.find((part) => part.type === 'timeZoneName')?.value ?? '';
}
