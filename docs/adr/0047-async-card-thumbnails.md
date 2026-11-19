# ADR-0047: Async card thumbnails for project screenshots and article lead images

- **Status:** Accepted
- **Date:** 2026-11-19
- **Source:** project media review — a project may carry tens of screenshots and the site tens of projects; the index carousels fetch the full-size images (the first slide eagerly, the remaining slides within the browser's lazy-loading margin, and every slide of a visible card over time via the 5 s auto-advance), so the card payload grew with the gallery size.
- **Supersedes:** amends the "thumbnails are not built" consequence of ADR-0021; relates to ADR-0046 (the first screenshot is the project cover and social image) and ADR-0045 (public page performance).

## Context

Since ADR-0046 a project's only media is its screenshot gallery, and the first screenshot by `(sort_order, id)` doubles as the card cover and the social image. The cards render that gallery through a horizontal carousel. The markup marks the first slide eager (`fetchpriority="high"`) and the rest `loading="lazy"`, but Chrome's lazy loading is a distance heuristic, not a visibility rule: every slide of a typical carousel sits inside the fetching margin and is requested right after the card loads (verified on the dev instance — with the auto-advance stubbed out and the cache ignored, all 13 card images of the first page were fetched within ~2.8 s). The 5 s auto-advance then pulls every slide of every visible card anyway. With uploads normalized to a 2048 px longest side at q85, a ten-screenshot card costs multiple megabytes.

The specs previously listed thumbnails as an explicit non-goal ("Not built (unchanged non-goals): thumbnails, responsive `<srcset>`/`<picture>` variants, quality presets beyond the single 85% WebP encode"). The measurement above makes that non-goal untenable for card-sized renders.

## Decision

- **Two card variants per stored image**: 480×270 (1x) and 960×540 (2x), a 16:9 centre cover crop — the same framing the cards already produce with `object-fit: cover` — encoded as metadata-free WebP at quality 80. Sources smaller than a box keep their own width (never upscaled); both files are always written so the `srcset` never references a missing object. GIF sources keep the current animated full-size fallback.
- **Deterministic paths**: `{dir}/{basename}.thumb.webp` and `{dir}/{basename}.thumb@2x.webp` next to the source (`ThumbnailPath`). Only the 1x path is stored; the 2x path is derived.
- **Readiness signal**: nullable `thumbnail_path` on `project_screenshots` and `blog_articles`. Null means "not generated yet" — cards fall back to the full-size image. Both objects are written before the column is set.
- **Generation is asynchronous**: `GenerateScreenshotThumbnailJob` / `GenerateArticleThumbnailJob` (queued, `ShouldBeUnique` per row, `afterCommit`, three tries) delegate to the app use cases. The store/update use cases enqueue only for new or replaced images — a reorder or a text-only edit enqueues nothing. Missing rows, missing objects and undecodable sources (including a stray SVG path) are logged and skipped, leaving the fallback intact.
- **The job cannot write a stale path**: the row update is guarded by the source path that was processed, and replacing an image nulls the column in the same save, so the card shows the new full image instead of the previous image's thumbnail while the job runs.
- **Deletion has two layers**: the existing delete flows (replace, force-delete, trash prune) delete the derived pair explicitly; the monthly `app:cleanup-orphaned-files` sweep also claims the derived paths and the recorded column values, so objects left by a crashed job are swept and objects left by a crash between the write and the column update are preserved for the backfill (`DatabaseAssetPathProvider` derives them from every referenced source).
- **Backfill**: `app:generate-thumbnails [--force] [--sync]` queues the generation for the rows without a thumbnail (all rows with `--force`), with `--sync` running inline. The command is also the post-deploy step for content uploaded before this change.
- **Demo data**: the committed demo artwork is WebP — the superseded SVG sources were removed once the seed data switched to the raster files; the seeder provisions those files and runs the backfill inline after seeding.
- **Cards only**: the projects index carousel, the landing featured cards, the blog index cards, the related-article cards and the admin list/grid previews render `srcset` with `1x`/`2x` descriptors. The project detail gallery, the lightbox, the article hero, `og:image`, the RSS enclosure and the JSON-LD image keep the full-size object — social previews must stay above the recommended OG size.
- **Configuration, not a setting**: `portfolio.thumbnail` holds the box and quality; there is no admin control.

## Consequences

- A queue worker is required: thumbnails appear after the job completes (cards fall back meanwhile), and the job flushes the public page cache so the cached props pick the pair up before the TTL expires.
- Like any new binding/job, the feature only reaches an already-running worker after a restart: `php artisan queue:restart` in a deployed environment, `docker compose restart queue` (and `scheduler`) in local development — `queue:work` boots the container once per process, so a worker started before the deploy resolves the new interfaces as "not instantiable".
- Storage grows by two derived objects per image. They are an order of magnitude smaller than the 2048 px sources, but the assets bucket and the backup archives include them; older backups restore rows with a null `thumbnail_path` (fallback plus a backfill run).
- Normal deletions remove the pair immediately; an orphaned pair waits for the monthly sweep (or a manual `app:cleanup-orphaned-files --dry-run`).
- The demo artwork is committed as WebP only: changing a demo illustration means replacing the committed `.webp` files (they are the seeder's source now) and re-running the backfill.
- Upload validation still rejects SVG (`mimes:jpeg,png,gif,webp,avif` — SVG can carry scripts and bypasses server-side image validation), so no SVG-specific thumbnail path exists.
- A single card-sized `srcset` pair is the full extent of responsive imagery — general `srcset`/`<picture>` variant sets and additional quality presets remain non-goals.
- Existing content needs `app:generate-thumbnails` after the deploy; until then every card renders its full-size image.
