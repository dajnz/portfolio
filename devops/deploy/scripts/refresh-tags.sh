#!/usr/bin/env bash
# refresh-tags.sh <release_dir>
#
# Mirrors the remote's tags into an existing release clone. clone-release.sh
# fetches a single ref into FETCH_HEAD and creates no tag refs, so the tags
# pointing at the deployed commit stay invisible to build-php-images.sh
# without this. Idempotent and safe against remotes without tags, so the
# playbook runs it on every deploy rather than only on a fresh clone.
set -euo pipefail

if [ "$#" -ne 1 ]; then
    echo "usage: refresh-tags.sh <release_dir>" >&2
    exit 2
fi

release_dir="$1"

if [ ! -d "$release_dir/.git" ]; then
    echo "not a git checkout: $release_dir" >&2
    exit 1
fi

git -C "$release_dir" fetch --depth 1 --quiet --tags origin
