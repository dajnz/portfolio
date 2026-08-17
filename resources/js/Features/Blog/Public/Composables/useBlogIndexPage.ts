import { type ArticleCardView, toArticleCardView } from '@blog-shared/Composables/useArticleCards';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

export type WireArticleCard = components['schemas']['ArticleCardData'];
export type WireTag = components['schemas']['TagData'];

export type ViewMode = 'grid' | 'list';

/** A selected tag pill — the `type` discriminator keeps the article
 *  tag and skill id spaces unambiguous (`?article_tags=` vs
 *  `?skill_tags=`, content-management.md:200). Optional (the shared
 *  PublicTagFilter's model type) — a missing type means an article
 *  tag. */
export interface SelectedTagView {
    id: number;
    name: string;
    /** The shared PublicTagFilter's discriminator — structurally
     *  compatible with its model type (`type?: string`). */
    type?: string;
}

export interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

export interface UseBlogIndexPageApi {
    navItems: ComputedRef<NavItem[]>;
    activeRoute: ComputedRef<string>;
    articles: ComputedRef<ArticleCardView[]>;
    availableYears: ComputedRef<string[]>;
    hasActiveFilters: ComputedRef<boolean>;
    isLoading: Ref<boolean>;
    loadError: Ref<boolean>;
    loadMore: () => void;
    retry: () => void;
    searchQuery: Ref<string>;
    selectedTags: Ref<SelectedTagView[]>;
    activeYear: Ref<string | null>;
    viewMode: Ref<ViewMode>;
    applyFilters: () => void;
    clearFilters: () => void;
    setViewMode: (mode: ViewMode) => void;
}

export const BLOG_VIEW_MODE_COOKIE = 'blog_view_mode';
const VIEW_MODE_COOKIE_MAX_AGE = 31536000;

/**
 * Maps the committed BlogIndexPageProps wire shape
 * (docs/openapi/inertia/blog.yaml) onto the blog index component
 * contracts (public-section-components.md:1209-1239): the resolved
 * card views, the URL-synced filter state (`?q=`, `?article_tags=` +
 * `?skill_tags=` — the AND/intersect split per tag type, `?year=`),
 * the grid/list view mode persisted in the `blog_view_mode` cookie
 * (architecture.md:204 — client-side write + read, the backend has
 * no view-mode prop), and the infinite-scroll machinery mirroring
 * 9.2/9.3: "Load More" reloads with the opaque `next_cursor`
 * (`replace: true` — history.replaceState per
 * public-section-ui.md:329-342) and the client REPLACES its items
 * with the reconstructed superset response (B158); a failed request
 * flips the inline error banner, `retry()` restarts from the initial
 * batch. Filter changes reload from the beginning — the cursor resets
 * because the reloaded URL carries no `cursor` param
 * (public-section-ui.md:156). The A10 nav mirrors the other public
 * pages: the Projects link is conditional on `has_projects` (B268).
 */
