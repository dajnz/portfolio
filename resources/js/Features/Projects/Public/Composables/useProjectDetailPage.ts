import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed } from 'vue';

export type WireProjectDetail = NonNullable<components['schemas']['ProjectDetailData']>;

export interface TechTagView {
    id: number;
    name: string;
    icon: string;
}

export interface DetailScreenshotView {
    id: number;
    image_url: string;
    alt_text: string;
    title: string;
    description: string;
}

export interface DetailItemView {
    id: number;
    icon: string | null;
    title: string;
    description: string;
}

export interface TechWriteupView {
    id: number;
    slug: string;
    title: string;
}

export interface ProjectDetailView {
    id: number;
    title: string;
    slug: string;
    full_description: string | null;
    problem: string | null;
    solution: string | null;
    brief_description: string;
    github_url: string | null;
    live_demo_url: string | null;
    tech_tags: TechTagView[];
    screenshots: DetailScreenshotView[];
    detail_items: DetailItemView[];
    tech_writeup: TechWriteupView | null;
}

export interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

export interface UseProjectDetailPageApi {
    navItems: ComputedRef<NavItem[]>;
    activeRoute: ComputedRef<string>;
    project: ComputedRef<ProjectDetailView | null>;
}

/**
 * Maps the committed ProjectDetailPageProps wire shape onto the
 * project detail component contracts: the locale-keyed screenshot maps
 * (`alt_text`, `title`, `description`) resolved for the active locale
 * with the en fallback, RustFS paths to public `/assets/` URLs, and the
 * engineering detail rows (already localized by the props builder). The
 * wire's `tech_writeup` carries only `{id, slug, title}`, and the
 * section hides when it is null (unlinked or the article was
 * unpublished). The `full_description`/`problem`/`solution` bodies
 * arrive as pipeline-rendered HTML and are injected via v-html.
 */
export function useProjectDetailPage(props: {
    project?: WireProjectDetail | null;
}): UseProjectDetailPageApi {
    const { t, locale } = useI18n();

    const page = usePage<{ url?: string }>();
    // The nav's active link: the section root — `/projects/{slug}` (and
    // its locale-prefixed form) maps to `/projects` so the header's
    // exact-match comparison highlights the Projects link.
    const activeRoute = computed(() => {
        const path = normalizeRoute(page.url ?? '');

        return path.startsWith('/projects/') ? '/projects' : path;
    });

    const project = computed<ProjectDetailView | null>(() =>
        props.project === null || props.project === undefined
            ? null
            : toProjectDetailView(props.project, locale.value),
    );

    const navItems = computed<NavItem[]>(() => [
        { label: t('nav.about_me'), url: '/#about' },
        { label: t('nav.projects'), url: '/projects' },
        { label: t('nav.experience'), url: '/experience' },
        { label: t('nav.blog'), url: '/blog' },
        { label: t('nav.contacts'), url: '/#contacts' },
    ]);

    return { navItems, activeRoute, project };
}

/**
 * Strips the query string and the cosmetic `/{locale}/` prefix from the
 * current Inertia URL — `/ru/projects/ledger?x=1` → `/projects/ledger`.
 */
function normalizeRoute(url: string): string {
    const path = url.split('?')[0] ?? '';
    const match = path.match(/^\/(?:en|ru)(\/|$)/);

    return match === null ? path : path.slice(match[1].length);
}

function toProjectDetailView(project: WireProjectDetail, locale: string): ProjectDetailView {
    return {
        id: project.id ?? 0,
        title: project.title ?? '',
        slug: project.slug ?? '',
        full_description: project.full_description ?? null,
        problem: project.problem ?? null,
        solution: project.solution ?? null,
        brief_description: project.brief_description ?? '',
        github_url: project.github_url ?? null,
        live_demo_url: project.live_demo_url ?? null,
        tech_tags: (project.tech_tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            name: tag.name ?? '',
            icon: tag.icon ?? '',
        })),
        screenshots: (project.screenshots ?? []).map((shot) => ({
            id: shot.id ?? 0,
            image_url: `/assets/${shot.image_path ?? ''}`,
            alt_text: localeText(shot.alt_text, locale),
            title: localeText(shot.title, locale),
            description: localeText(shot.description, locale),
        })),
        detail_items: (project.detail_items ?? []).map((item) => ({
            id: item.id ?? 0,
            icon: item.icon ?? null,
            title: item.title ?? '',
            description: item.description ?? '',
        })),
        tech_writeup:
            project.tech_writeup === null || project.tech_writeup === undefined
                ? null
                : {
                      id: project.tech_writeup.id ?? 0,
                      slug: project.tech_writeup.slug ?? '',
                      title: project.tech_writeup.title ?? '',
                  },
    };
}

function localeText(map: Record<string, string> | undefined, locale: string): string {
    const value = map?.[locale] ?? map?.en ?? null;

    return value ?? '';
}
