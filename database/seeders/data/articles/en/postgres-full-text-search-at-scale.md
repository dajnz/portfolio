PostgreSQL ships a capable full-text engine. For a portfolio or a small blog it removes the need for a dedicated search service entirely.

## Vectors and dictionaries

A `tsvector` column stores the lexemes of a document. Generated at write time, it becomes the single source of truth for search.

```sql
ALTER TABLE blog_article_translations
ADD COLUMN search_vector tsvector
GENERATED ALWAYS AS (
    to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(content, ''))
) STORED;

CREATE INDEX search_vector_idx
ON blog_article_translations USING gin (search_vector);
```

> [!TIP]
> Use `simple` for mixed-language content. A language-specific dictionary stems the wrong language and produces surprising results.

## Ranking without the noise

Raw `ts_rank` favours long documents. Normalisation divides by document length so short, precise posts can win.

| Normalisation | Effect |
| --- | --- |
| 0 | raw frequency |
| 1 | divide by log length |
| 32 | divide by length + 1 |

## Prefix search for autocomplete

A trailing `:*` turns the last lexeme into a prefix, which is exactly what a search box needs while the user is still typing.

```sql
SELECT slug
FROM blog_article_translations
WHERE search_vector @@ to_tsquery('simple', 'postgr:*');
```

## Highlighting

`ts_headline` produces the snippet with `<mark>` tags. Keep the parameters in one place so every surface highlights identically.

![Full-text search](/assets/demo/articles/search.svg)

> [!WARNING]
> Rebuild the vector whenever the dictionary changes. Existing rows keep the old lexemes until they are reindexed.

## When to reach for a search server

If you need fuzzy matching, per-field boosting at scale, or a relevance team, move on. For everything smaller, Postgres is enough and one less service to operate.
