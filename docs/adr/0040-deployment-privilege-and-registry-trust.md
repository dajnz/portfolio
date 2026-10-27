# ADR-0040: Deployment privilege model and local registry trust

- **Status:** Accepted
- **Date:** 2026-10-25
- **Source:** Deployment prerequisite review
- **Supersedes:** —
- **Amended by:** ADR-0043 (the local registry runs as a loopback-only
  container; the host-mode publish and firewall bullet below are superseded)

## Context

The deployment playbook configures the target with `become: true`, so it needs
root on the production server and on the VM test target. Requiring passwordless
sudo on a production host is undesirable, yet `make deploy` previously passed no
`--ask-become-pass`, which turned a normal sudo user into a confusing "Missing
sudo password" failure.

The image builds push to the local `registry:2` service on
`127.0.0.1:5000` over plain HTTP. Docker currently marks `127.0.0.0/8` as
insecure automatically, but its documentation says not to rely on that
behaviour. The daemon configuration was previously a manual prerequisite.
Docker itself remains a user-provided dependency: the playbook installs its
small tooling (git, envsubst, age, curl, openssl) but must not install or
upgrade Docker Engine.

## Decision

- `make deploy`, `make deploy-test`, `make rollback`, and `make rollback-test`
  pass `--ask-become-pass` by default. Ansible asks once per run and reuses the
  password for every privileged task; no sudoers changes are required. Set
  `ASK_BECOME=0` for a target whose user has passwordless sudo.
- A preflight task checks that Docker Engine is installed, running, and at
  least version 23. It fails with an actionable message when not; it never
  installs Docker.
- The same preflight ensures `127.0.0.1:5000` is present in the daemon's
  `insecure-registries`: it merges `/etc/docker/daemon.json` (preserving all
  other keys), writes it root-owned with mode `0644`, and live-reloads the
  daemon with SIGHUP. `insecure-registries` is one of the daemon options that
  supports live reload, so no containers are restarted.
- After the reload the playbook verifies that the running daemon reports
  `127.0.0.1:5000`. If it does not, the deploy stops with an instruction to
  restart Docker during a maintenance window; the playbook never restarts a
  live daemon on its own.
- The host firewall stays operator-managed. The registry uses a host-mode
  publish and therefore binds `0.0.0.0:5000`; the runbook documents the
  firewall rule.

## Consequences

- A deployment now asks for the sudo password once (plus the SSH key
  passphrase when the key is not in `ssh-agent`); production and the VM test
  target behave identically.
- Passwordless sudo is optional rather than a prerequisite, which keeps the
  production privilege model reasonable.
- Docker Engine 23+ is an explicit prerequisite; a missing or older engine
  fails before the clone and any image build.
- `/etc/docker/daemon.json` may be modified on the first deploy (other keys are
  preserved) and is live-reloaded without restarting containers.
- Registry trust no longer relies on Docker's loopback default, and the
  operator remains responsible for restricting port 5000 at the firewall.
