Self-hosted fonts for Midway Mobile Storage

Place the following WOFF2 files into this folder: `assets/fonts/`

Recommended subset (WOFF2):
- Inter-300.woff2
- Inter-400.woff2
- Inter-500.woff2
- Inter-600.woff2
- Inter-700.woff2

- Montserrat-600.woff2
- Montserrat-700.woff2

- RobotoCondensed-400.woff2
- RobotoCondensed-700.woff2

- RobotoMono-400.woff2
- RobotoMono-700.woff2

Notes:
- Download these files from the official font sources (Google Fonts download or the font project pages).
- Using WOFF2 provides the best compression for modern browsers. If you need broader legacy support, add WOFF copies and update the @font-face src fallback list.
- Keep filenames exactly as listed above (case-sensitive on many servers) or update the src paths in `assets/css/styles.css` to match your filenames.
- If you prefer to self-host variable-font versions, you can include a single variable font file per family and adjust the @font-face declarations accordingly.
- After placing the files, clear caches or bump your HTML/CSS cache-busting parameter to ensure browsers pick up the new fonts.
