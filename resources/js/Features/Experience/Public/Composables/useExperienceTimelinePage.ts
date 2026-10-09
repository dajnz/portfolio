import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

export type TimelineWireItem = components['schemas']['TimelineItemCardData'];

// The resolved view shape the timeline components consume — every field
// the template reads is guaranteed present (the wire type marks all
// fields optional). `period` is precomputed here so the card and the
// detail modal share one formatting path.
export interface TechTagView {
    id: number;
    name: string;
    icon: string;
}

export interface AchievementView {
    description: string;
    project_link: string | null;
}

export interface TimelineItemView {
    id: number;
    position: string;
    company_name: string | null;
    company_country: string | null;
    started_at: string;
    ended_at: string | null;
    is_current: boolean;
    project_name: string | null;
    project_slug: string | null;
    short_description: string;
    tech_tags: TechTagView[];
    achievements: AchievementView[];
    period: string;
}

export interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

export interface UseExperienceTimelinePageApi {
    navItems: ComputedRef<NavItem[]>;
    activeRoute: ComputedRef<string>;
    items: ComputedRef<TimelineItemView[]>;
    isLoading: Ref<boolean>;
    loadError: Ref<boolean>;
    loadMore: () => void;
    retry: () => void;
}

/**
 * Maps the committed ExperienceTimelinePageProps wire shape
 * (docs/openapi/inertia/experience.yaml) onto the timeline component
 * contracts (public-section-components.md:1011-1091): the resolved item
 * views (with the localized period, `YYYY – YYYY` / `YYYY – Present`
 * per the mockup), the public nav items per ADR-0025 (anchors point at the
 * landing page; the Projects link is conditional on the `has_projects`
 * prop — the only published-project signal the wire carries), and the
 * infinite-scroll machinery: "Load More" reloads with the opaque
 * `next_cursor` (`replace: true` → history.replaceState per
 * public-section-ui.md:329-342) and the client REPLACES its items with
 * the reconstructed superset response (ADR-0010 — the props are the list
 * state, nothing is appended locally); a failed request flips the
 * `loadError` state rendered as the inline error banner, `retry()`
 * restarts from the initial batch.
 */
export function useExperienceTimelinePage(props: {
    items?: TimelineWireItem[];
    has_more?: boolean;
    next_cursor?: string | null;
    has_projects?: boolean;
}): UseExperienceTimelinePageApi {
    const { t } = useI18n();
    const isLoading = ref(false);
    const loadError = ref(false);

    // The nav's active link: the current path with the cosmetic
    // `/{locale}/` prefix and the query string stripped — the layout
    // compares nav URLs exactly (`aria-current="page"`).
    const page = usePage<{ url?: string }>();
    const activeRoute = computed(() => normalizeRoute(page.url ?? ''));

    const items = computed<TimelineItemView[]>(() =>
        (props.items ?? []).map((item) => toTimelineItemView(item, t('experience.present'))),
    );

    const navItems = computed<NavItem[]>(() => [
        { label: t('nav.about_me'), url: '/#about' },
        { label: t('nav.projects'), url: '/projects', condition: props.has_projects ?? false },
        { label: t('nav.experience'), url: '/experience' },
        { label: t('nav.blog'), url: '/blog' },
        { label: t('nav.contacts'), url: '/#contacts' },
    ]);

    function loadMore(): void {
        const cursor = props.next_cursor;
        if (cursor === null || cursor === undefined || isLoading.value) {
            return;
        }

        isLoading.value = true;
        loadError.value = false;

        // `reload` forces preserveScroll+preserveState and returns void —
        // failures surface through the network/HTTP visit callbacks. A
        // network failure flips the inline error banner (the URL keeps
        // its pre-visit state, so a retry serves the initial batch);
        // HTTP error responses swap in the backend-rendered ErrorPage
        // (ADR-0015), nothing to fix inline.
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

    return { navItems, activeRoute, items, isLoading, loadError, loadMore, retry };
}

/**
 * Strips the query string and the cosmetic `/{locale}/` prefix from the
 * current Inertia URL — `/ru/experience?cursor=…` → `/experience` — so
 * the header's exact-match active detection works for both locales.
 */
function normalizeRoute(url: string): string {
    const path = url.split('?')[0] ?? '';
    const match = path.match(/^\/(?:en|ru)(\/|$)/);

    return match === null ? path : path.slice(match[1].length);
}

/**
 * The card period per the mockup (experience.html): year-only bounds
 * joined by an en dash — `2024 – Present` (ongoing) / `2021 – 2024`.
 */
function toTimelineItemView(item: TimelineWireItem, presentLabel: string): TimelineItemView {
    const startYear = (item.started_at ?? '').slice(0, 4);
    const endYear = (item.ended_at ?? '').slice(0, 4);
    const end = item.is_current === true || endYear === '' ? presentLabel : endYear;

    return {
        id: item.id ?? 0,
        position: item.position ?? '',
        company_name: item.company_name ?? null,
        company_country: item.company_country ?? null,
        started_at: item.started_at ?? '',
        ended_at: item.ended_at ?? null,
        is_current: item.is_current ?? false,
        project_name: item.project_name ?? null,
        project_slug: item.project_slug ?? null,
        short_description: item.short_description ?? '',
        tech_tags: (item.tech_tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            name: tag.name ?? '',
            icon: tag.icon ?? '',
        })),
        achievements: (item.achievements ?? []).map((achievement) => ({
            description: achievement.description ?? '',
            project_link: achievement.project_link ?? null,
        })),
        period: `${startYear} – ${end}`,
    };
}
