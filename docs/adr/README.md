# Architecture Decision Records

The durable architecture decisions behind this project. Each ADR is deliberately
short: it records the context, the decision and its consequences so a change can
be made without re-litigating old trade-offs.

Source code and the OpenAPI specs (`docs/openapi/`) reference these as
`ADR-XXXX`. When you touch an area, read its ADR first.

## Index

| ADR | Title |
|---|---|
| [0001](0001-modular-monolith-and-layering.md) | Modular monolith, feature slicing & layering rules |
| [0002](0002-domain-modeling-conventions.md) | Domain modeling, entities, value objects & DTOs |
| [0003](0003-cross-feature-read-contracts.md) | Cross-feature reads through Shared contracts |
| [0004](0004-openapi-contract-workflow.md) | OpenAPI contract workflow & generated TypeScript types |
| [0005](0005-reorder-and-reconciliation-payloads.md) | Reorder & content-reconciliation payloads |
| [0006](0006-slug-policy-and-old-slugs.md) | Slug policy, old-slug 301s & trash occupancy |
| [0007](0007-full-text-and-tag-search.md) | Full-text & tag search |
| [0008](0008-markdown-content-pipeline.md) | Shared Markdown content pipeline & highlighting |
| [0009](0009-i18n-and-localization.md) | i18n & localization |
| [0010](0010-cursor-pagination-and-infinite-scroll.md) | Cursor pagination & infinite scroll |
| [0011](0011-settings-and-feature-flags.md) | Settings & feature flags |
| [0012](0012-background-jobs-schedules-and-retention.md) | Background jobs, schedules & retention |
| [0013](0013-auth-and-admin-session-architecture.md) | Authentication & admin session architecture |
| [0014](0014-totp-recovery-codes-and-lockout.md) | TOTP recovery codes & lockout policy |
| [0015](0015-error-handling-and-response-mapping.md) | Error handling & response mapping |
| [0016](0016-api-based-mail-transport.md) | API-based mail transport |
| [0017](0017-analytics-metrics-reports-and-dashboard.md) | Analytics: privacy, metrics, reports & dashboard |
| [0018](0018-backup-and-restore-architecture.md) | Backup & restore architecture |
| [0019](0019-backup-admin-ui-and-disaster-recovery-import.md) | Backup admin UI & disaster-recovery import |
| [0020](0020-resume-generation-and-caching.md) | Resume generation & caching |
| [0021](0021-uploaded-image-pipeline.md) | Uploaded image pipeline & assets |
| [0022](0022-seo-sitemap-feeds-and-ssr-head.md) | SEO, sitemap, feeds & SSR head |
| [0023](0023-frontend-structure-aliases-icons-and-tokens.md) | Frontend structure, aliases, icons & tokens |
| [0024](0024-admin-ui-interaction-conventions.md) | Admin UI interaction conventions |
| [0025](0025-public-page-ux-conventions.md) | Public page UX conventions |
| [0026](0026-client-state-cookies-ssr-theming-and-csp.md) | Client state, cookies, SSR theming & CSP |
| [0027](0027-local-dev-environment-and-onboarding.md) | Local development environment & onboarding |
| [0028](0028-production-deployment-and-secrets.md) | Production deployment & secrets |
| [0029](0029-testing-strategy-and-fixtures.md) | Testing strategy, fixtures & seeding |
| [0030](0030-platform-and-toolchain-constraints.md) | Platform & toolchain constraints |
| [0031](0031-public-page-ux-conventions-carousel-revision.md) | Public page UX conventions (carousel navigation revision) |
| [0032](0032-use-case-composition-and-capability-services.md) | Use-case composition & capability services |
| [0033](0033-console-commands-and-queued-jobs-as-entry-points.md) | Console commands & queued jobs as thin entry points |
| [0034](0034-node-package-management-with-pnpm.md) | Node package management with pnpm |
| [0035](0035-local-git-hooks-with-lefthook.md) | Local git hooks with lefthook |
| [0036](0036-session-fingerprint-binding.md) | Session fingerprint binding & fixation hardening |
| [0037](0037-time-storage-and-display-timezones.md) | Time storage and display timezones |
| [0038](0038-deployment-image-build-correctness.md) | Deployment image build correctness |
| [0039](0039-vm-test-deployment-profile.md) | VM acceptance-test deployment profile with provided TLS |
| [0040](0040-deployment-privilege-and-registry-trust.md) | Deployment privilege model and local registry trust |
| [0041](0041-rustfs-provisioning-with-rc.md) | RustFS provisioning with the official rc client |
| [0042](0042-ansible-deployment-target-config.md) | Ansible-native deployment target configuration and AGE prompt |
| [0043](0043-loopback-only-local-registry.md) | Loopback-only local registry container |
| [0044](0044-first-deploy-admin-bootstrap.md) | First-deploy admin bootstrap |

## Format

Each ADR records its `Status`, `Date`, `Source` (the originating planning item),
`Supersedes`, then `Context` / `Decision` / `Consequences`. When a decision
changes, add a new ADR and mark the old one `Superseded by ADR-XXXX` rather than
rewriting history.

`Source` keeps the original planning-item IDs for traceability; the plan itself
is not part of this repository. Some records also mention local-only planning or
mockup files — the decision text is self-contained.
