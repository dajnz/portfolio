import type { components } from './inertia';

/**
 * Per-page SEO props — the generated Inertia schema is authoritative.
 */
export type SeoProps = components['schemas']['SeoProps'];

/**
 * JSON-LD structured data block rendered as a
 * `application/ld+json` script with the CSP nonce.
 */
export type JsonLd = Record<string, unknown>;
