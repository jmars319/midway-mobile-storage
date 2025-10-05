#!/usr/bin/env bash
# scripts/check-no-inline-hex.sh
# Simple grep-based check to fail if non-token hex color literals are present
# in PHP/HTML/JS files. Excludes vendor and the canonical stylesheet(s).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EXCLUDES=("vendor" "node_modules" "assets/css/styles.css" "assets/css/admin.css")

# Build an exclude pattern for grep
EXCLUDE_ARGS=()
for e in "${EXCLUDES[@]}"; do
  EXCLUDE_ARGS+=(--exclude-dir="$e" --exclude="$e")
done

# Search pattern for hex colors (#fff, #ffffff)
PATTERN='#[0-9a-fA-F]{3,6}'

# Files to check
FILES=$(grep -RIn --binary-files=without-match --line-number --perl-regexp "$PATTERN" -- "$(pwd)" \*.php \*.html \*.htm \*.js \*.jsx \*.json 2>/dev/null || true)

# Filter out excluded paths
if [ -n "$FILES" ]; then
  FILTERED=$(echo "$FILES" | grep -vE "(/vendor/|/node_modules/|assets/css/styles.css|assets/css/admin.css)" || true)
else
  FILTERED=""
fi

if [ -n "$FILTERED" ]; then
  echo "Found inline hex color literals in non-stylesheet files:" >&2
  echo "$FILTERED" >&2
  exit 2
fi

# No matches
exit 0
