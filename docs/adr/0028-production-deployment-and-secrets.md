# ADR-0028: Production deployment & secrets

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B320, B321
- **Supersedes:** —

## Context

Production runs as a Docker Swarm stack deployed by Ansible. The PHP image bakes a config cache, while `docker stack deploy` ignores `env_file`, so a decision was needed on how runtime configuration and secrets reach the services.

## Decision

- Runtime config reaches PHP services through the **image build**: the PHP image bakes `config:cache` at build time (env() values frozen) and the swarm `app`/`queue`/`scheduler` services carry no DB/Redis/RustFS/APP_KEY env. `deploy.yml` sources `/opt/portfolio/.env.prod` into every PHP-image `docker build`.
- Env-only changes (no tag change) are detected via `/opt/portfolio/.env.sha256` and trigger a rebuild plus `docker service update --force` on the three PHP services.
- `.env.prod.local` is built from the devops Production overrides table plus prod values mirroring dev naming (`DB_HOST=db`, `DB_DATABASE=portfolio`, `DB_USERNAME=portfolio`, `REDIS_HOST=redis`, `RUSTFS_*` endpoints/buckets, `INERTIA_SSR_URL=http://node:3000`, `MAIL_MAILER=api`, `TRUSTED_PROXIES` empty). `APP_URL` and `GITHUB_REDIRECT_URI` are derived from `DOMAIN`; the file must stay `set -a; .`-sourceable.
- `GITHUB_CLIENT_SECRET` is generated as a random placeholder (the validation gate rejects only empty/`CHANGE_ME` values, so it cannot force replacement; the file carries a comment telling the admin to replace it). `.env.prod.local`, `.env.prod.age` and `age-identity.txt` are never committed.
- `GIT_REPO` supplies the clone URL and fails fast when unset.
- `envsubst` is targeted: `deploy.yml` seds the four `${VAR:-default}` forms to plain names, then substitutes an explicit 11-variable list (plain envsubst corrupts the compose `$${...}` healthcheck escapes); bash is not required on the server.
- Registry pruning deletes by digest via the v2 API (registry ≥ 2.8 rejects tag-reference deletes) with an inline stdlib-python task, then runs `registry garbage-collect`; the Accept header lists OCI manifest+index and docker schema2 manifest+list.
- `deploy.yml`/`rollback.yml` run as root (`become`), require git/gettext-base/age/curl (Ubuntu/apt) and pass `ansible-playbook --syntax-check`.
- The manifest is bootstrapped with the current main short SHA for all five services; the CI `update-manifest` job refines it on push.
- Deploy/rollback bash + python snippets live in `devops/deploy/scripts/` (`clone-release.sh`, `build-php-images.sh`, `deploy-stack.sh`, `wait-for-convergence.sh`, `shred-files.sh`, `registry-prune.py`) and are invoked via the `script:` module; `deploy-stack.sh` supports a `COMPOSE_OUTPUT` override for testability.
- Postgres/Redis/RustFS are stateful and not in the manifest; upgrades use `docker service update --update-order stop-first` (start-first + shared-volume singleton), and Postgres major versions follow the backup → `pg_dumpall` → scale 0 → new `pgdataNN` volume → deploy → restore runbook with rollback via the untouched old volume.

## Consequences

- Baked secrets live only in the host-local registry, the same trust domain as the server's plaintext `.env.prod`.
- Deleting a manifest by digest removes all tags pointing at that digest — production-safe because every build tag has a unique digest; a duplicate stale tag only surfaces a warning on the second DELETE.
