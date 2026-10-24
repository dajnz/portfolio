# ADR-0021: Uploaded image pipeline & assets

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B15, B248, B249, B314
- **Supersedes:** —

## Context

The public site needs branded, self-hosted image assets (logo, section favicons, social preview), and the admin panel accepts image uploads that must be normalized and stored consistently. Decisions were needed on how source assets are generated and committed and how uploaded bytes are processed.

## Decision

- `public/images/og-default.png` (1200×630) is a committed asset and the default OG image.
- Asset preparation runs via `make pictures` → `devops/assets/prepare-images.mjs` (node; `svgo` + `sharp` devDependencies). It optimizes every `resources/images/*` into `public/images/` (SVG via SVGO, rasters via sharp re-encode) and generates the section favicons from `logo-favicon.svg`: `favicon-{public,admin}.svg` plus `favicon-{public,admin}-{32,180}.png`.
- The pipeline is deterministic and idempotent; files that live only in `public/images/` (e.g. `og-default.png`) are left untouched. Generated assets are committed (repo convention, like generated TS types).
- The Blade layout selects the favicon by path prefix (`admin` → `favicon-admin`, otherwise public).
- Header logo links render `logo-full-{dark|light}.svg` (alt = the logo text / admin section title); the login card renders `logo-iconic-{dark|light}.svg` (default when no `logo_url`, h-16). Variants resolve through the reactive themeStore hydrated from the shared `theme` prop, so SSR renders the correct variant and ThemeSwitch swaps it client-side.
- `POST /api/upload` runs a synchronous WebP pipeline for JPEG/PNG/WebP/AVIF uploads using Intervention Image ^4.3 with the GD driver: EXIF auto-orientation at decode, downscale of the largest side to the `image_max_dimension` setting, then WebP encode at quality 85.
- `image_max_dimension` is an integer setting (default 2048 from `config/portfolio.php`, UI bounds 512–4096) exposed as `SettingName::ImageMaxDimension`, with a seeder default and the General tab's new **Content settings** group.
- GD writes no metadata chunks, so EXIF/IPTC/XMP are stripped by the re-encode (no separate strip step). Decode failures return 422 "The file must be a valid image." on `file`.
- Stored file name is `{random(40)}.webp`; `name` keeps the original client filename; `size` records the stored (processed) bytes.
- Processing is synchronous (uploads are admin-only, ≤ 3072 KB, single variant). GIFs pass through unprocessed (animation + metadata preserved); animated WebP/AVIF flatten to a single frame (accepted, no animation pipeline).
- AVIF input stays accepted: both Docker images compile GD `--with-avif` (libavif-dev) and install `exif`; production php.ini `memory_limit` is raised to 256M for in-memory decode of max-size uploads.
- Existing stored images are NOT re-processed — the pipeline applies to new uploads only (backup restore mirrors objects verbatim).
- Uses plain `intervention/image` (no image-laravel facade); `Shared/Infra/Services/UploadedImageProcessor` instantiates `ImageManager(GdDriver)` and is bound in AppServiceProvider.

## Consequences

- Changing source images requires re-running `make pictures` and committing the regenerated outputs.
- AVIF/EXIF support adds libavif-dev and exif to both Docker images; the 256M memory limit is a runtime requirement.
- Legacy uploads keep their pre-pipeline bytes; no backfill or migration exists.
- Synchronous processing keeps the upload contract simple but adds request-time CPU cost, bounded by the 3072 KB cap.
