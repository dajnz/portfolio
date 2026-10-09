import { Head, usePage } from '@inertiajs/vue3';
import type { SeoProps } from '@shared-cross/Types/seo';
import type { VNode } from 'vue';
import { computed, h, onScopeDispose, render, watch } from 'vue';

const JSONLD_MARKER = 'data-seo-jsonld';
const DEFAULT_OG_IMAGE = '/images/og-default.png';
const DEFAULT_OG_IMAGE_WIDTH = 1200;
const DEFAULT_OG_IMAGE_HEIGHT = 630;

/**
 * SEO head sync: on client-side Inertia navigations the composable
 * renders the page's `seo` props into the document head via Inertia's
 * `<Head>` component (title + meta + canonical + robots) and
 * inserts/removes the JSON-LD blocks directly (tags `<Head>` cannot
 * express), carrying the current CSP nonce. SSR renders the same props
 * from the Blade layout — the composable is a client-only no-op there.
 * Feature pages call it once; the composable follows the page's `seo`
 * props reactively.
 */
export function useSeoUpdater(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const page = usePage<{
        seo?: SeoProps | null;
        cspNonce?: string;
        supported_locales?: string[];
    }>();
    const seo = computed(() => page.props?.seo ?? null);
    const supportedLocales = computed(() => page.props?.supported_locales ?? []);

    const container = document.createElement('div');

    function syncHead(): void {
        // Recreate the Head vnode per sync: the head manager replaces its
        // elements on provider disconnect/reconnect, which removes stale
        // title/meta from the previous page.
        render(null, container);
        const current = seo.value;
        const vnode = h(Head, { title: current?.title ?? '' }, () =>
            headChildren(current, supportedLocales.value),
        );
        render(vnode, container);
    }

    function syncJsonLd(): void {
        document.querySelectorAll(`script[${JSONLD_MARKER}]`).forEach((element) => {
            element.remove();
        });

        const blocks = seo.value?.json_ld;
        if (!blocks || blocks.length === 0) {
            return;
        }

        const nonce = page.props?.cspNonce ?? '';
        for (const block of blocks) {
            const script = document.createElement('script');
            script.type = 'application/ld+json';
            script.setAttribute(JSONLD_MARKER, '');
            if (nonce !== '') {
                script.setAttribute('nonce', nonce);
            }
            script.textContent = JSON.stringify(block);
            document.head.appendChild(script);
        }
    }

    syncHead();
    syncJsonLd();

    watch(seo, () => {
        syncHead();
        syncJsonLd();
    });

    onScopeDispose(() => {
        render(null, container);
        document.querySelectorAll(`script[${JSONLD_MARKER}]`).forEach((element) => {
            element.remove();
        });
    });
}

function headChildren(seo: SeoProps | null, supportedLocales: string[]): VNode[] {
    if (seo === null) {
        return [];
    }

    const ogTitle = seo.og_title ?? seo.title;
    const ogDescription = seo.og_description ?? seo.description;
    const ogImage = seo.og_image ?? DEFAULT_OG_IMAGE;

    const nodes: VNode[] = [
        h('meta', { name: 'description', content: seo.description }),
        h('meta', { property: 'og:title', content: ogTitle }),
        h('meta', { property: 'og:description', content: ogDescription }),
        h('meta', { property: 'og:image', content: ogImage }),
        h('meta', {
            property: 'og:image:width',
            content: String(seo.og_image_width ?? DEFAULT_OG_IMAGE_WIDTH),
        }),
        h('meta', {
            property: 'og:image:height',
            content: String(seo.og_image_height ?? DEFAULT_OG_IMAGE_HEIGHT),
        }),
        h('meta', { property: 'og:type', content: seo.og_type ?? 'website' }),
        h('meta', { name: 'twitter:card', content: 'summary_large_image' }),
        h('meta', { name: 'twitter:title', content: ogTitle }),
        h('meta', { name: 'twitter:description', content: ogDescription }),
        h('meta', { name: 'twitter:image', content: ogImage }),
    ];

    if (seo.og_url !== undefined) {
        nodes.push(h('meta', { property: 'og:url', content: seo.og_url }));
    }

    if (seo.canonical_url !== undefined) {
        // hreflang alternates: locale-prefixed variants of the slashless
        // canonical + x-default. The Blade layout renders the same set
        // during SSR from the shared `supported_locales` prop, so
        // hydration replaces them 1:1.
        for (const alternate of alternateHrefs(seo.canonical_url, supportedLocales)) {
            nodes.push(
                h('link', { rel: 'alternate', hreflang: alternate.hreflang, href: alternate.href }),
            );
        }
        nodes.push(h('link', { rel: 'alternate', hreflang: 'x-default', href: seo.canonical_url }));
        nodes.push(h('link', { rel: 'canonical', href: seo.canonical_url }));
    }

    if (seo.robots !== undefined) {
        nodes.push(h('meta', { name: 'robots', content: seo.robots }));
    }

    if (seo.article !== undefined && seo.article !== null) {
        if (seo.article.published_time) {
            nodes.push(
                h('meta', {
                    property: 'article:published_time',
                    content: seo.article.published_time,
                }),
            );
        }
        if (seo.article.author) {
            nodes.push(h('meta', { property: 'article:author', content: seo.article.author }));
        }
        for (const tag of seo.article.tags ?? []) {
            nodes.push(h('meta', { property: 'article:tag', content: tag }));
        }
    }

    return nodes;
}

interface AlternateHref {
    hreflang: string;
    href: string;
}

/**
 * Locale-prefixed URL variants of a canonical (slashless) URL —
 * `https://site/blog/x` → `https://site/{locale}/blog/x`; the root path
 * maps to `https://site/{locale}` (no trailing slash, mirroring the
 * sitemap alternates).
 */
function alternateHrefs(canonicalUrl: string, locales: string[]): AlternateHref[] {
    let parsed: URL;
    try {
        parsed = new URL(canonicalUrl);
    } catch {
        return [];
    }

    const path = parsed.pathname === '/' ? '' : parsed.pathname;

    return locales.map((locale) => ({
        hreflang: locale,
        href: `${parsed.origin}/${locale}${path}`,
    }));
}
