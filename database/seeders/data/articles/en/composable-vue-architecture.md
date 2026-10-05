A large Vue component is usually three responsibilities wearing one file: fetching, state and presentation. Composables let you separate them without inventing a store.

## Split by responsibility

Each composable owns one concern and exposes a narrow, typed surface. The component becomes a thin template over them.

```ts
export function useArticle(slug: Ref<string>) {
    const article = ref<Article | null>(null);
    const loading = ref(false);

    watchEffect(async () => {
        loading.value = true;
        article.value = await fetchArticle(slug.value);
        loading.value = false;
    });

    return { article, loading };
}
```

> [!TIP]
> If a composable returns more than a handful of refs, it is doing too much. Split it.

## Test the logic, not the DOM

Composables are plain functions. Vitest can exercise them without mounting a component.

| Layer | Tested with |
| --- | --- |
| Composables | Vitest |
| Components | Vitest + Testing Library |
| Pages | Playwright |

## Composable or store?

Use a store only when state is genuinely shared across unrelated routes. Local state belongs in a composable.

> [!TRADEOFF]
> Composables scatter state compared to a single store. The payoff is that each piece can be understood in isolation.

## Naming is the API

`useArticle`, `useArticleFilters`, `useArticleReactions` describe exactly what they own. A composable named `useData` owns nothing.
