Thank you for contributing! A few lightweight rules help keep presentation separated from logic in this repository.

Inline style rule
------------------
- Avoid inline presentation styles in application code (HTML/PHP/JS/Markdown).
- Allowed patterns:
  - CSS class toggles (e.g., `element.classList.add('open')`).
  - CSS variables set via JavaScript using `.style.setProperty('--var', value)` for dynamic numeric values.
- Disallowed patterns (will fail CI and local hook):
  - `style="..."` attributes in HTML/PHP/MD
  - direct `.style.<property> = ...` writes in JS (except `.style.setProperty(...)`)

Why
---
Centralizing presentation in CSS improves maintainability, minimizes visual regression risk, and makes runtime themes (e.g., dark mode) easier to support.

How to fix violations
---------------------
1. Move static rules into CSS classes and apply those classes in your templates or JS.
2. For dynamic sizes or transforms, set CSS variables from JS and let CSS consume them.
   - Example: `el.style.setProperty('--y', y + 'px')` combined with `.hero { transform: translateY(var(--y)); }`.
3. If a vendor file is the source of the violation, do not edit vendor files; open an issue or add an override in CSS.

Local hooks
-----------
This repo includes a pre-commit hook under `.githooks/pre-commit`. To enable it locally, run:

```bash
# from repo root
git config core.hooksPath .githooks
```

That will run the `scripts/validate-no-inline-styles.sh` script before each commit. The CI workflow also runs the same check on push/PRs.

If you prefer not to enable hooks (e.g., CI will still enforce the rule), you can skip local hooks by temporarily unsetting `core.hooksPath` or using `git commit --no-verify` (not recommended for routine commits).

Thanks for helping keep styles centralized!
