#!/usr/bin/env bash
#
# Build the ZIP that goes to wordpress.org.
#
# Written down because the thing that got this plugin its first Plugin Check errors
# was not its code — it was shipping the developer's own files alongside it. A build
# that is "zip the folder" is a build that ships whatever happens to be in the folder.
#
# Usage:  bin/build.sh        # writes ../humainbox.zip
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
out="${root}/../humainbox.zip"
stage="$(mktemp -d)/humainbox"

mkdir -p "$stage"

# Everything not named in .distignore. rsync rather than a find pipeline: the
# exclude list is then the same file a reader checks, not a second copy of it.
rsync -a --exclude-from="${root}/.distignore" "${root}/" "${stage}/"

rm -f "$out"
( cd "$(dirname "$stage")" && zip -rq "$out" humainbox -x '*.DS_Store' )

echo "  $(cd "$(dirname "$stage")" && find humainbox -type f | wc -l | tr -d ' ') files"
echo "  $out"
