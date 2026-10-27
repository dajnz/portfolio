<p align="center">
  <img
    src="docs/readme/assets/main-page-preview.webp"
    alt="Portfolio landing page with the hero section, skills and featured projects"
    width="100%"
  >
</p>

# Portfolio

[![CI](https://github.com/dajnz/portfolio/actions/workflows/ci.yml/badge.svg)](https://github.com/dajnz/portfolio/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**English** | [Русский](docs/readme/README.ru.md)

A personal portfolio and blog platform: a bilingual public site (English and
Russian) with a custom admin panel for content, analytics, resume generation and
backups. Made using agent-based development with manual reviews.

> The project is being prepared for its first public release; there is no live
> deployment yet.

## Features

### Public site

- Landing page with a hero section, skills, featured projects, latest articles
  and contacts.
- Project catalogue with detail pages, tech writeups and related skills.
- Experience timeline.
- Blog with full-text search, tag filtering, related articles, reactions, a
  table of contents and an RSS feed.
- Generated resume PDF at `/cv`, rendered from the same content as the site.
- English and Russian routes (`/` and `/ru`), with a dark/light theme.
- SEO: localized meta tags, JSON-LD, hreflang alternates, a sitemap and
  `robots.txt`.
- Privacy-conscious analytics with hashed IP addresses.

### Admin

- Session authentication with TOTP, recovery codes and optional GitHub OAuth.
- CRUD for blog articles, tags, projects, experience, skills and contacts, plus
  feedback triage.
- Bulk actions, soft deletes and restores, drag-and-drop reordering and signed
  previews.
- Markdown editor with image uploads and automatic WebP conversion.
- Analytics dashboard with daily metrics, charts and rankings.
- Resume editor with a preview link and cached PDF generation.
- Encrypted database and asset backups, restore and disaster-recovery import.
- Settings and feature flags.

## Tech stack

| Layer | Technologies |
| --- | --- |
| Backend | PHP 8.5, Laravel 13, PostgreSQL 18, Redis 8 |
| Frontend | Vue 3, Inertia.js 3, TypeScript, Vite 8, Tailwind CSS 4 (admin), BEM + scoped CSS (public) |
| Content | CommonMark, Shiki and highlight.php, Intervention Image, S3-compatible RustFS |
| Infrastructure | Docker Compose, nginx, Docker Swarm, Ansible |
| Quality | Pest 4, Vitest 4, Playwright, Biome, ESLint, Stylelint, Pint, Spectral, Lighthouse CI |

## Architecture

The application is a modular monolith. Each feature lives under
`app/Features/{Feature}/` and is split into `Domain`, `App` and `Infra` layers.
The domain layer is framework-free, and cross-feature reads go through `Shared`
contracts.

The HTTP surface is documented OpenAPI-first in [`docs/openapi`](docs/openapi/README.md):
the specs are the source of truth and the TypeScript types are generated from
them. Public pages are Inertia + Vue with server-side rendering. Durable
architecture decisions are recorded as [ADRs](docs/adr/README.md).

## Quick start

**Requirements**:
- Docker Engine with the Compose plugin;
- GNU Make (for running stuff);
- tested only on Ubuntu 24.04.

Services like PHP, Node, PostgreSQL, Redis and the S3-compatible storage all run in containers.

**Important note**: Docker should be configured to be run without SUDO permissions.
To do this, use the following snippet:

```bash
# Adds current user to docker group, this is kind of OK-ish for local-dev only.
# Not for production servers, since has security implications.
sudo usermod -aG docker $USER
# Starts a new shell with a different primary group (in this case, the docker group)
newgrp docker
```

```bash
git clone https://github.com/dajnz/portfolio.git
cd portfolio
make setup
```

`make setup` builds the containers, installs PHP and Node dependencies, builds
the front-end, runs migrations, seeds demo content, creates the initial admin
account (it prompts for credentials) and creates the test database.

- Public site: <http://localhost:8888>
- Admin panel: <http://localhost:8888/admin>

The default setup uses localhost mode and needs no DNS or reverse proxy. A TLS
mode with Traefik is available through `make up` for hosts that have
`portfolio.dev.lan` configured.
Configuring local environment with TLS support is not a part of this project.

The seeders create fictional demo content; replace it from the admin panel.

## Common commands

| Command | Description |
| --- | --- |
| `make up-localhost` | Start the localhost stack at <http://localhost:8888> |
| `make down-localhost` | Stop and remove the localhost stack |
| `make seed-fresh` | Recreate the database, seed demo content and the admin |
| `make admin-create` | Create an admin account |
| `make test` | Run the Pest and Vitest suites |
| `make test-e2e` | Run the Playwright end-to-end suite |
| `make coverage` | Run both suites with the 80% backend coverage threshold |
| `make lint` | Run Pint, Biome, ESLint, Stylelint and Spectral |
| `make typecheck` | Generate the API types and run `vue-tsc` |
| `make lighthouse` | Check the Lighthouse budgets on six public routes |
| `make open-dev` | Open a shell inside the dev container |

## Testing

- Backend tests run against a real PostgreSQL database, never SQLite.
- `make test` runs Pest and Vitest; `make test-e2e` runs Playwright against an
  isolated database and application instance.
- `make coverage` enforces an 80% backend line-coverage threshold.
- CI mirrors the local targets: lint, typecheck, backend and frontend tests,
  end-to-end tests and Lighthouse budgets.

## Project layout

```text
app/Features/       Feature modules (Domain / App / Infra)
docs/openapi/       OpenAPI and Inertia page-prop contracts
docs/adr/           Architecture decision records
docs/readme/        Translated README and its assets
resources/js/       Vue 3 + TypeScript frontend
resources/css/      Public design tokens and BEM styles
devops/             Docker images, deployment and tooling
tests/              Pest, Vitest and Playwright suites
```

## Deployment

Deployment is manual and runs outside CI. CI only maintains
`devops/deploy/manifest.yml`, the per-service image-tag manifest: on every push
to `main` that passes the fast test jobs, a bot recomputes the tags and commits
the manifest.

Because that manifest commit lands after the code commit, deploy from `main`
(the tip includes the manifest) or create the release tag after the manifest
job has finished:

```bash
make deploy GIT_REF=main
```

Production runs as a Docker Swarm stack provisioned with Ansible. Secrets are
kept out of the repository and provisioned on the host. The `main` branch is
protected by a ruleset that requires the CI status checks.

## Contributing

This is a personal project, so feature development is owner-driven. Bug reports
and focused fixes are welcome — please open an issue first for anything
non-trivial.

## Acknowledgements

Built with [Laravel](https://laravel.com/), [Vue](https://vuejs.org/) and
[Inertia.js](https://inertiajs.com/). Inter and JetBrains Mono are served through
[Bunny Fonts](https://fonts.bunny.net/) under the SIL Open Font License; icons
come from the Heroicons, Lucide, Simple Icons and Devicon sets.

## License

[MIT](LICENSE) © 2026 Vladimir Ponomarev.
