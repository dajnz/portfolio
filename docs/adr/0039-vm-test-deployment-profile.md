# ADR-0039: VM acceptance-test deployment profile with provided TLS

- **Status:** Accepted
- **Date:** 2026-10-25
- **Source:** VM deployment acceptance test
- **Supersedes:** —

## Context

Testing the production workflow against a local VM cannot obtain a Let's
Encrypt certificate (no public DNS, and the test hostname is not resolvable by
the ACME server). The deployment must still exercise the real stack, the
Swarm orchestration, and nginx's HTTPS path without weakening the production
certbot flow or letting a test deployment reuse production secrets.

## Decision

- The playbook gains `deploy_profile`:

  | profile | env file | TLS mode |
  |---|---|---|
  | `production` (default) | `/opt/portfolio/.env.prod` | certbot service + `certbot_certs` volume (unchanged) |
  | `vm-test` | `/opt/portfolio/.env.prod.test` | operator-provided files, certbot scaled to zero |

  `make deploy-test` selects `vm-test`; `make deploy` pins `production`. The
  env hash file is profile-specific (`.env.sha256.test`) so the two never
  collide on one VM.
- `secrets/init.sh` accepts the plaintext and encrypted paths plus the
  profile; `make secrets-init-test` writes `.env.prod.test.local` /
  `.env.prod.test.age`. The test profile pre-fills dummy GitHub/mail values
  and sets `MAIL_MAILER=log`; `DOMAIN` remains gated so the operator must set
  the certificate hostname.
- Provided TLS is a strict contract:
  `/opt/portfolio/tls/live/<DOMAIN>/fullchain.pem` and `privkey.pem`. After
  the first-deploy env provisioning (so `DOMAIN` is known) and before any
  clone or build, the playbook asserts both files exist and that the
  certificate matches the domain (`openssl x509 -checkhost`).
- `devops/deploy/docker-compose.provided-tls.yml` replaces nginx's
  `/etc/letsencrypt` named volume with a read-only bind mount of
  `/opt/portfolio/tls` (same target — verified with `docker stack config`) and
  sets `certbot` to zero replicas. `deploy-stack.sh` accepts an optional sixth
  argument and passes it as an additional `-c` file. Nginx's entrypoint already
  enables the HTTPS block when the certificate files are present, so no
  entrypoint or config change is needed.
- The production profile never includes the overlay: `.env.prod`, the
  certbot_certs volume, and the renewal loop remain exactly as in ADR-0028.

## Consequences

- A missing or mismatched certificate fails the test deployment before images
  are built, with the expected paths in the message.
- The test VM builds images against the test env, so its registry entries are
  not interchangeable with production images.
- Certificate rotation in the test profile is a file replacement plus
  `docker service update --force portfolio_nginx`; certificates are not
  renewed automatically.
- One profile runs per VM at a time: the stack name, release root, and ports
  are shared.
