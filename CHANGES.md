2025-10-02 — Rename storage for submissions

- Renamed submissions storage from `data/messages.json` to `data/applications.json`.
- Admin UI and export/archive code updated to use the new filename.
- Added compatibility fallback: admin code will attempt to read `applications.json` first, and fall back to `messages.json` if present (short-term safety). Archive loading checks both `applications-*.json.gz` and `messages-*.json.gz` patterns.
- A gzipped backup of the legacy `messages.json` was created and is stored in `data/archives/` before the migration.

Notes:
- This change is intended to be backward-compatible for a transition window; the compatibility fallback can be removed after deployment when you're confident there are no external references to the old filename.

```markdown
2025-10-07 — Remove unused Image Preview page

- Deleted `admin/image-preview.php` and removed the topbar link from `admin/index.php`.
- Updated `admin/README.md` and top-level `README.md` to reflect that image browsing is integrated into the admin UI (per-section previews and a hidden "Show all images" modal).

Notes:
- No remaining references to `image-preview.php` were found. No additional image assets required removal.

```
