#!/usr/bin/env bash
# clone-release.sh <repo> <dest> <ref>
#
# Shallow-clones the given git ref (branch, tag or commit SHA) into a
# dedicated release directory. Plain `git clone --depth 1` cannot fetch an
# arbitrary SHA at depth 1, so the clone is emulated with init + fetch:
# the fetch pins exactly the requested ref and the checkout detaches HEAD
# at FETCH_HEAD. Any pre-existing directory at <dest> is removed first —
# the playbook only calls this when the release dir does not exist (or is
# incomplete, i.e. has no .git), so wiping is safe.
set -euo pipefail

if [ "$#" -ne 3 ]; then
    echo "usage: clone-release.sh <repo> <dest> <ref>" >&2
    exit 2
fi

repo="$1"
dest="$2"
ref="$3"

rm -rf "$dest"
git init --quiet "$dest"
git -C "$dest" remote add origin "$repo"
git -C "$dest" fetch --depth 1 --quiet origin "$ref"
git -C "$dest" checkout --quiet --detach FETCH_HEAD

echo "cloned $repo at $ref -> $dest"
