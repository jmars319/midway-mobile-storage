2025-10-02 — Rename storage for submissions

- Renamed submissions storage from `data/messages.json` to `data/applications.json`.
- Admin UI and export/archive code updated to use the new filename.
- Added compatibility fallback: admin code will attempt to read `applications.json` first, and fall back to `messages.json` if present (short-term safety). Archive loading checks both `applications-*.json.gz` and `messages-*.json.gz` patterns.
- A gzipped backup of the legacy `messages.json` was created and is stored in `data/archives/` before the migration.

Notes:
- This change is intended to be backward-compatible for a transition window; the compatibility fallback can be removed after deployment when you're confident there are no external references to the old filename.

```markdown
2025-10-10 — Modal, footer, and content updates

- Legal modal: load Privacy/Terms into an accessible modal (fetch + extract) and improve close UX:
	- Close button converted to an inline SVG, moved into the modal header, and centralized into `includes/modal-close.php` so fetched content can't overwrite it.
	- Added a `<template id="tmpl-modal-close">` so dynamically-built modals (hours/contact/legal) can clone the shared close button.
	- Close handling is delegated on the modal container (pointerdown + click) to avoid issues when the button is replaced, and a small ripple press animation was added for tactile feedback.
	- Hours modal and contact modal updated to use the header+close pattern for consistent layout and accessibility.

- Footer and legal pages:
	- Center-aligned the footer legal links and adjusted footer grid layout.
	- Placed the phone number and Hours link side-by-side in the footer center column (responsive stack on small screens) and added a muted separator.
	- Converted static legal pages to dynamic PHP versions (`privacy-policy.php`, `terms-of-service.php`) that read `data/content.json` so phone and business info are always current. The old `.html` pages were updated to point to the dynamic pages for backward compatibility.

- Content changes:
	- Updated `data/content.json` `last_updated` timestamp and added exterior/interior/door dimension descriptions for the 20' and 40' units.

Notes:
- These updates improve accessibility, reduce DOM-replacement bugs when fetching content, and centralize shared modal markup for easier maintenance.

2025-10-07 — Remove unused Image Preview page

- Deleted `admin/image-preview.php` and removed the topbar link from `admin/index.php`.
- Updated `admin/README.md` and top-level `README.md` to reflect that image browsing is integrated into the admin UI (per-section previews and a hidden "Show all images" modal).

Notes:
- No remaining references to `image-preview.php` were found. No additional image assets required removal.

```
