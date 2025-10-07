#!/usr/bin/env bash
set -euo pipefail

echo "Running inline-style check (non-vendor files)..."

# Search tracked files using git grep for two patterns:
#  - style="  -> inline style attributes in HTML/PHP/MD
#  - \.style\b -> JS .style property accesses
# We explicitly allow uses of .style.setProperty (CSS variables) because
# the project's pattern uses CSS variables via setProperty for numeric values.

set +e
raw_matches=$(git grep -nE --no-color -e 'style="' -e '\.style\b' 2>/dev/null || true)
git_grep_exit=0
set -e

if [ $git_grep_exit -ne 0 ] && [ -z "$raw_matches" ]; then
  echo "PASS: No non-vendor inline style occurrences found."
  exit 0
fi

# Filter out vendor and other excluded paths and allow .style.setProperty
# Also ignore matches in markdown and shell script files and anything under scripts/
filtered=$(printf "%s\n" "$raw_matches" \
  | grep -vE '/vendor/|/node_modules/|/\.github/|/\.git/|/uploads/|/tmp/|/scripts/' \
  | grep -vE '\.md:|\.sh:' \
  | grep -v '\.style\.setProperty' \
  || true)

if [ -z "$filtered" ]; then
  echo "PASS: No non-vendor inline style occurrences found."
  exit 0
else
  echo "FAIL: Found non-vendor inline style occurrences:" >&2
  echo "$filtered" >&2
  echo "\nNotes: .style.setProperty (CSS variables) is allowed. Move other inline presentation rules to CSS classes or CSS variables." >&2
  exit 1
fi
