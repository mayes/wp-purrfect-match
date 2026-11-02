#!/usr/bin/env bash
#
# Build a distributable plugin ZIP that contains only the files WordPress needs.
#
# It archives the committed HEAD and honors the `export-ignore` rules in
# .gitattributes, so dev-only files (tools/, examples/, README.md, bin/, …) are
# automatically left out. Output: dist/purrfect-match.zip
#
set -euo pipefail

SLUG="purrfect-match"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# git archive packages committed HEAD, so refuse a misleading release when
# the working tree (tracked or new files, outside dist/) differs from it.
if [ -n "$(git status --porcelain --untracked-files=normal -- . ':(exclude)dist')" ]; then
  echo "Refusing to build: commit or stash changes (including new files) first." >&2
  exit 1
fi

# The three version strings must agree, or WordPress shows one version while
# the readme advertises another.
VERSION="$(git show HEAD:readme.txt | { grep -m1 'Stable tag:' || true; } | sed 's/.*:[[:space:]]*//' | tr -d '[:space:]')"
HEADER_VERSION="$(git show HEAD:purrfect-match.php | sed -n 's/^ \* Version:[[:space:]]*//p' | head -n1 | tr -d '[:space:]')"
CONST_VERSION="$(git show HEAD:purrfect-match.php | sed -n "s/.*PURRFECT_MATCH_VERSION', '\([^']*\)'.*/\1/p" | head -n1)"
if [ -z "$VERSION" ] || [ "$VERSION" != "$HEADER_VERSION" ] || [ "$VERSION" != "$CONST_VERSION" ]; then
  echo "Refusing to build: versions disagree (readme ${VERSION:-?}, header ${HEADER_VERSION:-?}, constant ${CONST_VERSION:-?})." >&2
  exit 1
fi

OUT="dist"
mkdir -p "$OUT"
rm -f "${OUT}/${SLUG}.zip"

# Archive HEAD with a top-level <slug>/ folder (how WordPress expects a plugin
# ZIP to unpack), applying .gitattributes export-ignore.
git archive --format=zip --prefix="${SLUG}/" -o "${OUT}/${SLUG}.zip" HEAD

echo "Built ${OUT}/${SLUG}.zip (version ${VERSION})"
echo "--- contents ---"
unzip -Z1 "${OUT}/${SLUG}.zip"
