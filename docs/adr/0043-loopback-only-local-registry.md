# ADR-0043: Loopback-only local registry container

- **Status:** Accepted
- **Date:** 2026-10-27
- **Source:** Deployment registry exposure review
- **Supersedes:** —
- **Amends:** ADR-0040 (registry deployment and firewall bullet)

## Context

ADR-0040 deployed the local registry as a Swarm service with
`--publish published=5000,target=5000,mode=host`. Swarm host-mode publishing
binds `0.0.0.0:5000` (and `[::]:5000`); Swarm's `--publish` has no host-IP
option, so loopback-only publishing was not possible with the service.
The registry is plain HTTP and unauthenticated. Leaving it reachable from other
hosts allows image poisoning: the deployment pulls
`127.0.0.1:5000/portfolio-*:<tag>` from the same registry.

The deployment is single-node: builds and pushes run on the manager against
`127.0.0.1:5000`, and the stack's image references use the same loopback
address. A loopback-only registry therefore satisfies every consumer.

## Decision

- Run `registry:2` as a normal container named `registry`, with
  `--restart unless-stopped`, published only on `127.0.0.1:5000:5000`, and the
  `/opt/registry/data` bind mount.
- The deploy preflight creates the container when missing, starts it when
  stopped, and waits for `GET http://127.0.0.1:5000/v2/` to return 200. It does
  not create or probe a Swarm service.
- Registry garbage collection targets the container (`docker exec registry …`).
- No automatic migration from the old service is included. A host that still
  runs the legacy `registry` Swarm service must remove it
  (`docker service rm registry`) or be wiped before using this playbook; the
  data remains in `/opt/registry/data`.

## Consequences

- Port 5000 is not reachable from other hosts; no firewall rule is required for
  it. The operator-managed firewall requirement for port 5000 from ADR-0040 is
  retired.
- The registry is not rescheduled by Swarm. The `unless-stopped` restart policy
  covers crashes and host reboots, which matches the single-node assumption.
- The registry no longer appears in `docker service ls`; preflight, rollback,
  and convergence tooling operate on `portfolio_*` services only.
- The registry container and its `registry:2` image remain outside the stack;
  `docker stack deploy` does not remove them.
