#!/usr/bin/env python3
"""Prune stale image tags from the local Docker registry.

usage: registry-prune.py <deployed_tags_json> <manifest_json>

For every portfolio service repository (portfolio-<service>) on the local
registry at 127.0.0.1:5000, deletes every tag except the current manifest
tag and the previously deployed tag (kept so `docker service rollback` can
still start from it). Tags are deleted by manifest digest via the registry
v2 API (registries >= 2.8 reject tag-reference deletes) and the number of
deletions is reported on stdout as "deleted=N" — the caller runs `registry
garbage-collect` when N > 0.

Uses only the Python standard library. Failures to list a repository or to
delete an individual tag are reported as warnings and never abort the
prune of the remaining repositories.
"""

import json
import sys
import urllib.error
import urllib.request

REGISTRY_BASE = "http://127.0.0.1:5000"
# Modern docker pushes OCI manifests by default; advertise every media type
# the registry may have stored (OCI + docker schema2, single + list/index)
# or the HEAD is rejected with MANIFEST_UNKNOWN.
ACCEPT_MANIFEST = {
    "Accept": ", ".join(
        [
            "application/vnd.oci.image.manifest.v1+json",
            "application/vnd.oci.image.index.v1+json",
            "application/vnd.docker.distribution.manifest.v2+json",
            "application/vnd.docker.distribution.manifest.list.v2+json",
        ]
    )
}


def main() -> int:
    if len(sys.argv) != 3:
        print(f"usage: {sys.argv[0]} <deployed_tags_json> <manifest_json>", file=sys.stderr)
        return 2

    deployed = json.loads(sys.argv[1])
    manifest = json.loads(sys.argv[2])
    deleted = 0

    for service in sorted(manifest):
        repo = "portfolio-" + service
        keep = {
            tag for tag in (manifest[service], deployed.get(service) or "") if tag
        }

        try:
            with urllib.request.urlopen(
                f"{REGISTRY_BASE}/v2/{repo}/tags/list", timeout=10
            ) as response:
                tags = json.load(response).get("tags") or []
        except urllib.error.HTTPError as error:
            if error.code == 404:
                print(f"{repo}: no tags yet")
            else:
                print(f"warning: {repo}: cannot list tags: {error}")
            continue
        except Exception as error:
            print(f"warning: {repo}: cannot list tags: {error}")
            continue

        for tag in sorted(tags):
            if tag in keep:
                continue
            try:
                request = urllib.request.Request(
                    f"{REGISTRY_BASE}/v2/{repo}/manifests/{tag}",
                    method="HEAD",
                    headers=ACCEPT_MANIFEST,
                )
                with urllib.request.urlopen(request, timeout=10) as response:
                    digest = response.headers.get("Docker-Content-Digest")
                if not digest:
                    print(f"warning: {repo}:{tag}: no digest header, skipping")
                    continue
                request = urllib.request.Request(
                    f"{REGISTRY_BASE}/v2/{repo}/manifests/{digest}", method="DELETE"
                )
                with urllib.request.urlopen(request, timeout=10):
                    pass
                deleted += 1
                print(f"deleted {repo}:{tag}")
            except Exception as error:
                print(f"warning: {repo}:{tag}: {error}")

    print(f"deleted={deleted}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
