# ADR-0020: Resume generation & caching

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A6, B275, B276, B277, B278, B279, B280, B281, B282
- **Supersedes:** —

## Context

The resume is a generated PDF served publicly at `/cv` and edited in admin, drawing on content from several features and cached for performance. Decisions were needed on serving, cache keying/invalidation, layout ordering, and the admin editing semantics.

## Decision

- **Serving (A6, B277):** `/cv` honors a `?locale=` query param that overrides the cookie for that request only (used by the admin "Download preview" new-tab link). The PDF is served inline (`Content-Disposition: inline; filename="resume.pdf"`, `application/pdf`) via `Storage::disk('rustfs')->response()`; a missing config or manual file returns 404.
- **Cache (B276):** keys are `resume:pdf:{locale}:{hash}`, stored in the dedicated `Cache::store('redis')` regardless of the default store. Invalidation runs through `ResumePdfInvalidationProvider` via table-based wildcard listeners (`eloquent.{saved,deleted,restored}: *`, guarded on the six source tables). Batched reorder writes (`EloquentContactRepository::reorder`, `EloquentTechSkillWriteRepository` `reorderSkills`/`batchUpdate`) bump `updated_at` explicitly so the content hash rotates.
- **Mode & controllers (B278):** the mode is the validated `auto`|`manual` string on the `ResumeConfig` entity (no enum), exposed via `isAuto()`/`isManual()`. `GetResumeForEditUseCase` returns `ResumeEditDataDto` and `UpdateResumeConfigUseCase` accepts the request-validated mode string, keeping `ResumeController` domain-free; `PublicResumeController` uses the `App` facade for locale.
- **DTOs (B278):** `ResumePdfDto`/`ResumeEditDataDto` use readonly public properties (per the DTO convention).
- **Layout order (B275, B279):** the five-section default layout order is normalized to 0–4 (matching the `between:0,4` validation and the plan), used by the `ResumeController` fallback and `E2ETestSeeder`. `ResumeEditPageProps` carries `included_skills_count`/`included_experience_count`/`included_contacts_count` (skills/contacts with `include_in_resume = true`; experience with the flag and `deleted_at IS NULL`) and the typed `layout` (`ResumeLayout`/`ResumeSectionConfig`: template enum + visible/order 0–4). Counts come from `ResumeDataReaderInterface::inclusionCounts()`.
- **Admin auto-save (B280):** auto-saves use `PUT /admin/resume` (routes.md defines only PUT, no PATCH). Auto-save payloads omit `about_text` so stored texts are untouched while the textarea keeps the user's unsaved edits. A failed switch to manual without a file keeps the user's selection, shows the field error + an error toast, and defers persistence to Save after the upload.
- **Section editor (B280, B281):** the arrows swap order values with the adjacent visible neighbour (hidden sections snap to the bottom and cannot be reordered); native drag-and-drop live-shifts a ghosted drop-position preview, and any reorder rewrites the five order values to their display positions (a unique 0–4 permutation). Toggling visibility keeps the order value.
- **Rendering (B282):** templates render sections in layout order — modern/compact use `@foreach ($order as $section)`, classic honors the order within its fixed two columns. The content hash covers the layout JSON, so a reorder rotates the cache key and regenerates the PDF.
- **Rendering options:** the layout JSON also carries `skills_layout` (`per_line`|`inline`), `contacts_show_heading` (bool), `contacts_layout` (`per_line`|`two_columns`) and `experience_show_skills` (bool), normalized to defaults for rows stored before the options existed. All templates share `partials/skills.blade.php` and `partials/contacts.blade.php`; the skills option applies to every template (compact included) and inline skills omit proficiency labels. Two-column contacts fill row by row, left to right.
- **Classic pagination:** dompdf does not split table rows across pages, so the classic two-column body uses an absolutely positioned sidebar with an indented main column rather than a single-row table (the table version clipped long resumes and produced blank pages; a float is also unsuitable because dompdf subtracts the float width on top of the main column's margin, halving it).

## Consequences

- Any new source table feeding the resume must be added to the invalidation guard, or the cached PDF goes stale.
- Redis is required for the resume cache even when the default cache store differs.
- New resume edits must respect the auto-save omitted-`about_text` contract to avoid clobbering stored translations, and the layout must stay a unique 0–4 permutation.
