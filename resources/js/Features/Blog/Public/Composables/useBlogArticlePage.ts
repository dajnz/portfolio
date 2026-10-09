import { type ArticleCardView, toArticleCardView } from '@blog-shared/Composables/useArticleCards';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { components } from '@shared-cross/Types/inertia';
import { type ComputedRef, computed } from 'vue';

export type WireArticleDetail = NonNullable<components['schemas']['ArticleDetailData']>;
export type WireReactions = NonNullable<
    components['schemas']['BlogArticlePageProps']['props']
>['reactions'];

export interface ArticleTagView {
    id: number;
    slug: string;
    name: string;
}

export interface ArticleSkillTagView {
    id: number;
    name: string;
    icon: string;
}

export interface ArticleDetailView {
    id: number;
    title: string;
    slug: string;
    content_html: string;
    short_description: string;
    published_at: string | null;
    reading_time_minutes: number;
    lead_image: string | null;
    lead_image_alt: string;
    tags: ArticleTagView[];
    skill_tags: ArticleSkillTagView[];
    related: ArticleCardView[];
    views_count: number;
    /** The visitor's reaction state — null when the `social_reactions`
     *  feature flag is off (no widget rendered — the ArticleContent
     *  contract's `article.reactions`, public-section-components.md:
     *  1268-1290) or on the signed preview (ADR-0004). */
    reactions: WireReactions;
}

/** A TableOfContents entry — h2/h3 only (public-section-components.md:
 *  1312-1326; h4+ is silently dropped by the extractor). */
export interface ArticleHeading {
    id: string;
    text: string;
    level: 2 | 3;
}

export interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

export interface UseBlogArticlePageApi {
    navItems: ComputedRef<NavItem[]>;
    activeRoute: ComputedRef<string>;
    article: ComputedRef<ArticleDetailView | null>;
    headings: ComputedRef<ArticleHeading[]>;
}

/**
 * Maps the committed BlogArticlePageProps wire shape
 * (docs/openapi/inertia/blog.yaml — `ArticleDetailData`) onto the
 * article page component contracts (public-section-components.md:
 * 1244-1341): the locale-resolved `lead_image_alt`, RustFS paths to
 * public `/assets/` URLs (ADR-0027), the related cards (the full
 * `ArticleCardData` — the detail's wire carries them), and the
 * `headings` the TableOfContents consumes — extracted from
 * `content_html` via DOMParser, `<h2 id>`/`<h3 id>` only (the
 * contract: the extractor scans the rendered HTML and drops h4+).
 * The reactions wire block (`null` when the `social_reactions` flag
 * is off — no widget, ADR-0004) is passed through untouched.
 */
export function useBlogArticlePage(props: {
    article?: WireArticleDetail | null;
    reactions?: WireReactions;
    has_projects?: boolean;
}): UseBlogArticlePageApi {
    const { t, locale } = useI18n();

    const page = usePage<{ url?: string }>();
    // The nav's active link: the section root — `/blog/{slug}` (and
    // its locale-prefixed form) maps to `/blog` so the header's
    // exact-match comparison highlights the Blog link.
    const activeRoute = computed(() => {
        const path = normalizeRoute(page.url ?? '');

        return path.startsWith('/blog/') ? '/blog' : path;
    });

    const article = computed<ArticleDetailView | null>(() =>
        props.article === null || props.article === undefined
            ? null
            : toArticleDetailView(props.article, locale.value, props.reactions ?? null),
    );

    const headings = computed<ArticleHeading[]>(() =>
        extractHeadings(props.article?.content_html ?? ''),
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

    return { navItems, activeRoute, article, headings };
}

/**
 * Strips the query string and the cosmetic `/{locale}/` prefix from
 * the current Inertia URL — `/ru/blog/ledger?x=1` → `/blog/ledger`.
 */
function normalizeRoute(url: string): string {
    const path = url.split('?')[0] ?? '';
    const match = path.match(/^\/(?:en|ru)(\/|$)/);

    return match === null ? path : path.slice(match[1].length);
}

function toArticleDetailView(
    article: WireArticleDetail,
    locale: string,
    reactions: WireReactions,
): ArticleDetailView {
    return {
        id: article.id ?? 0,
        title: article.title ?? '',
        slug: article.slug ?? '',
        content_html: article.content_html ?? '',
        short_description: article.short_description ?? '',
        published_at: article.published_at ?? null,
        reading_time_minutes: article.reading_time_minutes ?? 0,
        lead_image: article.lead_image ? `/assets/${article.lead_image}` : null,
        lead_image_alt: localeText(article.lead_image_alt, locale),
        tags: (article.tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            slug: tag.slug ?? '',
            name: tag.name ?? '',
        })),
        skill_tags: (article.skill_tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            name: tag.name ?? '',
            icon: tag.icon ?? '',
        })),
        related: (article.related ?? []).map((card) => toArticleCardView(card, locale)),
        views_count: article.views_count ?? 0,
        reactions,
    };
}

function localeText(map: Record<string, string> | undefined, locale: string): string {
    const value = map?.[locale] ?? map?.en ?? null;

    return value ?? '';
}

/**
 * The TOC headings — `<h2 id>`/`<h3 id>` only (the contract's
 * extractor rule; h4+ silently dropped). DOMParser over the
 * server-rendered HTML is SSR-safe and independent of the v-html
 * mount timing.
 */
function extractHeadings(contentHtml: string): ArticleHeading[] {
    if (contentHtml === '' || typeof DOMParser === 'undefined') {
        return [];
    }

    const document = new DOMParser().parseFromString(contentHtml, 'text/html');
    const headings: ArticleHeading[] = [];

    for (const element of document.querySelectorAll('h2[id], h3[id]')) {
        const level = element.tagName === 'H2' ? 2 : 3;
        const id = element.getAttribute('id') ?? '';

        if (id !== '') {
            headings.push({ id, text: element.textContent ?? '', level });
        }
    }

    return headings;
}
