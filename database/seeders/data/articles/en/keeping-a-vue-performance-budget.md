Like any budget, a performance budget is only useful when it is measured automatically and breaks the build when exceeded.

## Start with the real user metric

The bundle size is a proxy. What matters is Largest Contentful Paint on a mid-range phone, and that number is dominated by three things:

| Cost | Typical offender |
| --- | --- |
| JavaScript parse + execute | large component library |
| Hydration | rendering everything eagerly |
| Network | oversized vendor chunk |

> [!IMPORTANT]
> Measure on a throttled CPU. A flagship laptop hides the exact regressions your users feel.

## Code splitting by route

The router is the cleanest split point. Each page pulls only what it needs, and the landing page stops paying for the admin editor.

```ts
const routes = [
    { path: '/', component: () => import('./LandingPage.vue') },
    { path: '/blog/:slug', component: () => import('./ArticlePage.vue') },
];
```

## Memoise the expensive parts

`computed` is enough for most cases. Virtualise only when a list can grow without bound.

```ts
const visible = computed(() => all.value.slice(0, page.value * 20));
```

![Performance budget](/assets/demo/articles/performance.svg)

> [!TRADEOFF]
> Aggressive splitting improves first paint but adds request waterfalls. Group small pages together instead of splitting every component.

## Keep the budget in CI

Fail the pipeline, not the user. A budget that lives in a wiki is a wish; a budget that lives in CI is a guarantee.
