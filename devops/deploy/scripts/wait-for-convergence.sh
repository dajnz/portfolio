#!/usr/bin/env bash
# wait-for-convergence.sh <timeout_seconds> [service_name ...]
#
# Waits until every given service (default: all portfolio_* services of the
# stack) reports current == desired replicas and its newest task is Running.
# Services scaled to zero (e.g. certbot in the provided-TLS profile) count as
# converged.
# Prints a service table and exits 1 when the deadline passes. Used by both
# deploy.yml (whole stack after `docker stack deploy`) and rollback.yml (per
# service after `docker service rollback`).
set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "usage: wait-for-convergence.sh <timeout_seconds> [service_name ...]" >&2
    exit 2
fi

timeout_seconds="$1"
shift

if [ "$#" -gt 0 ]; then
    services=("$@")
else
    mapfile -t services < <(docker service ls --filter name=portfolio_ --format '{{.Name}}')
fi

if [ "${#services[@]}" -eq 0 ]; then
    echo "no services to wait for" >&2
    exit 1
fi

deadline=$(( $(date +%s) + timeout_seconds ))
while [ "$(date +%s)" -lt "$deadline" ]; do
    pending=""
    for svc in "${services[@]}"; do
        replicas=$(docker service ls --filter name="$svc" --format '{{.Replicas}}' 2>/dev/null || true)
        if [ -z "$replicas" ]; then
            echo "service $svc not found" >&2
            exit 1
        fi
        case "$replicas" in
            "0/0") continue ;;
            *"/0" | "0/"*) pending="$pending $svc($replicas)"; continue ;;
        esac
        if [ "${replicas%%/*}" != "${replicas##*/}" ]; then
            pending="$pending $svc($replicas)"
            continue
        fi
        state=$(docker service ps "$svc" --format '{{.CurrentState}}' 2>/dev/null | head -n 1 || true)
        case "$state" in
            Running) ;;
            *) pending="$pending $svc($state)" ;;
        esac
    done
    if [ -z "$pending" ]; then
        echo "all services converged"
        exit 0
    fi
    sleep 10
done

echo "ERROR: services did not converge within ${timeout_seconds}s:" >&2
docker service ls --filter name=portfolio_ >&2
exit 1
