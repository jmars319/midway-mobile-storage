#!/usr/bin/env python3
"""
Conservative CSS usage scanner for admin.css.

Outputs a JSON report to tmp/css_usage_report.json listing class and id selectors
that don't appear to be referenced in project files. This is intentionally
conservative and may include false positives; review before removing rules.
"""
import re
import os
import sys
import json

root = os.path.dirname(os.path.dirname(__file__))
css_path = os.path.join(root, 'assets', 'css', 'admin.css')
if not os.path.exists(css_path):
    print('admin.css not found', file=sys.stderr)
    sys.exit(1)

with open(css_path, 'r', encoding='utf-8') as f:
    css = f.read()

# find simple class and id selectors (conservative)
classes = set(re.findall(r"\.([a-zA-Z0-9_-]+)", css))
ids = set(re.findall(r"#([a-zA-Z0-9_-]+)", css))

# ignore overly-generic tokens we don't want to flag
ignore = {
    'admin', 'page-wrap', 'btn', 'modal', 'modal-backdrop', 'app-modal-dialog',
    'card', 'inline', 'img', 'row', 'col', 'btn-primary'
}

classes = {c for c in classes if c not in ignore and len(c) > 1}
ids = {i for i in ids if i not in ignore and len(i) > 1}

exts = ('.php', '.html', '.js', '.css', '.md', '.twig')
found = {('class', c): False for c in classes}
found.update({('id', i): False for i in ids})

for dirpath, dirnames, filenames in os.walk(root):
    # skip vendor, node_modules, git internals
    if '/vendor/' in dirpath or '/node_modules/' in dirpath or '/.git/' in dirpath:
        continue
    for fn in filenames:
        if not fn.lower().endswith(exts):
            continue
        path = os.path.join(dirpath, fn)
        try:
            with open(path, 'r', encoding='utf-8', errors='ignore') as fh:
                txt = fh.read()
        except Exception:
            continue

        for c in list(classes):
            # look for class="... c ..." or standalone occurrences (JS/CSS)
            if re.search(r'class=[\"\"][^\"\"]*\b' + re.escape(c) + r'\b', txt) or re.search(r'\b' + re.escape(c) + r'\b', txt):
                found[('class', c)] = True

        for i in list(ids):
            if re.search(r'id=[\"\"][^\"\"]*\b' + re.escape(i) + r'\b', txt) or re.search(r'\b' + re.escape(i) + r'\b', txt):
                found[('id', i)] = True

unused = [{'type': t, 'name': n} for (t, n), v in found.items() if not v]
report = {
    'total_classes': len(classes),
    'total_ids': len(ids),
    'unused_count': len(unused),
    'unused': unused,
}

print(json.dumps(report, indent=2))
os.makedirs(os.path.join(root, 'tmp'), exist_ok=True)
with open(os.path.join(root, 'tmp', 'css_usage_report.json'), 'w', encoding='utf-8') as out:
    out.write(json.dumps(report, indent=2))

print('\nWrote tmp/css_usage_report.json')
