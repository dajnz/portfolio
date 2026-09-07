'use strict';

/**
 * Lighthouse CI config for the public Performance Budgets
 * (architecture.md:1197-1264, plan step 13.3). The budget caps live in
 * `./budgets.json` (mirroring the architecture.md table — the single
 * source of truth); this file derives LHCI per-URL assertions from it.
 *
 * Budget failures assert at WARN severity — the plan keeps the gate
 * non-blocking until the budgets are consistently met (architecture.md:
 * 1262-1263), then flips to blocking.
 *
 * Pages are collected fresh (no caching) with one run each; the URLs are
 * supplied through `LH_URLS` (comma-separated, canonical public routes
 * incl. a blog article and a project detail — the e2e demo dataset,
 * fixtures in tests/e2e/fixtures/content.ts).
 */

const fs = require('fs');
const path = require('path');

const budgets = JSON.parse(
  fs.readFileSync(path.join(__dirname, 'budgets.json'), 'utf8'),
);

// Chrome resolution: the dev container bakes PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
// into the image; CI installs Playwright's own chromium (npm ci +
// `npx playwright install --with-deps chromium`).
let playwrightChromePath;
try {
  playwrightChromePath = require('playwright').chromium.executablePath();
} catch {
  playwrightChromePath = undefined;
}

const chromePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ?? playwrightChromePath;

// LHCI asserts a URL group per matching pattern; `/` must not fall back
// to "matches everything" here (the converter does that), so each path
// compiles to a disjoint regex against the final page URL.
function pathToPattern(pathExpression) {
  if (pathExpression === '/') {
    return 'https?://[^/]+/?$';
  }

  const escaped = pathExpression
    .split('*')
    .map((part) => part.replace(/([-[\]{}()*+?.,\\^$|#\s])/g, '\\$1'))
    .join('.*');

  return `https?://[^/]+${escaped}$`;
}

const scriptBudgetsKb = budgets.map((budget) => {
  const sizes = (budget.resourceSizes ?? []).filter(
    (size) => size.resourceType === 'script',
  );

  return { path: budget.path, budgetKb: sizes[0]?.budget ?? 0 };
});

module.exports = {
  ci: {
    collect: {
      url: (process.env.LH_URLS ?? '').split(',').filter(Boolean),
      numberOfRuns: 1,
      ...(chromePath ? { chromePath } : {}),
      settings: {
        // The container Chromium (dev Alpine image) and CI both run
        // without a usable setuid/userns sandbox — standard for audit
        // browsers; the pages under test are our own. The dev container's
        // /dev/shm is small — Chromium must not rely on it for frames.
        // NOTE: LHCI joins array-valued chromeFlags with commas; pass a
        // single space-joined string so each flag reaches the browser.
        chromeFlags: '--no-sandbox --disable-dev-shm-usage',
      },
    },
    assert: {
      // Assert matrix (per-URL budget groups) derived from budgets.json.
      assertMatrix: scriptBudgetsKb.map(({ path: pathExpression, budgetKb }) => ({
        matchingUrlPattern: pathToPattern(pathExpression),
        assertions: {
          'resource-summary:script:size': [
            'warn',
            { maxNumericValue: budgetKb * 1024 },
          ],
        },
      })),
    },
    upload: {
      target: 'filesystem',
      outputDir: path.join(__dirname, '..', '.lighthouseci'),
    },
  },
};
