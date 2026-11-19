#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
dev_exec="$repo_root/devops/scripts/dev-exec.sh"

lint_range() {
    "$dev_exec" pnpm exec commitlint --from "$1" --to "$2"
}

lint_single() {
    git log -1 --format=%B "$1" | "$dev_exec" pnpm exec commitlint
}

status=0
while read -r _local_ref local_sha _remote_ref remote_sha; do
    [[ "$local_sha" =~ ^0+$ ]] && continue

    if [[ "$remote_sha" =~ ^0+$ ]]; then
        base="$(git rev-list "$local_sha" --not --remotes | tail -n 1)"
        [[ -z "$base" ]] && continue

        if ! parent="$(git rev-parse --verify -q "$base^")"; then
            lint_single "$base" || status=1
            continue
        fi

        lint_range "$parent" "$local_sha" || status=1
        continue
    fi

    lint_range "$remote_sha" "$local_sha" || status=1
done

exit "$status"
