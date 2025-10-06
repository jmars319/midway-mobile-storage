How to update your local clone after the history rewrite
=======================================================

Date: 2025-10-06

We rewrote the repository history and force-pushed to `origin/main`. This changes commit SHAs. To avoid merge conflicts, follow one of the options below.

If you don't have local changes (recommended)
------------------------------------------
1. Delete your local clone and re-clone:

    git clone https://github.com/jmars319/midway-mobile-storage.git

Or, reset your existing clone (destructive to uncommitted work):

    git fetch origin
    git reset --hard origin/main

If you have local branches or commits you want to keep
-----------------------------------------------------
1. Fetch the rewritten remote:

    git fetch origin

2. For each branch you want to keep, rebase it onto the rewritten main:

    git checkout your-branch
    git rebase origin/main

3. Resolve any conflicts and continue the rebase. If you have already pushed the branch, you may need to force-push it to your fork:

    git push --force-with-lease origin your-branch

If you're unsure, re-cloning is the least error-prone.

Why this is necessary
---------------------
The rewrite changed commit SHAs. If you don't update your local clone, git operations (push/pull) will become confusing and risky.

Need help?
----------
If you have questions or run into conflicts, open an issue or message the repository owner with the branch name and a short description of your local changes. We can help rebase or preserve work safely.
