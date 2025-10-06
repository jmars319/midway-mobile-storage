Migration notes — reservations → quotes

Summary
-------
This repo was migrated from a "reservation" workflow to a "storage quote" workflow.
The change replaces the public reservation form with a storage-container quote form and
converts server-side and admin handling from reservations → quotes.

What changed
------------
- Public form now posts to `admin/reserve.php` which writes sanitized quote entries to `data/quotes.json`.
- An append-only audit is written to `data/quote-audit.json`.
- Admin UI (`admin/index.php`) exposes CSV/JSON downloads and a Quote Audit viewer at `admin/quote-audit.php`.
- Legacy artifacts referencing "reservation" were removed or updated:
  - `admin/reservation-audit.php` removed (legacy path deleted per request).
  - Documentation updated to reference "quote" and the new audit file.
- Notification config constant renamed to `QUOTE_NOTIFICATION_EMAIL` (set in `admin/config.php`).
- Email sending is disabled during CLI tests and can be turned off with env var `DISABLE_EMAILS=1`.

Files changed (high level)
-------------------------
- admin/reserve.php                 (quote handler, sanitization, audit)
- admin/index.php                   (admin UI changed to show quotes)
- admin/quote-audit.php             (new audit viewer for quote audit)
- admin/config.php                  (QUOTE_NOTIFICATION_EMAIL)
- admin/smtp-settings.php           (updated help text)
- assets/js/inline.js, assets/js/main.js (client text & comments)
- data/content.json                 (hero CTA updated to `#storage-quote`)
- README.md, admin/README.md, README-clean.md updated
- .gitignore updated to ignore generated `data/quotes.json` and `data/quote-audit.json`

Testing
-------
- CLI test helper (used during development) was removed. You can test locally by:
  1. Start the dev server: `./dev.sh start` or `php -S 127.0.0.1:8000 -t .`
  2. Open the site and submit the storage quote form (Hero button → "Get a Quote").
  3. Confirm the entry appears in the admin UI at `/admin/index.php` and is present in `data/quotes.json`.

Notes
-----
- The handler will attempt to send notifications to `QUOTE_NOTIFICATION_EMAIL`. To disable email sending
  during tests or CI set the environment variable `DISABLE_EMAILS=1` or run via CLI (which automatically disables emails).
- Generated quote data is ignored by git (`.gitignore` updated). Remove the ignore if you want to keep a persistent dataset in the repo (not recommended).

Contact
-------
If anything needs adjusting (re-add redirect, change CSV exports, rename config constant back for compatibility), open an issue or tell me which adjustments you'd like and I will apply them.
