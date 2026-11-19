import type { components } from '@shared-cross/Types/inertia';

export type WireThumbnail = components['schemas']['ThumbnailData'];

/**
 * The resolved card thumbnail pair: the 1x `/assets/` URL and the
 * `srcset` with both variants. Cards prefer it over the full-size image
 * and fall back while the background generation has not finished
 * (ADR-0047).
 */
export interface ThumbnailView {
    url: string;
    srcset: string;
}

/** RustFS object paths gain the public `/assets/` prefix. */
export function assetUrl(path: string): string {
    return `/assets/${path}`;
}

export function toThumbnailView(thumbnail?: WireThumbnail | null): ThumbnailView | null {
    if (!thumbnail?.path || !thumbnail.retina_path) {
        return null;
    }

    return {
        url: assetUrl(thumbnail.path),
        srcset: `${assetUrl(thumbnail.path)} 1x, ${assetUrl(thumbnail.retina_path)} 2x`,
    };
}
