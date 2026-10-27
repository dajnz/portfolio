#!/usr/bin/env bash
# Load the target SSH key into ssh-agent before Ansible connects.
# usage: ensure-ssh-key.sh <host_vars_file>
set -euo pipefail

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

HOST_VARS="${1:-}"
[ -n "$HOST_VARS" ] || die "usage: ensure-ssh-key.sh <host_vars_file>"
[ -f "$HOST_VARS" ] || die "$HOST_VARS not found — run the matching deploy-init target first"

KEY="$(sed -n 's/^deploy_server_ssh_key_path:[[:space:]]*//p' "$HOST_VARS" | head -n 1 | sed 's/[[:space:]]*$//')"
KEY="${KEY%\"}"
KEY="${KEY#\"}"
KEY="${KEY%\'}"
KEY="${KEY#\'}"
KEY="${KEY/#\~/$HOME}"

[ -n "$KEY" ] || die "deploy_server_ssh_key_path is missing in $HOST_VARS"
[ -f "$KEY" ] || die "SSH key not found at $KEY"

[ -n "${SSH_AUTH_SOCK:-}" ] || die "no ssh-agent is running — start one (for example: eval \"\$(ssh-agent -s)\")"

if [ -f "$KEY.pub" ]; then
    FINGERPRINT="$(ssh-keygen -lf "$KEY.pub" 2>/dev/null | awk '{print $2}')"
    if [ -n "$FINGERPRINT" ] && ssh-add -l 2>/dev/null | grep -qF "$FINGERPRINT"; then
        exit 0
    fi
fi

printf '[deploy] loading %s into ssh-agent (passphrase prompt follows)\n' "$KEY"
ssh-add "$KEY" || die "could not load $KEY into ssh-agent"
