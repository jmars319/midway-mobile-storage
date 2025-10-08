#!/usr/bin/env bash
# Safer build script: concatenate modular admin CSS files in order and write
# to assets/css/admin.min.css. This preserves original formatting and avoids
# breaking CSS by aggressive whitespace normalization.
# Usage: ./scripts/build-admin-css.sh
set -euo pipefail
ROOT_DIR=$(cd "$(dirname "$0")/.." && pwd)
CSS_DIR="$ROOT_DIR/assets/css"
OUT_FILE="$CSS_DIR/admin.min.css"
# Files to include (order matters)
FILES=(
  "$CSS_DIR/admin.base.css"
  "$CSS_DIR/admin.menu.css"
  "$CSS_DIR/admin.modal.css"
  "$CSS_DIR/admin.css"
)

echo "/* admin.min.css - generated concat of admin.*.css - $(date -u) */" > "$OUT_FILE"
for f in "${FILES[@]}"; do
  if [ -f "$f" ]; then
    printf '\n/* >>> %s >>> */\n' "$f" >> "$OUT_FILE"
    # If this is the admin.css wrapper, strip any @import lines (they would be invalid
    # in the middle of a concatenated bundle and would re-import files we've already
    # added). For other files, append as-is.
    if [[ "$f" =~ /admin\.css$ ]]; then
      # remove lines that start with optional whitespace followed by @import
      awk '!/^[[:space:]]*@import/' "$f" >> "$OUT_FILE"
    else
      cat "$f" >> "$OUT_FILE"
    fi
  else
    echo "/* missing: $f */" >> "$OUT_FILE"
  fi
done

# Update timestamp for cache-busting
touch "$OUT_FILE"
echo "Wrote $OUT_FILE"
