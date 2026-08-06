import type { components } from './inertia';

/**
 * Per-page SEO props (docs/openapi/inertia/shared.yaml#SeoProps, seo.md:18-31).
 * Field names are snake_case per the committed Inertia contract — the seo.md
 * TypeScript interface is camelCase, the generated schema is authoritative.
 */
export type SeoProps = components['schemas']['SeoProps'];

/**
 * JSON-LD structured data block rendered as a
 * `application/ld+json` script with the CSP nonce.
 */
export type JsonLd = Record<string, unknown>;
