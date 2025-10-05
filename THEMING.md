THEMING GUIDE
=============

Overview
--------
This project centralizes color and layout tokens in `assets/css/styles.css`.
Modify the colors there to change the site's look. The admin UI (`assets/css/admin.css`) now references those tokens so the admin matches the public site.

Key tokens (examples)
- `--primary-color` — brand primary (buttons, links)
- `--secondary-color` — accent for warnings/secondary CTAs
- `--accent-color` — neutral accent
- `--success-color`, `--error-color` — status colors
- `--background-color`, `--surface-color` — background surfaces
- `--text-primary`, `--text-muted` — typographic colors

Guidelines
----------
- Always edit `assets/css/styles.css` for color changes. That file is the single source of truth.
- Do not add hex literals directly in PHP/HTML/JS templates. Instead, add a new CSS variable in `styles.css` and reference it from `admin.css` or your template CSS.

Automated check
---------------
There's a small script that fails if non-token hex literals appear in PHP/HTML/JS/JSON files:

  scripts/check-no-inline-hex.sh

Run it locally to verify your changes before committing. It intentionally excludes `vendor/`, `node_modules/` and the canonical stylesheets `assets/css/styles.css` and `assets/css/admin.css`.

Suggested pre-commit hook
-------------------------
Add this to `.git/hooks/pre-commit` (make it executable):

```sh
#!/usr/bin/env bash
./scripts/check-no-inline-hex.sh
if [ $? -ne 0 ]; then
  echo "Inline hex color check failed. Fix inline color literals first." >&2
  exit 1
fi
```

Questions
---------
If you want the admin to use slightly different shades, we can map specific admin variables to different derived shades in `admin.css`. Right now admin references the canonical tokens to keep the palette consistent.

Done.
