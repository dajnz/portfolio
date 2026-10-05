Redis is not a database replacement. Used deliberately, it removes the repeated work that dominates read-heavy applications.

## Cache-aside for reads

Check the cache, fall back to the database, then populate. Keys carry a TTL so a missed invalidation self-heals.

```php
$article = Cache::remember(
    "article:{$slug}",
    now()->addMinutes(10),
    fn () => $repository->findBySlug($slug),
);
```

> [!IMPORTANT]
> Cache-aside is safe only if a stale read is acceptable. For anything money-related, read through to the source.

## Counters with atomic increments

View counts and reactions must not be read-modify-write. Redis increments are atomic and cheap.

| Operation | Command |
| --- | --- |
| increment | `INCR` |
| unique visitor | `PFADD` |
| top N | `ZINCRBY` |

## Sorted sets for leaderboards

A sorted set keeps the ranking ordered as it is updated, so "top 10" is a single range query.

![Caching layers](/assets/demo/articles/caching.svg)

> [!WARNING]
> Large sets and wildcard key scans can block the single-threaded server. Prefer explicit keys and bounded collections.

## Plan the invalidation

For every key, know the event that removes it. If there is no such event, you have built a stale-data generator.
