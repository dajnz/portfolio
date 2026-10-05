"Add a cache" is not a strategy. There are four layers, each solving a different problem, and mixing them up is how stale data reaches users.

## HTTP cache

The cheapest request is the one that never reaches the server. Immutable assets can be cached for a year; HTML needs revalidation.

| Resource | Policy |
| --- | --- |
| Hashed assets | `max-age=31536000, immutable` |
| HTML | `no-cache` |
| API reads | short `max-age`, revalidate |

> [!IMPORTANT]
> Only cache what you can invalidate. If you cannot name the event that clears it, do not add the layer.

## Application cache

Expensive computations land in Redis with an explicit key and TTL. Keys derived from content hash invalidate themselves when the content changes.

```php
$key = "article:html:{$id}:{$locale}:{$contentHash}";
Cache::put($key, $html, now()->addWeek());
```

## Query cache

Short-lived, request-scoped caches remove duplicate queries inside a single request. They must never outlive the request.

![Caching layers](/assets/demo/articles/caching.svg)

> [!WARNING]
> A cache without a TTL is a memory leak with extra steps.

## Invalidation beats caching

Design the invalidation first. The cache itself is the easy part.
