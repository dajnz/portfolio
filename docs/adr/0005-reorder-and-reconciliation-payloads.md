# ADR-0005: Reorder & content-reconciliation payloads

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A2, A4
- **Supersedes:** —

## Context

Admin list sections need drag-reorder, and repeating child collections (screenshots, engineering detail items) need bulk editing. A single payload convention was needed so frontend drag state and backend persistence agree without per-endpoint special cases.

## Decision

- **Reconciliation (A2):** screenshot and detail-item collections reconcile by `id` — rows with existing IDs are updated; entries without an `id` are created; DB rows absent from the submitted array are deleted, and deleted screenshot files are hard-deleted from RustFS.
- **Simple reorder (A4):** simple lists (contacts, skill groups) send `{ "ids": [...] }`; the backend assigns zero-indexed `sort_order` in array order.
- **Skill reorder (A4):** skills send `{ "skills": [{ "id", "skill_group_id" }] }` in drag order; the backend writes both `sort_order` and the group assignment.

## Consequences

- Clients must preserve existing `id`s when editing child rows; a stored row submitted without an `id` is interpreted as a create.
- Reorder endpoints are whole-collection replacements — any id missing from `ids`/`skills` is dropped.
- Screenshot deletion permanently removes files from RustFS, so an accidental omission is destructive.
