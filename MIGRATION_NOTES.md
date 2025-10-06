MIGRATION NOTES
================

Date: 2025-10-06

Summary
-------
This repository's commit history was rewritten on 2025-10-06 to correct the commit author/committer identity for existing commits. All commits previously authored or committed as

    Jason Marshall <jason_marshall@MacBookAir.attlocal.net>

have been updated to:

    Jason Marshall <97139706+jmars319@users.noreply.github.com>

Why
---
- Ensure proper attribution of past work and align repository history with the desired GitHub Account email.

What was done
--------------
- A mirror backup of the repository was created before any changes:

    /Users/jason_marshall/Documents/Website Projects/Current/midway-mobile-storage-backup-20251006.git

- Because `git-filter-repo` was not available on the system, `git filter-branch` (fallback) was used to rewrite history and update author/committer name and email for commits that used the old email.
- After the rewrite, filter-branch backup refs were removed, a garbage collection was run, and the rewritten branches and tags were force-pushed to `origin`.

New author identity
-------------------
- Name: Jason Marshall
- Email: 97139706+jmars319@users.noreply.github.com

Local verification
------------------
- After the rewrite, the repository's commit author summary shows the adjusted identity for the rewritten commits.

Important notes & risks
-----------------------
- Rewriting history changes commit SHAs. Any clones, forks, CI caches, or other clones of this repository will diverge and must be updated. Collaborators who have local clones must take action described below.
- Force-pushing was performed to update the remote to match the rewritten history.
- Keep the mirror backup (path above) until you are confident everything is correct. It contains the pre-rewrite repository state.

What contributors should do (recommended)
----------------------------------------
If you have a local clone of this repository, follow one of these approaches depending on your preference:

1) Re-clone (recommended, simplest):

    git clone https://github.com/jmars319/midway-mobile-storage.git

2) Reset existing clone to the rewritten remote (destructive to local uncommitted work):

    git fetch origin
    git reset --hard origin/main

3) If you have local branches or commits you want to keep, rebase them onto the rewritten main:

    git fetch origin
    git checkout your-branch
    git rebase origin/main

If you are unsure, re-cloning is the least error-prone.

Notes about tooling
-------------------
- For more complex history operations in the future, consider installing and using `git-filter-repo` (https://github.com/newren/git-filter-repo/) — it is safer and faster than `git filter-branch`.

Contact
-------
If you want me to revert this rewrite, run further rewrites, or add/remove other historical metadata, tell me what to do and I can prepare a safe plan (I made a pre-rewrite mirror backup already).

Commit created with this change:

- docs(migration): add migration notes for history rewrite — assign commits to Jason Marshall
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
