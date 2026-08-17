# Changelog

All notable changes to the OpenAPI specs that affect the public contract are
documented in this file, following [Keep a Changelog](https://keepachangelog.com/).
Each entry references the new `info.version` (see `docs/detailed-spec/openapi.md`
— Versioning & Deprecation).

Generated API types always match the latest version — there is no multi-version
runtime support.

## [Unreleased]

### Added

- **Blog public pages contract completion (v1.0.17 inertia + v1.0.3 REST, step 9.4, B268):** the public blog surface gains the fields the frontend contracts and the mockup require but the committed schemas lacked: `ArticleCardData.skill_tags` + `ArticleCardData.lead_image_alt` (per-locale map, nullable — the card's `<img>` alt), `ArticleDetailData.skill_tags` (the linked TechSkillTag chips, `{id, name, icon}` — new `SkillTagData` schema, the projects.yaml shape), and `has_projects` on both `BlogIndexPageProps` and `BlogArticlePageProps` (the A10 nav Projects-link condition — the v1.0.16 experience precedent). The REST `ArticleItem` gains the matching `lead_image_alt` (its `skill_tags` was already documented — a latent 8.2 under-delivery the step now fixes in the mapping). The backend `ArticleCardDto` carries `skillTags`/`leadImageAlt`, the public card selects gain `a.lead_image_alt`, and the list/related-card hydration uses the existing `skillTagsFor()` batch read. `info.version` bumped to `1.0.17` in `inertia.yaml` and `1.0.3` in `openapi.yaml`.

- **`projectSortDirection` query parameter + `projectSort` enum narrowed (v1.0.2, step 9.3 rework):** the projects list sort lost the `complexity` strategy (the "most complex" dropdown option was removed per the 2026-08-16 user decision) — `?sort=` now accepts `date` only — and gained the `?direction=asc|desc` parameter (default `desc`; `asc` = "newest last") on the `GET /projects` JSON variant (`listProjectsJson`). The backend's keyset cursor mirrors the direction (the strict seek and the `id` tiebreaker invert for `asc`). `info.version` bumped to `1.0.2` in `openapi.yaml`.

- **`ExperienceTimelinePageProps.has_projects` (v1.0.16, step 9.2):**
  the public experience timeline page now carries whether at least one
  published, non-trashed project exists — the header nav's Projects-link
  condition (A10; the committed page schema carried no published-project
  signal, a gap-fill per user decision 2026-08-15). The value comes from
  a query-builder read in `PublicExperienceController` (the B11
  cross-feature pattern). `info.version` bumped to `1.0.16` in
  `inertia.yaml`.

- **`BlogListPageProps.years` (v1.0.14, step 8.3):** the admin blog
  list now carries the distinct publication years of the published,
  non-trashed articles, newest first — the year filter's dropdown
  options (admin-section-ui.md:390 lists the year filter; the
  committed page schema had no options source, a gap-fill). The
  `years` prop comes from the list query repository's
  `yearsOfPublishedArticles()` (the public index's existing source,
  reused — no new query shape). `info.version` bumped to `1.0.14` in
  `inertia.yaml`.

- **`ArticleTableRowData.lead_image` + `related_count` (v1.0.15, step
  8.3):** the admin blog list row gains the RustFS `lead_image` path
  (the small preview rendered before the title in the Title cell —
  user request 2026-08-13) and the Related column is now the static
  `related_count` integer (the bidirectional `related_articles` row
  count; per-row related management lives on the form page only). The
  former `related` array of `{id, title}` rows is removed from the
  row shape — the edit form keeps its `ArticleEditData.related`. The
  backend now hydrates the count via the list query repository's
  `relatedCountFor()`. `info.version` bumped to `1.0.15` in
  `inertia.yaml`.

### Changed

- **`ArticleDetailData.content` → `ArticleDetailData.content_html` +
  `views_count` + nullable `reactions` (v1.0.13, step 8.2):** the
  public article page serves the body rendered server-side by the
  shared content pipeline (safe-mode CommonMark + callouts + syntax
  highlighting with the `data-code-block`/`data-callout-type` markers,
  content-management.md:466-481, B23) — the raw Markdown is no longer
  exposed. The page additionally carries the pre-increment
  `article_views` counter (content-management.md:217) and the reaction
  state (`{user_reaction, likes, dislikes}`) is now nullable: `null`
  when the `social_reactions` feature flag is disabled (no widget
  rendered, public-section-components.md:1273) or on the signed
  preview (B211). `info.version` bumped to `1.0.13` in `inertia.yaml`.

### Added

- **`ArticleTableRowData.tags` + `ArticleTableRowData.related` (v1.0.12,
  step 8.1):** the admin blog list row now carries the unified tag
  chips (`{id, name, type: "tag"|"skill"}` — the Tags column renders
  them as non-removable badges, admin-section-components.md:1187-1188)
  and the related-article rows (`{id, title}` — the Related column
  chips plus the inline picker source, admin-section-components.md:
  1189-1194). `info.version` bumped to `1.0.12` in `inertia.yaml`.

- **`BlogFormPageProps.tags` + `BlogFormPageProps.articles` +
  `BlogFormPageProps.preview_url` (v1.0.12, step 8.1):** the form page
  now receives the tag picker options (all article tags), the
  published articles (`{id, slug, title}`) of the Related Articles
  multi-select (B188 pattern — only published articles can be linked,
  content-management.md:243) and the signed 1-hour preview URL
  (routes.md:155 — `URL::temporarySignedRoute` on the `blog.preview`
  route; null on the create form). `info.version` bumped to `1.0.12`
  in `inertia.yaml`.

- **`ArticleEditData.is_featured` + `ArticleEditData.skill_tags`
  (v1.0.12, step 8.1):** the edit form needs the featured flag (the
  Featured checkbox) and the attached tech skills (the unified tag
  picker's pills — the committed contract carried only the article
  tags). `info.version` bumped to `1.0.12` in `inertia.yaml`.

- **`TagListPageProps.meta` + `TagTableRowData` (v1.0.12, step 8.1):**
  the tag list is server-side paginated (admin-section-ui.md:317) —
  the page props now carry the cursor pagination `meta`; the rows are
  `TagTableRowData` (TagData + `articles_use_count` — the Article
  Count column and the delete-warning count, admin-section-ui.md:316).
  `info.version` bumped to `1.0.12` in `inertia.yaml`.

- **`TagFormPageProps.tag` → `TagEditData` (v1.0.12, step 8.1):** the
  tag form's LocaleTabs need the per-locale names map (`names`) in
  addition to the resolved display name. `info.version` bumped to
  `1.0.12` in `inertia.yaml`.

- **`ProjectTableRowData.preview_image` + `ProjectTableRowData.tech_tags`
  (v1.0.11, step 7.2):** the admin list row now carries the preview
  image path (the Preview column, admin-section-ui.md:332) and the
  resolved tech tags — the Tags column renders them as removable chips
  and the inline "+" picker attaches more (admin-section-ui.md:343);
  the committed contract previously exposed only `tech_tags_count`.
  `info.version` bumped to `1.0.11` in `inertia.yaml`.

- **`ProjectsListPageProps.featured_projects` (v1.0.11, step 7.2):** the
  list page now receives the featured projects of the live view ordered
  by `featured_sort_order` — the separate SortableList section reorders
  them via `POST /admin/projects/reorder-featured` (admin-section-ui.md:
  338). Empty in the trashed view. `info.version` bumped to `1.0.11` in
  `inertia.yaml`.

- **`ProjectFormPageProps.articles` (v1.0.11, step 7.2):** the form page
  now receives the published blog articles (`{id, slug, title}`) — the
  "Tech Writeup Article" select of the Relations section
  (admin-section-ui.md:356) needs option labels; only published articles
  can be linked (content-management.md:159). Empty when none are
  published. `info.version` bumped to `1.0.11` in `inertia.yaml`.

- **`ProjectDetailData.problem` + `ProjectDetailData.solution` (v1.0.10,
  step 7.1):** the public project detail page renders the Problem and
  Solution sections (public-section-ui.md:194-195) — the committed
  contract did not carry them. Both are nullable Markdown strings,
  rendered via the shared content pipeline (content-management.md:131,
  B23). `info.version` bumped to `1.0.10` in `inertia.yaml`.

- **`ProjectFormPageProps.preview_url` (v1.0.10, step 7.1):** the form
  page now receives the signed 1-hour temporary preview URL (routes.md:
  151 — `URL::temporarySignedRoute` on the `projects.preview` route);
  null on the create form. The preview button of the admin UI opens it
  in a new tab (content-management.md:151). `info.version` bumped to
  `1.0.10` in `inertia.yaml`.

- **`ProjectDetailData.tech_writeup` (v1.0.10, step 7.1):** the public
  project detail page now receives the linked tech-writeup article
  (`{id, slug, title}`) resolved against its CURRENT publication state —
  present only while the article is published, null when no link is set
  or the article is unpublished/trashed (content-management.md:133, the
  link visibility rule was previously unrepresentable in the contract).
  `info.version` bumped to `1.0.10` in `inertia.yaml`.

- **`ProjectEditData` restructured to per-locale maps (v1.0.10, step 7.1):**
  the translatable form fields (`title`, `brief_description`,
  `full_description`, `problem`, `solution`, `preview_image_alt`) are now
  documented as per-locale maps keyed by locale code — the admin form
  edits them under LocaleTabs and the 7.1 controller serves them as
  locale maps (`data-dynamic` keys `project.title.en` etc. in the
  `project-form.html` mockup). `ProjectEditData` no longer reuses
  `ProjectDetailData` (the public shape keeps locale-resolved strings).
  `detail_items` now reference the new `ProjectDetailItemEditData` schema
  with per-locale title/description maps + the reconcile-by-id `id` field
  (A2). `info.version` bumped to `1.0.10` in `inertia.yaml`.

- **`TimelineItemEditData` per-locale maps (v1.0.9, step 6.2):** the
  translatable form fields (`position`, `company_name`, `company_country`,
  `short_description`) are now documented as per-locale maps keyed by
  locale code — the admin form edits them under LocaleTabs and the 6.1
  controller already served them as locale maps (`data-dynamic` keys
  `item.position.en` etc. in the `experience-form.html` mockup). The
  `achievements[].description` field follows the same shape: a per-locale
  map containing only the locales that have a description (the backend
  persists only non-empty locale values to the jsonb column). `info.version`
  bumped to `1.0.9` in `inertia.yaml`.

- **`TimelineItemTableRowData.include_in_resume` (v1.0.8, step 6.1):** the
  admin experience list rows now carry `include_in_resume` (boolean) — the
  DataTable's "Include in Resume" column per `admin-section-components.md`
  (:1063) and the `experience.html` mockup was previously unrepresentable
  in the committed contract. `info.version` bumped to `1.0.8` in
  `inertia.yaml`.

- **Shared prop `supported_locales` (v1.0.7, step 5.2 rework):** the admin
  header's language selector and every per-locale editing surface
  (LocaleTabs) now receive the ACTIVE locales from the `supported_locales`
  setting via the new shared prop — locales disabled on the Settings page
  are no longer offered for translation anywhere in the admin, and their
  existing translations are preserved (the backend syncs stop reconciling
  inactive locales). `info.version` bumped to `1.0.7` in `inertia.yaml`.

### Changed

- **`LoginPageProps.github_oauth_enabled` → `github_login_available` (v1.0.6, step 4.3, B127):**
  the login page's GitHub button is now shown only when the `github_oauth`
  feature flag is ON **and** at least one admin account is linked with GitHub
  (`admins.github_id` set) — the OAuth login flow can only fail otherwise.
  The renamed prop carries the combined condition; `info.version` bumped to
  `1.0.6` in `inertia.yaml`.
### Added

- **Landing props `console_greeting` + `has_more` + `feedback_honeypot_name` (v1.0.5, step 4.1):**
  (1) new `console_greeting` object added to `LandingPageProps` (per
  `public-section-components.md:578-590` + B80 — the data arrives with
  the landing page): `greeting` (localized static text), `name` and
  `position` (locale-resolved about values), `contacts` as
  `{label, value}` pairs; the public layout renders `ConsoleGreeting`
  only when the prop is passed and the `console_greeting` feature flag
  is enabled. (2) new `has_more` boolean — whether more published
  projects exist beyond the featured ones on the page, controlling the
  "View all projects" link (`public-section-components.md:981-985`,
  `content-management.md:116-120`). (3) new `feedback_honeypot_name`
  (nullable string) — the session-derived randomized honeypot field
  name embedded in the page HTML per `authentication.md:345-351`
  (null when no session is active). `info.version` bumped to `1.0.5`
  in `inertia.yaml` (openapi.yaml unaffected). Note: `experience_items`
  (TimelineItemData[]) remains documented but is not provided by 4.1 —
  the landing layout (public-section-ui.md:56-101) defines no experience
  block; the prop is populated or removed by a later step.

- **`recovery_codes_remaining` profile prop (v1.0.4, step 3.6):** added to
  `ProfilePageProps.admin` — count of unused recovery codes (0 when TOTP is
  disabled), rendered by the `TOTPSetup` component
  (`admin-section-components.md:1477-1489`). Optional property; required-set
  unchanged. `info.version` bumped to `1.0.4` in `inertia.yaml`
  (openapi.yaml unaffected).

- **`SeoProps` shared schema (v1.0.3, step 3.5):** new `SeoProps` schema in
  `inertia/shared.yaml` (per `seo.md:18-31`, field names snake_case per the
  Inertia convention) and a `seo` prop added to the six public page schemas:
  `LandingPageProps`, `BlogIndexPageProps`, `BlogArticlePageProps`,
  `ProjectsIndexPageProps`, `ProjectDetailPageProps`, `ExperienceTimelinePageProps`.
  Required set: `title` + `description` (localized server-side). Optional:
  `og_title`, `og_description`, `og_image` (falls back to
  `/images/og-default.png`), `og_image_width` (1200) / `og_image_height` (630),
  `og_url`, `og_type` (website|article), `canonical_url`, `robots`, and
  `json_ld` (server-localized JSON-LD blocks rendered with the CSP nonce by the
  Blade layout and replaced client-side by `useSeoUpdater`). `info.version`
  bumped to `1.0.3` in `inertia.yaml` (openapi.yaml unaffected). Existing
  documented props are unchanged.

- **Inertia shared props (v1.0.2):** `InertiaSharedProps` documents the
  `request_id` UUID prop (per `error-handling.md:266-273`); `AdminUser`
  documents `name` and `is_oauth_primary` (per B3/B27/B35). Both additions
  are optional properties — the required-set is unchanged. `info.version`
  bumped to `1.0.2` in `inertia.yaml` (openapi.yaml unaffected).

### Changed

- **Docs-only completeness pass (v1.0.1):** `info.contact` added to both root
  specs; `info.version` bumped to `1.0.1`. `GET /api/tags/search` and
  `GET /api/blog-tags/search` now document a `429` (rate limit) response (no
  schema changes — reuses `ErrorResponse`). Inertia page operations gained
  `description` fields and admin pages document a `419` (session/CSRF expiry)
  response via `InertiaErrorProps`; the inertia root now defines `servers`
  (placeholder), global `tags`, and the `cookieAuth` security scheme inline.
  The `cookieAuth` scheme moved from `components/security.yaml` into
  `openapi.yaml` (stitched `securitySchemes` maps are not resolvable by
  Spectral).
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
