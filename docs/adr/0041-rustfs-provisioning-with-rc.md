# ADR-0041: RustFS provisioning with the official rc client

- **Status:** Accepted
- **Date:** 2026-10-26
- **Source:** RustFS provisioning review
- **Supersedes:** —
- **Amends:** ADR-0027

## Context

Dev setup and CI provisioned RustFS with the MinIO `mc` client. The official
`minio/mc` images became unavailable (Docker Hub denied, Quay 401), and a
third-party legacy mirror is not a durable dependency. CI switched to the AWS
CLI for buckets, but that is S3-only and cannot manage RustFS IAM users; the
deployment playbook did not provision RustFS at all, so a fresh server had no
`portfolio`/`backups` buckets and no separate backups user.

RustFS ships an official CLI, `rc` (`rustfs/rc`), which targets the RustFS Admin
API rather than MinIO admin endpoints. It covers buckets, anonymous access,
IAM users, policies, and policy attachments, with an official multi-arch image
and versioned releases.

## Decision

- Provision RustFS with `rustfs/rc:v0.1.36` (pinned) in **dev, deployment, and
  CI**. The MinIO `mc` image and the AWS CLI bucket workaround are removed.
- `devops/deploy/scripts/provision-rustfs.sh <network> <env_file>
  <policy_file> [endpoint]` runs one throwaway `rc` container on the given
  network:
  - creates `portfolio` and `backups` (`--ignore-existing`);
  - sets anonymous `download` on `portfolio` (public assets via nginx) and
    leaves `backups` private;
  - recreates the backups user and attaches `backups-rw`;
  - defaults the endpoint to `RUSTFS_ENDPOINT` from the env file and retries
    for up to 180 seconds so it can run immediately after a stack deploy.
- Least privilege: `devops/deploy/rustfs/backups-rw.json` grants only
  `GetObject`, `PutObject`, `DeleteObject`, and `ListBucket` on `backups`.
  Dev, CI, and production all use a backups access key distinct from the root
  key; the script rejects equal keys.
- RustFS does not advertise `admin.iam.policy-detach`, so the backups user is
  removed and recreated on every run. This converges policy attachments (the
  old broad `readwrite` cannot survive) and resets the secret to the env value.
- The deployment playbook runs the script after convergence, before recording
  the env hash, on `portfolio_prod`. CI runs it on `host` with
  `http://localhost:9000`; its `RUSTFS_BACKUPS_*` keys are `rustfsbackups`
  rather than the root credentials, exercising the scoped policy in the e2e
  backup suite.

## Consequences

- One vendor-supported tool provisions RustFS everywhere; no dependency on
  MinIO images or the MinIO Admin API.
- A provisioning failure now fails `make setup`/the deploy instead of silently
  seeding without buckets or serving a site without assets.
- The backups user is briefly absent while it is recreated during setup/deploy;
  backup jobs must not run in that window. Recreating the same access key also
  resets its secret to the env file.
- `rc` is 0.x: the version is pinned and upgrades are deliberate. The image is
  official, multi-arch, and open source; if it ever becomes unavailable, the
  CLI can be built from source (`rustfs-cli`) or installed from a release
  binary.
