# Changelog

All notable changes to the OpenAPI specs that affect the public contract are
documented in this file, following [Keep a Changelog](https://keepachangelog.com/).
Each entry references the new `info.version` (see `docs/detailed-spec/openapi.md`
— Versioning & Deprecation).

Generated API types always match the latest version — there is no multi-version
runtime support.

## [Unreleased]

### Changed

- **Dual-response filter parameters:** `GET /blog` now documents `article_tags`,
  `skill_tags`, and `year` query parameters; `GET /projects` documents `tags`
  (AND/intersection semantics). The obsolete `tag` (`tagFilter`) parameter was
  removed from `components/parameters.yaml`.
- **Inertia page schemas restructured:** all page schemas now extend the
  `InertiaPage` wrapper (`{ component, props, url?, version? }`) with shared
  props nested inside `props`, matching the Inertia protocol payload shape.
- **Component namespaces corrected:** every Inertia component uses
  `{Feature}/Public/Pages/...` or `{Feature}/Admin/Pages/...` (no `UI` namespace).
- **Create/edit form pages merged** into single form components per feature:
  `BlogFormPage`, `ProjectFormPage`, `ExperienceFormPage` (matching `routes.md`).
- **Preview routes moved** to `/admin/preview/blog/{article}` and
  `/admin/preview/projects/{project}` (signed URLs), rendering the public pages.
- **Analytics report types** enum corrected to `[overall, blog, projects, feedback]`;
  **backup status** enum extended with `processing`.
- **Field renames (snake_case):** `featuredProjects` → `featured_projects`,
  `experienceItems` → `experience_items`, `skillTags` → `skill_tags`,
  `hasMore`/`nextCursor` → `has_more`/`next_cursor`, `detailItems` → `detail_items`,
  `githubOAuthEnabled` → `github_oauth_enabled`, `userReaction` → `user_reaction`,
  `encryptionKeyPrompt` → `encryption_key_prompt`.
- **Schema corrections vs. the database spec:** removed unbacked fields
  (`meta_description`, `AboutData.content`, `ContactData.type`); added
  `AboutData.name` + `relocation_readiness`; `ProjectDetailData.content` →
  `full_description`; `tech_writeup` → `tech_writeup_article_id` (integer);
  achievements `project_url` → `project_link`; added
  `sort_order`/`include_in_resume` to `TimelineItemEditData` and
  `skills[].include_in_resume` to `SkillGroupEditData`.
- **Auth pages:** removed the standalone `GET /admin/profile/totp-enable` page
  (TOTP enrollment lives on the profile page); added
  `GET /admin/profile/github-link/totp` (GitHub link TOTP confirmation).
- **New pages documented:** `ResumeEditPage` (`inertia/resume.yaml`),
  `SkillsListPage` (`inertia/techskills.yaml`), `FeedbackDetailPage`,
  `TagFormPage` (create/edit tag pages).
- **Feature flags endpoint added:** `PATCH /admin/settings/feature-flags`
  in `inertia/settings.yaml`.
- **Inertia error responses** are now proper Response Objects
  (description + content) referencing `InertiaErrorProps`; error component is
  `Shared/CrossSurface/Pages/ErrorPage`.

### Added

- `docs/openapi/inertia/techskills.yaml`, `docs/openapi/inertia/resume.yaml`.
- This changelog.

### Removed

- `components/parameters.yaml` `tagFilter` parameter (replaced by
  `article_tags`/`skill_tags`/`tags`).
- Inertia `PreviewPage`/`ProjectPreviewPage` page schemas and the
  `/admin/{feature}/{id}/preview` paths (replaced by signed-URL preview paths).
