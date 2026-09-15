#!/usr/bin/env bash
#
# Activates one dev-mode's values in the project .env (invoked by `make up` /
# `make up-localhost` and the `setup` targets — see devops.md "Environment
# Setup"). Switching between the localhost and TLS dev modes is therefore a
# single command.
#
# Mode-dependent variables are stored in .env as a pair: the active line and
# the other mode's line commented out, e.g.
#
#   APP_URL=http://localhost:8888
#   # APP_URL=https://portfolio.dev.lan
#
# Managed variables: APP_URL, TRUSTED_PROXIES, PORTFOLIO_DEV_MODE.
#
# Legacy single-value .env files are migrated in place: the first occurrence
# of a managed variable is replaced by the pair, later duplicates are dropped
# and a variable missing entirely is appended. File permissions are preserved.
#
# Usage: switch-env-mode.sh <localhost|tls>

set -euo pipefail

mode="${1:-}"
case "$mode" in
    localhost | tls) ;;
    *)
        echo "usage: $(basename "$0") <localhost|tls>" >&2
        exit 2
        ;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$ROOT/.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "error: $ENV_FILE not found — run 'make setup' / 'make setup-localhost' first" >&2
    exit 1
fi

tmp="$(mktemp "${ENV_FILE}.XXXXXX")"
trap 'rm -f "$tmp"' EXIT

awk -v mode="$mode" '
    function emit(name, localhost_value, tls_value) {
        if (mode == "localhost") {
            print name "=" localhost_value
            print "# " name "=" tls_value
        } else {
            print "# " name "=" localhost_value
            print name "=" tls_value
        }
    }
    /^#?[[:space:]]*APP_URL=/ {
        if (!seen["APP_URL"]++) {
            emit("APP_URL", "http://localhost:8888", "https://portfolio.dev.lan")
        }
        next
    }
    /^#?[[:space:]]*TRUSTED_PROXIES=/ {
        if (!seen["TRUSTED_PROXIES"]++) {
            emit("TRUSTED_PROXIES", "", "*")
        }
        next
    }
    /^#?[[:space:]]*PORTFOLIO_DEV_MODE=/ {
        if (!seen["PORTFOLIO_DEV_MODE"]++) {
            emit("PORTFOLIO_DEV_MODE", "localhost", "tls")
        }
        next
    }
    { print }
    END {
        if (!seen["APP_URL"]) emit("APP_URL", "http://localhost:8888", "https://portfolio.dev.lan")
        if (!seen["TRUSTED_PROXIES"]) emit("TRUSTED_PROXIES", "", "*")
        if (!seen["PORTFOLIO_DEV_MODE"]) emit("PORTFOLIO_DEV_MODE", "localhost", "tls")
    }
' "$ENV_FILE" > "$tmp"

cat "$tmp" > "$ENV_FILE"
