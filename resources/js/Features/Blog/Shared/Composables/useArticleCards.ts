import type { components } from '@shared-cross/Types/inertia';

export type WireArticleCard = components['schemas']['ArticleCardData'];
export type WireTag = components['schemas']['TagData'];

/** The article tags of a card — `PublicBaseBadge` (accent) chips. */
export interface ArticleTagView {
    id: number;
    slug: string;
    name: string;
}

/** The skill tags of a card — linked `TechSkillTag` chips. */
export interface ArticleSkillTagView {
    id: number;
    name: string;
    icon: string;
}

/** The resolved view shape of an article card — every field the
 *  templates read is guaranteed present (the wire type marks all
 *  fields optional). RustFS paths gain the `/assets/` prefix, the
 *  per-locale `lead_image_alt` map resolves for the active locale
 *  (en fallback — the props builder's convention) and the
 *  `short_description` may carry the FTS `<mark>` snippet HTML on
 *  search results (sanitized server-side; the card renders it via
 *  v-html). */
export interface ArticleCardView {
    id: number;
    title: string;
    slug: string;
    short_description: string;
    /** The raw ISO timestamp — consumers format via `formatArticleDate`. */
    published_at: string | null;
    reading_time_minutes: number;
    lead_image: string | null;
    lead_image_alt: string;
    tags: ArticleTagView[];
    skill_tags: ArticleSkillTagView[];
}

/**
 * Maps one wire card row (`ArticleCardData` — shared by the blog index
 * articles and the article page's related cards) onto the ArticleCard
 * component view.
 */
export function toArticleCardView(card: WireArticleCard, locale: string): ArticleCardView {
    return {
        id: card.id ?? 0,
        title: card.title ?? '',
        slug: card.slug ?? '',
        short_description: card.short_description ?? '',
        published_at: card.published_at ?? null,
        reading_time_minutes: card.reading_time_minutes ?? 0,
        lead_image: card.lead_image ? `/assets/${card.lead_image}` : null,
        lead_image_alt: localeText(card.lead_image_alt, locale),
        tags: (card.tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            slug: tag.slug ?? '',
            name: tag.name ?? '',
        })),
        skill_tags: (card.skill_tags ?? []).map((tag) => ({
            id: tag.id ?? 0,
            name: tag.name ?? '',
            icon: tag.icon ?? '',
        })),
    };
}

function localeText(map: Record<string, string> | null | undefined, locale: string): string {
    const value = map?.[locale] ?? map?.en ?? null;

    return value ?? '';
}

/**
 * The article card/meta date ("Jan 15, 2026" / "15 янв. 2026 г.") via
 * `Intl.DateTimeFormat`: the numeric `dd.mm.yyyy` format applies to the
 * compact autocomplete rows only (PublicSearchAutocomplete's
 * `publishedMeta`). An unparseable value renders '' (the caller hides
 * the meta).
 */
export function formatArticleDate(value: string | null, locale: string): string {
    if (value === null) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(locale === 'ru' ? 'ru-RU' : 'en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(date);
}
