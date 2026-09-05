#!/usr/bin/env bash
# shred-files.sh [path_or_glob ...]
#
# Securely removes files (shred -u, falling back to plain rm when shred is
# unavailable). Arguments may be glob patterns; patterns without matches and
# empty arguments are ignored, so the script is safe to call with the
# identity-file placeholder even when it was never created. Exits 0 when no
# file was matched.
set -euo pipefail

for pattern in "$@"; do
    [ -n "$pattern" ] || continue
    for file in $pattern; do
        [ -e "$file" ] || continue
        shred -u "$file" 2>/dev/null || rm -f "$file"
        echo "shredded $file"
    done
done
