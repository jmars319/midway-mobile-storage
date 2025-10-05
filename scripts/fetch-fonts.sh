#!/usr/bin/env bash
# fetch-fonts.sh
# Attempt to fetch WOFF2 font files for local self-hosting into assets/fonts/
# NOTE: This script tries well-known Google Fonts static URLs but may fail
# if the upstream changes. If a download fails, the script prints the URL so
# you can fetch manually.

set -euo pipefail
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FONTS_DIR="$ROOT_DIR/assets/fonts"
mkdir -p "$FONTS_DIR"

declare -A files
files[Inter-300.woff2]="https://github.com/google/fonts/raw/main/ofl/inter/Inter%5Bslnt%2Cwght%5D.ttf"
# NOTE: the above is a TTF pointer—preferred source is to download from Google Fonts or the font project and generate WOFF2. The script will attempt to download any known WOFF2 if available.

echo "This script provides guidance and best-effort downloads. In many cases you'll need to download WOFF2 files manually (see assets/fonts/README.md)."

echo "No automated WOFF2 downloads are configured by default. Please place WOFF2 files in: $FONTS_DIR"
echo "Expected filenames (see README):"
cat "$ROOT_DIR/assets/fonts/README.md"

exit 0
