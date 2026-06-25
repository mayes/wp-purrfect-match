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

VERSION="$(grep -m1 'Stable tag:' readme.txt | sed 's/.*:[[:space:]]*//')"
OUT="dist"
mkdir -p "$OUT"
rm -f "${OUT}/${SLUG}.zip"

# Archive HEAD with a top-level <slug>/ folder (how WordPress expects a plugin
# ZIP to unpack), applying .gitattributes export-ignore.
git archive --format=zip --prefix="${SLUG}/" -o "${OUT}/${SLUG}.zip" HEAD

echo "Built ${OUT}/${SLUG}.zip (version ${VERSION:-unknown})"
echo "--- contents ---"
unzip -l "${OUT}/${SLUG}.zip" | awk 'NR>3 && $4 {print $4}'
