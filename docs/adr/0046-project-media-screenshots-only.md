# ADR-0046: Project media — screenshots as the single media concept

- **Status:** Accepted
- **Date:** 2026-11-18
- **Source:** review of the project preview-image usage (preview image only appeared as a fallback on `/projects`, as the landing card image and as `og:image`)
- **Supersedes:** —

## Context

Projects carried two media concepts: `projects.preview_image` (+ the per-locale `preview_image_alt` map) and the `project_screenshots` gallery. The preview image was only rendered when a project had no screenshots on the projects index, was the only image the landing page could use (its read model did not fetch screenshots), and supplied the project `og:image`. On the project detail page it was passed but never displayed. Maintaining a second upload field, alt map, contract fields, cleanup paths and validation for such a narrow fallback was not justified.

## Decision

- Screenshots are the project's only media source. The `projects.preview_image` and `preview_image_alt` columns are dropped.
- Cover image = the first screenshot by `(sort_order, id)`. There is no cover column and no `is_cover` flag; the admin drag-to-reorder order decides it, and the screenshots area carries a localized note that the first screenshot is the card cover and social image.
- Public cards keep using the full screenshot list (the carousel); a project without screenshots shows the existing placeholder. The landing page renders the derived cover as a static image (no carousel) to keep the lightweight featured-project read model.
- `og:image` for project detail and signed preview is the derived cover. The landing card alt stays the project title; cards and the detail gallery keep using the screenshot `title` as the alt text.
- Read contracts expose the derived `cover_image` only where the consumer cannot read the screenshot list: `ProjectTableRowData`, `FeaturedProjectData` and the REST `ProjectItem`. `ProjectCardData` receives screenshots and needs no cover field; `ProjectDetailData` and `ProjectEditData` lost the preview fields.
- The migration converts every preview image of a project **without screenshots** into a screenshot row (`title` = the old alt map, `sort_order` = 0) before dropping the columns. When a project had both, the curated preview is dropped and its RustFS object is left to `app:cleanup-orphaned-files`.
- The demo seeder follows the same rule: the existing demo preview SVGs become single screenshot rows for the projects that had no gallery; the redundant previews of projects that already had screenshots are removed.

## Consequences

- Social previews now show the first gallery screenshot. A 16:9 screenshot is cropped to the 1.91:1 social frame, so the admin guidance recommends 16:9 at least 1280×720 and a centered composition; an optional social-only `og_image` override remains the fallback path if the quality becomes insufficient.
- New projects can be published without any image and fall back to the placeholder; no validation enforces a minimum of one screenshot.
- Pre-change backups contain the dropped columns in their table dumps and cannot be restored verbatim after the schema change; take a fresh backup after deploying this change.
- The landing read model gains one batched query for the featured projects' first screenshots, mirroring the existing tech-tags batch.
