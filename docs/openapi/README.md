# OpenAPI Spec — Authoring Guide

These specs define the **contract** for all JSON API endpoints. The implementation
must conform to what's written here, not the other way around.

## Structure

```
docs/openapi/
├── openapi.yaml           # Root — info, servers, tags, paths ($ref stitching)
├── components/
│   ├── schemas.yaml       # Shared DTOs, error envelopes, pagination
│   └── parameters.yaml    # Shared query/path/header parameters
├── features/
│   ├── portfolio.yaml     # Feedback endpoint
│   ├── blog.yaml          # Reactions, tags
│   ├── analytics.yaml     # Event tracking
│   ├── shared.yaml        # File uploads, skill search
│   ├── resume.yaml        # Resume PDF regeneration
│   └── dual-response.yaml # GET /blog, GET /projects (JSON)
├── inertia/               # Inertia.js page-props contracts (separate spec)
│   ├── inertia.yaml       # Root — Inertia page response contracts
│   ├── auth.yaml          # Login, TOTP verification, profile pages
│   ├── blog.yaml          # Blog post list/detail/form/tag pages
│   ├── portfolio.yaml     # Landing, admin, about, contacts pages
│   ├── projects.yaml      # Project list/detail pages
│   ├── experience.yaml    # Experience timeline pages
│   ├── techskills.yaml    # Skills management page
│   ├── resume.yaml        # Resume settings page
│   ├── analytics.yaml     # Analytics dashboard pages
│   ├── backup.yaml        # Backup management pages
│   ├── settings.yaml      # Settings pages
│   └── shared.yaml        # Shared Inertia props (notifications, user)
└── README.md              # This file
```

## Workflow: Adding a New Endpoint

1. **Spec first** — write the path + operation + schema in the feature file
2. **Validate** — run `pnpm exec spectral lint docs/openapi/openapi.yaml`
3. **Generate TS types** — `pnpm run generate-api-types` (if configured)
4. **Implement** — build the controller/use case to match the spec
5. **Verify** — the dev validation middleware warns on mismatches at runtime

## Conventions

- **Feature files** map to `app/Features/{Feature}/`:
  - `portfolio.yaml` → `app/Features/Portfolio/`
  - `blog.yaml` → `app/Features/Blog/`
  - `resume.yaml` → `app/Features/Resume/`
  - etc.
- **All `$ref` paths are relative** to the file they appear in.
- **Response codes** follow the project's error handling conventions
  (see `docs/detailed-spec/error-handling.md`).
- **Schemas** that are shared across 2+ feature files go in `components/schemas.yaml`.
- **Inertia specs** (`inertia/`) define internal page-props contracts for Inertia.js
  routes (not a public API). They follow the same `$ref` conventions as the public
  spec but describe the shape of props passed from Laravel controllers to Vue page
  components.

## Validation

```bash
# Public JSON API spec
docker compose exec dev pnpm exec spectral lint docs/openapi/openapi.yaml

# Inertia page-props spec
docker compose exec dev pnpm exec spectral lint docs/openapi/inertia/inertia.yaml
```

A CI step runs the same checks on every PR.
