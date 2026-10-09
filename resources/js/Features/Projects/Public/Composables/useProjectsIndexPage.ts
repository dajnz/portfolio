import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

export type WireProject = components['schemas']['ProjectCardData'];
export type WireSkillTag = components['schemas']['SkillTagData'];

export type SortColumn = 'date' | 'complexity';
export type SortDirection = 'asc' | 'desc';
export type ViewMode = 'grid' | 'list';

// The resolved view shapes the projects index consumes — every field the
// template reads is guaranteed present (the wire type marks all fields
// optional). Locale-keyed maps (`preview_image_alt`, screenshot
// `alt_text`) are resolved for the active locale (en fallback, matching
// the props builder) and RustFS paths gain the `/assets/` prefix.
export interface TechTagView {
    id: number;
    name: string;
    icon: string;
}

export interface ScreenshotView {
    image_url: string;
    alt_text: string;
}

export interface ProjectCardView {
    id: number;
    title: string;
    slug: string;
    brief_description: string;
    preview_image: string | null;
    preview_image_alt: string;
    github_url: string | null;
    live_demo_url: string | null;
    tech_tags: TechTagView[];
    screenshots: ScreenshotView[] | null;
}

export interface SelectedTagView {
    id: number;
    name: string;
}

export interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

export interface UseProjectsIndexPageApi {
    navItems: ComputedRef<NavItem[]>;
    activeRoute: ComputedRef<string>;
    projects: ComputedRef<ProjectCardView[]>;
    isLoading: Ref<boolean>;
    loadError: Ref<boolean>;
    loadMore: () => void;
    retry: () => void;
    searchQuery: Ref<string>;
    selectedTags: Ref<SelectedTagView[]>;
    sortColumn: Ref<SortColumn>;
    sortDirection: Ref<SortDirection>;
    viewMode: Ref<ViewMode>;
    hasActiveFilters: ComputedRef<boolean>;
    applyFilters: () => void;
    clearFilters: () => void;
    setViewMode: (mode: ViewMode) => void;
}

export const PROJECTS_VIEW_MODE_COOKIE = 'projects_view_mode';
const VIEW_MODE_COOKIE_MAX_AGE = 31536000;

/**
 * Maps the committed ProjectsIndexPageProps wire shape onto the projects
 * index component contracts: the resolved card views (localized alt
 * texts, `/assets/` URLs), the URL-synced filter state (`?q=`, `?tags=`,
 * `?sort=date` + `?direction=asc|desc`), the grid/list view mode
 * persisted in the `projects_view_mode` cookie, and the infinite-scroll
 * machinery: "Load More" reloads with the opaque `next_cursor`
 * (`replace: true` — history.replaceState) and the client replaces its
 * items with the reconstructed superset response; a failed request flips
 * the inline error banner, `retry()` restarts from the initial batch.
 * Filter changes reload from the beginning — the cursor resets because
 * the reloaded URL carries no `cursor` param.
 */
