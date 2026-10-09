export interface UnifiedTagChip {
    id: number;
    name: string;
    type: 'tag' | 'skill';
}

export interface RelatedArticleRow {
    id: number;
    title: string;
}

export interface ArticleTableRow {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    published_at: string | null;
    reading_time_minutes: number;
    views: number;
    lead_image: string | null;
    related_count: number;
    created_at: string;
    updated_at: string;
    tags: UnifiedTagChip[];
}

export interface TagData {
    id: number;
    slug: string;
    name: string;
}

export interface AdminListMeta {
    total: number;
    per_page: number;
    current_page: number;
    sort_column: string;
    sort_direction: 'asc' | 'desc';
    cursors: {
        first: { id: number; sort_value: string | null };
        last: { id: number; sort_value: string | null };
        page: number;
    };
}

export interface ArticleOption {
    id: number;
    slug: string;
    title: string;
}

export interface ArticleEditData {
    id: number;
    slug: string;
    title: Record<string, string>;
    short_description: Record<string, string>;
    content: Record<string, string>;
    published_at: string | null;
    reading_time_minutes: number;
    lead_image: string | null;
    lead_image_alt: Record<string, string | null>;
    is_featured: boolean;
    is_published: boolean;
    related_article_ids: number[];
    tags: TagData[];
    skill_tags: Array<{ id: number; name: string; icon: string | null }>;
    related: RelatedArticleRow[];
}

export interface TagTableRow {
    id: number;
    slug: string;
    name: string;
    articles_use_count: number;
}

export interface TagEditData {
    id: number;
    slug: string;
    name: string;
    names: Record<string, string>;
}
