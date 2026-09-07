import { expect, test } from '@playwright/test';

import { demoArticles, demoProjects } from '../fixtures/content';

/**
 * The SEO feeds (testing.md:483): `/sitemap.xml` lists the public URLs,
 * `/rss` serves the recent articles as valid XML. Pure HTTP checks — no
 * browser rendering needed, hence no data-testid constraints apply.
 */
test.describe('public feeds', () => {
    test('sitemap.xml lists the public routes', async ({ request }) => {
        const response = await request.get('/sitemap.xml');

        expect(response.ok()).toBeTruthy();

        const body = await response.text();

        expect(body).toContain('<urlset');
        expect(body).toContain('<loc>');

        for (const location of [
            '/blog',
            `/blog/${demoArticles.queues.slug}`,
            `/blog/${demoArticles.postgres.slug}`,
            '/projects',
            `/projects/${demoProjects.portfolio.slug}`,
            '/experience',
        ]) {
            expect(body).toContain(location);
        }
    });

    test('rss feed contains the recent published articles', async ({ request }) => {
        const response = await request.get('/rss');

        expect(response.ok()).toBeTruthy();

        const body = await response.text();

        expect(body).toContain('<rss');
        expect(body).toContain('<item>');
        expect(body).toContain(demoArticles.queues.titleEn);
        expect(body).toContain(demoArticles.postgres.titleEn);
    });
});