export function useProjectsIndexPage(props: {
    projects?: WireProject[];
    skill_tags?: WireSkillTag[];
    has_more?: boolean;
    next_cursor?: string | null;
}): UseProjectsIndexPageApi {
    const { t, locale } = useI18n();
    const isLoading = ref(false);
    const loadError = ref(false);

    const page = usePage<{ url?: string }>();
    const activeRoute = computed(() => normalizeRoute(page.url ?? ''));

    const projects = computed<ProjectCardView[]>(() =>
        (props.projects ?? []).map((project) => toProjectCardView(project, locale.value)),
    );

    const navItems = computed<NavItem[]>(() => [
        { label: t('nav.about_me'), url: '/#about' },
        {
            label: t('nav.projects'),
            url: '/projects',
            condition: (props.projects?.length ?? 0) > 0 || (props.has_more ?? false),
        },
        { label: t('nav.experience'), url: '/experience' },
        { label: t('nav.blog'), url: '/blog' },
        { label: t('nav.contacts'), url: '/#contacts' },
    ]);

    // URL-synced filter state (SSR-safe: initialized from the current
    // query string; preserved across Inertia visits via preserveState).
    const searchQuery = ref(initialQueryParam('q'));
    const selectedTags = ref<SelectedTagView[]>(initialSelectedTags(props.skill_tags ?? []));
    const sortColumn = ref<SortColumn>('date');
    const sortDirection = ref<SortDirection>(
        initialQueryParam('direction') === 'asc' ? 'asc' : 'desc',
    );
    const viewMode = ref<ViewMode>(initialViewMode());

    const hasActiveFilters = computed<boolean>(
        () => searchQuery.value !== '' || selectedTags.value.length > 0,
    );

    function applyFilters(): void {
        const params: Record<string, string> = {};

        if (searchQuery.value !== '') {
            params.q = searchQuery.value;
        }

        if (selectedTags.value.length > 0) {
            params.tags = selectedTags.value.map((tag) => tag.id).join(',');
        }

        if (sortColumn.value !== 'date') {
            params.sort = sortColumn.value;
        }

        if (sortDirection.value === 'asc') {
            params.direction = 'asc';
        }

        router.get(currentPath(), params, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function clearFilters(): void {
        searchQuery.value = '';
        selectedTags.value = [];
        router.get(currentPath(), {}, { preserveState: true, preserveScroll: true });
    }

    function setViewMode(mode: ViewMode): void {
        viewMode.value = mode;
        // biome-ignore lint/suspicious/noDocumentCookie: the `projects_view_mode` cookie, same pattern as useTheme/useI18n
        document.cookie = `${PROJECTS_VIEW_MODE_COOKIE}=${mode};path=/;max-age=${VIEW_MODE_COOKIE_MAX_AGE};SameSite=Lax`;
    }

    function loadMore(): void {
        const cursor = props.next_cursor;
        if (cursor === null || cursor === undefined || isLoading.value) {
            return;
        }

        isLoading.value = true;
        loadError.value = false;

        router.reload({
            data: { cursor },
            replace: true,
            onSuccess: () => {
                isLoading.value = false;
            },
            onError: () => {
                isLoading.value = false;
            },
            onNetworkError: () => {
                isLoading.value = false;
                loadError.value = true;
                return false;
            },
        });
    }

    function retry(): void {
        loadError.value = false;
        isLoading.value = true;

        router.reload({
            replace: true,
            onSuccess: () => {
                isLoading.value = false;
            },
            onError: () => {
                isLoading.value = false;
            },
            onNetworkError: () => {
                isLoading.value = false;
                loadError.value = true;
                return false;
            },
        });
    }

    return {
        navItems,
        activeRoute,
        projects,
        isLoading,
        loadError,
        loadMore,
        retry,
        searchQuery,
        selectedTags,
        sortColumn,
        sortDirection,
        viewMode,
        hasActiveFilters,
        applyFilters,
        clearFilters,
        setViewMode,
    };
}

/**
 * Strips the query string and the cosmetic `/{locale}/` prefix from the
 * current Inertia URL — `/ru/projects?tags=1` → `/projects` — so the
 * header's exact-match active detection works for both locales.
 */
function normalizeRoute(url: string): string {
    const path = url.split('?')[0] ?? '';
    const match = path.match(/^\/(?:en|ru)(\/|$)/);

    return match === null ? path : path.slice(match[1].length);
}

function currentPath(): string {
    if (typeof window === 'undefined') {
        return '/projects';
    }

    return window.location.pathname;
}

function initialQueryParam(name: string): string {
    if (typeof window === 'undefined') {
        return '';
    }

    return new URLSearchParams(window.location.search).get(name) ?? '';
}

/**
 * The selected tag pills after an SSR load: `?tags=1,5` IDs resolved
 * against the `skill_tags` prop (the only name source on the wire).
 */
function initialSelectedTags(skillTags: WireSkillTag[]): SelectedTagView[] {
    if (typeof window === 'undefined') {
        return [];
    }

    const ids = initialQueryParam('tags')
        .split(',')
        .map((id) => Number.parseInt(id, 10))
        .filter((id) => Number.isInteger(id) && id > 0);

    return ids
        .map((id) => {
            const skill = skillTags.find((candidate) => (candidate.id ?? 0) === id);

            return { id, name: skill?.name ?? '' };
        })
        .filter((tag) => tag.name !== '');
}

function initialViewMode(): ViewMode {
    if (typeof document === 'undefined') {
        return 'grid';
    }

    const cookie = document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(`${PROJECTS_VIEW_MODE_COOKIE}=`));

    return cookie?.endsWith('=list') ? 'list' : 'grid';
}

/**
 * Resolves one wire card row into the component view: locale-keyed
 * maps to strings (en fallback — the props builder's convention) and
 * RustFS paths to public `/assets/` URLs. `screenshots` is null
 * when the wire sent none — the card falls back to the static preview
 * image.
 */
function toProjectCardView(project: WireProject, locale: string): ProjectCardView {
    const wireScreenshots = project.screenshots ?? [];

    return {
        id: project.id ?? 0,
        title: project.title ?? '',
        slug: project.slug ?? '',
        brief_description: project.brief_description ?? '',
        preview_image: project.preview_image ? `/assets/${project.preview_image}` : null,
        preview_image_alt: localeText(project.preview_image_alt, locale),
        github_url: project.github_url ?? null,
        live_demo_url: project.live_demo_url ?? null,
        tech_tags: (project.tech_tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            name: tag.name ?? '',
            icon: tag.icon ?? '',
        })),
        screenshots:
            wireScreenshots.length > 0
                ? wireScreenshots.map((shot) => ({
                      image_url: `/assets/${shot.image_path ?? ''}`,
                      alt_text: localeText(shot.alt_text, locale),
                  }))
                : null,
    };
}

function localeText(map: Record<string, string> | undefined, locale: string): string {
    const value = map?.[locale] ?? map?.en ?? null;

    return value ?? '';
}