export function useBlogIndexPage(props: {
    articles?: WireArticleCard[];
    tags?: WireTag[];
    years?: number[];
    has_more?: boolean;
    next_cursor?: string | null;
    has_projects?: boolean;
}): UseBlogIndexPageApi {
    const { t, locale } = useI18n();
    const isLoading = ref(false);
    const loadError = ref(false);

    const page = usePage<{ url?: string }>();
    const activeRoute = computed(() => normalizeRoute(page.url ?? ''));

    const articles = computed<ArticleCardView[]>(() =>
        (props.articles ?? []).map((card) => toArticleCardView(card, locale.value)),
    );

    // The year filter options — the wire sends integers, the header
    // contract declares strings (public-section-components.md:1223).
    const availableYears = computed<string[]>(() =>
        (props.years ?? []).map((year) => String(year)),
    );

    const navItems = computed<NavItem[]>(() => [
        { label: t('nav.about_me'), url: '/#about' },
        {
            label: t('nav.projects'),
            url: '/projects',
            condition: props.has_projects ?? false,
        },
        { label: t('nav.experience'), url: '/experience' },
        { label: t('nav.blog'), url: '/blog' },
        { label: t('nav.contacts'), url: '/#contacts' },
    ]);

    // URL-synced filter state (SSR-safe: initialized from the current
    // query string; preserved across Inertia visits via preserveState).
    const searchQuery = ref(initialQueryParam('q'));
    const selectedTags = ref<SelectedTagView[]>(
        initialSelectedTags(props.tags ?? [], props.articles ?? []),
    );
    const activeYear = ref<string | null>(initialQueryParam('year') || null);
    const viewMode = ref<ViewMode>(initialViewMode());

    const hasActiveFilters = computed<boolean>(
        () =>
            searchQuery.value !== '' || selectedTags.value.length > 0 || activeYear.value !== null,
    );

    function applyFilters(): void {
        const params: Record<string, string> = {};

        if (searchQuery.value !== '') {
            params.q = searchQuery.value;
        }

        const articleIds = selectedTags.value
            .filter((tag) => tag.type !== 'skill')
            .map((tag) => tag.id);
        const skillIds = selectedTags.value
            .filter((tag) => tag.type === 'skill')
            .map((tag) => tag.id);

        if (articleIds.length > 0) {
            params.article_tags = articleIds.join(',');
        }

        if (skillIds.length > 0) {
            params.skill_tags = skillIds.join(',');
        }

        if (activeYear.value !== null) {
            params.year = activeYear.value;
        }

        router.get(currentPath(), params, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function clearFilters(): void {
        searchQuery.value = '';
        selectedTags.value = [];
        activeYear.value = null;
        router.get(currentPath(), {}, { preserveState: true, preserveScroll: true });
    }

    function setViewMode(mode: ViewMode): void {
        viewMode.value = mode;
        // biome-ignore lint/suspicious/noDocumentCookie: the spec-mandated `blog_view_mode` cookie (architecture.md:204), same pattern as useTheme/useI18n
        document.cookie = `${BLOG_VIEW_MODE_COOKIE}=${mode};path=/;max-age=${VIEW_MODE_COOKIE_MAX_AGE};SameSite=Lax`;
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
        articles,
        availableYears,
        hasActiveFilters,
        isLoading,
        loadError,
        loadMore,
        retry,
        searchQuery,
        selectedTags,
        activeYear,
        viewMode,
        applyFilters,
        clearFilters,
        setViewMode,
    };
}

/**
 * Strips the query string and the cosmetic `/{locale}/` prefix from
 * the current Inertia URL — `/ru/blog?year=2026` → `/blog`.
 */
function normalizeRoute(url: string): string {
    const path = url.split('?')[0] ?? '';
    const match = path.match(/^\/(?:en|ru)(\/|$)/);

    return match === null ? path : path.slice(match[1].length);
}

function currentPath(): string {
    if (typeof window === 'undefined') {
        return '/blog';
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
 * The selected tag pills after an SSR load: the `?article_tags=` ids
 * resolve against the `tags` prop (all article tags — the wire name
 * source), the `?skill_tags=` ids against the first batch's card
 * `skill_tags` — the AND/intersect guarantees every displayed article
 * carries every selected skill, so the visible batch always resolves
 * the names. On an empty batch (a no-results reload) unresolvable
 * skill pills are omitted — the URL still applies the filter
 * server-side (B269).
 */
function initialSelectedTags(tags: WireTag[], articles: WireArticleCard[]): SelectedTagView[] {
    if (typeof window === 'undefined') {
        return [];
    }

    const idsOf = (name: string): number[] =>
        initialQueryParam(name)
            .split(',')
            .map((id) => Number.parseInt(id, 10))
            .filter((id) => Number.isInteger(id) && id > 0);

    const articlePills = idsOf('article_tags')
        .map((id) => {
            const tag = tags.find((candidate) => (candidate.id ?? 0) === id);

            return { id, name: tag?.name ?? '', type: 'tag' as const };
        })
        .filter((pill) => pill.name !== '');

    const skillNames = new Map<number, string>();
    for (const card of articles) {
        for (const skill of card.skill_tags ?? []) {
            if (!skillNames.has(skill.id ?? 0)) {
                skillNames.set(skill.id ?? 0, skill.name ?? '');
            }
        }
    }

    const skillPills = idsOf('skill_tags')
        .map((id) => ({ id, name: skillNames.get(id) ?? '', type: 'skill' as const }))
        .filter((pill) => pill.name !== '');

    return [...articlePills, ...skillPills];
}

function initialViewMode(): ViewMode {
    if (typeof document === 'undefined') {
        return 'grid';
    }

    const cookie = document.cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(`${BLOG_VIEW_MODE_COOKIE}=`));

    return cookie?.endsWith('=list') ? 'list' : 'grid';
}
