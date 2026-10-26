#!/usr/bin/env bash
# provision-rustfs.sh <network> <env_file> <policy_file> [endpoint]
#
# Creates the RustFS buckets (portfolio public-read, backups private) and the
# backups IAM user with the bucket-scoped policy at <policy_file>. The official
# RustFS `rc` client talks to the RustFS Admin API; the whole sequence runs in
# one throwaway container on <network>.
#
# Idempotent: buckets use --ignore-existing, and the backups user is recreated
# on every run because RustFS does not support detaching an IAM policy. That
# also resets the user's secret to the value in <env_file> and guarantees the
# user carries only the scoped policy (no stale broad readwrite attachment).
#
# <endpoint> defaults to RUSTFS_ENDPOINT from the env file (http://rustfs:9000
# for dev/prod; http://localhost:9000 for CI with `host` networking). The
# script retries for up to 180 seconds so it can run right after a stack deploy.
set -euo pipefail

if [ "$#" -lt 3 ] || [ "$#" -gt 4 ]; then
    echo "usage: provision-rustfs.sh <network> <env_file> <policy_file> [endpoint]" >&2
    exit 2
fi

network="$1"
env_file="$2"
policy_source="$3"
endpoint_override="${4:-}"
rc_image="rustfs/rc:v0.1.36"

if [ ! -f "$env_file" ]; then
    echo "ERROR: env file not found: $env_file" >&2
    exit 1
fi
if [ ! -f "$policy_source" ]; then
    echo "ERROR: policy file not found: $policy_source" >&2
    exit 1
fi

set -a
. "$env_file"
set +a

endpoint="${endpoint_override:-${RUSTFS_ENDPOINT:-http://rustfs:9000}}"
policy_file="$(realpath "$policy_source")"

if [ -z "${RUSTFS_ACCESS_KEY:-}" ] || [ -z "${RUSTFS_SECRET_KEY:-}" ]; then
    echo "ERROR: RUSTFS_ACCESS_KEY/RUSTFS_SECRET_KEY missing from $env_file" >&2
    exit 1
fi
if [ -z "${RUSTFS_BACKUPS_ACCESS_KEY:-}" ] || [ -z "${RUSTFS_BACKUPS_SECRET_KEY:-}" ]; then
    echo "ERROR: RUSTFS_BACKUPS_* missing from $env_file" >&2
    exit 1
fi
if [ "$RUSTFS_BACKUPS_ACCESS_KEY" = "$RUSTFS_ACCESS_KEY" ]; then
    echo "ERROR: RUSTFS_BACKUPS_ACCESS_KEY must differ from RUSTFS_ACCESS_KEY" >&2
    exit 1
fi

if ! docker image inspect "$rc_image" >/dev/null 2>&1; then
    if ! docker pull -q "$rc_image" >/dev/null; then
        echo "ERROR: cannot pull $rc_image" >&2
        exit 1
    fi
fi

provision() {
    docker run --rm --network "$network" \
        -v "$policy_file:/policies/backups-rw.json:ro" \
        -e RC_ENDPOINT="$endpoint" \
        -e RC_KEY="$RUSTFS_ACCESS_KEY" \
        -e RC_SECRET="$RUSTFS_SECRET_KEY" \
        -e RC_BACKUP_KEY="$RUSTFS_BACKUPS_ACCESS_KEY" \
        -e RC_BACKUP_SECRET="$RUSTFS_BACKUPS_SECRET_KEY" \
        --entrypoint sh \
        "$rc_image" -c '
            set -e
            rc alias set local "$RC_ENDPOINT" "$RC_KEY" "$RC_SECRET" >/dev/null

            rc bucket create local/portfolio --ignore-existing
            rc bucket create local/backups --ignore-existing
            rc bucket anonymous set download local/portfolio

            rc admin user rm local "$RC_BACKUP_KEY" >/dev/null 2>&1 || true
            rc admin user add local "$RC_BACKUP_KEY" "$RC_BACKUP_SECRET"

            rc admin policy rm local backups-rw >/dev/null 2>&1 || true
            rc admin policy create local backups-rw /policies/backups-rw.json
            rc admin policy attach local backups-rw --user "$RC_BACKUP_KEY"
        '
}

deadline=$(( $(date +%s) + 180 ))
until provision; do
    if [ "$(date +%s)" -ge "$deadline" ]; then
        echo "ERROR: RustFS provisioning failed (is rustfs reachable on $network?)" >&2
        exit 1
    fi
    sleep 3
done

echo "RustFS buckets, public portfolio policy, and backups user are ready"
