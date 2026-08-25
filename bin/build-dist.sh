#!/usr/bin/env bash
#
# Builds the distributable plugin in dist/banners-og, which is what
# WordPress.org receives and what plugin-check should be pointed at.

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
target="${root}/dist/banners-og"

# rsync --delete instead of rm -rf: keeps the directory inode, so a rebuild is
# safe while something else (a bind mount, an editor) is holding it open.
mkdir -p "${target}"

excludes=()
while IFS= read -r line; do
    [[ -z "${line}" || "${line}" == \#* ]] && continue
    excludes+=( "--exclude=${line}" )
done < "${root}/.distignore"

# --delete-excluded, not just --delete: plain --delete protects excluded files
# already sitting in the target, so a newly excluded path would linger forever.
rsync -a --delete --delete-excluded "${excludes[@]}" "${root}/" "${target}/"

echo "Built ${target}"
