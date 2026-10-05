A bilingual site is not an English site with translations bolted on. Both locales are first-class, and the content model has to make that obvious from the very first migration.

## One entity, many locales

The base table stores locale-independent columns only: slug, publication state and images. Everything a reader actually reads lives in a translation table keyed by `(article_id, locale)`.

| Concern | Base table | Translation table |
| --- | --- | --- |
| Slug | yes | no |
| Title | no | yes |
| Body | no | yes |
| Publication state | yes | no |

> [!IMPORTANT]
> Never put a translatable column on the base table "for convenience". It always becomes the fallback that quietly ships the wrong language.

## Fallbacks are a product decision

If the Russian row is missing, do you hide the article or serve the English one? The answer changes the API contract, so decide it once and encode it in a single resolver instead of scattering `??` through the codebase.

```php
$translation = $article->translationFor($locale)
    ?? $article->translationFor(config('portfolio.fallback_locale'));
```

## The editing experience

A locale tab per language keeps authors honest: they see exactly what is missing instead of a half-filled form that looks complete.

![Bilingual content model](/assets/demo/articles/content-model.svg)

> [!TRADEOFF]
> Translation tables cost an extra join, but they turn "untranslated" into a first-class state rather than a nullable column nobody checks.

## Slug strategy

Slugs are language-independent here, which keeps URLs stable when a translation is added later. Localising slugs is possible, but then every language needs its own redirect history.

## Takeaways

- Keep the base row language-neutral.
- Decide the fallback policy before writing the first query.
- Treat a missing translation as a state, not an error.
