#!/usr/bin/env bash
# Harden permissions for runtime secrets and uploaded files.
# Safe-by-default: runs in dry-run mode unless --apply is passed.

set -euo pipefail

DRY_RUN=1
WEB_USER="www-data"
WEB_GROUP="www-data"

usage(){
  cat <<EOF
Usage: $0 [--apply] [--user USER] [--group GROUP]

By default this prints recommended chown/chmod commands (dry-run).
Pass --apply to execute the changes. Optionally pass --user and --group
to set the web user/group (defaults: www-data:www-data).
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply) DRY_RUN=0; shift ;;
    --user) WEB_USER="$2"; shift 2 ;;
    --group) WEB_GROUP="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown arg: $1"; usage; exit 1 ;;
  esac
done

# Files and dirs to secure
declare -a DIRS=(
  "admin/auth.json"
  "private_data"
  "uploads"
  "uploads/images"
  "data"
)

echo "Permission hardening plan (web user: ${WEB_USER}:${WEB_GROUP})"
echo

run_cmd(){
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "DRY-RUN: $*"
  else
    echo "RUN: $*"
    eval "$@"
  fi
}

# 1) Protect admin auth file (if exists) — readable by web user only
if [[ -f admin/auth.json ]]; then
  run_cmd "chown ${WEB_USER}:${WEB_GROUP} admin/auth.json || true"
  run_cmd "chmod 0640 admin/auth.json || true"
else
  echo "Note: admin/auth.json not present (ok; create it on server with correct secrets)."
fi

# 2) Private data (outside webroot) — directories 750, files 640
if [[ -d private_data ]]; then
  run_cmd "chown -R ${WEB_USER}:${WEB_GROUP} private_data || true"
  run_cmd "find private_data -type d -exec chmod 0750 {} \; || true"
  run_cmd "find private_data -type f -exec chmod 0640 {} \; || true"
else
  echo "Note: private_data/ not present; create on server for sensitive uploaded files."
fi

# 3) Uploads - keep owner as web user, directories 750, files 640
if [[ -d uploads ]]; then
  run_cmd "chown -R ${WEB_USER}:${WEB_GROUP} uploads || true"
  run_cmd "find uploads -type d -exec chmod 0750 {} \; || true"
  run_cmd "find uploads -type f -exec chmod 0640 {} \; || true"
else
  echo "Note: uploads/ not present."
fi

# 4) Data files: keep writable by web user but not world-readable (640)
if [[ -d data ]]; then
  run_cmd "chown -R ${WEB_USER}:${WEB_GROUP} data || true"
  run_cmd "find data -type d -exec chmod 0750 {} \; || true"
  run_cmd "find data -type f -exec chmod 0640 {} \; || true"
fi

# 5) Composer vendor directory: not world-writeable
if [[ -d vendor ]]; then
  run_cmd "find vendor -type d -exec chmod 0755 {} \; || true"
  run_cmd "find vendor -type f -exec chmod 0644 {} \; || true"
fi

echo
if [[ $DRY_RUN -eq 1 ]]; then
  echo "Dry-run complete. Re-run with --apply to perform changes."
else
  echo "Permission changes applied."
fi

exit 0
